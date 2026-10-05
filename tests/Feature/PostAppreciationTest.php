<?php

namespace Tests\Feature;

use App\Actions\ApplyPointTransaction;
use App\Actions\GivePostAppreciation;
use App\Enums\AppreciationType;
use App\Enums\PointTransactionType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostAppreciationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_can_appreciate_another_users_post_once_for_five_points_without_crediting_owner(): void
    {
        $sender = User::factory()->create();
        $owner = User::factory()->create();
        $post = $this->createPost($owner);
        $this->credit($sender, 12);

        $response = $this->actingAs($sender)->post('/posts/'.$post->uid.'/appreciation', [
            'type' => 'bermanfaat',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseCount('post_appreciations', 1);
        $this->assertDatabaseHas('post_appreciations', [
            'user_id' => $sender->getKey(),
            'post_id' => $post->getKey(),
            'type' => 'bermanfaat',
            'points_spent' => 5,
        ]);
        $this->assertSame(7, (int) $sender->pointWallet()->value('balance'));
        $this->assertNull($owner->pointWallet()->value('balance'));
        $this->assertDatabaseCount('point_transactions', 2);
        $this->assertDatabaseHas('point_transactions', [
            'wallet_id' => $sender->pointWallet()->value('id'),
            'type' => 'debit',
            'amount' => -5,
            'reason_code' => 'post_appreciation',
        ]);
    }

    public function test_different_senders_can_appreciate_the_same_post_using_their_own_wallets(): void
    {
        $firstSender = User::factory()->create();
        $secondSender = User::factory()->create();
        $post = $this->createPost(User::factory()->create());
        $this->credit($firstSender, 5);
        $this->credit($secondSender, 5);

        $this->actingAs($firstSender)
            ->post('/posts/'.$post->uid.'/appreciation', ['type' => 'bermanfaat'])
            ->assertRedirect();
        $this->actingAs($secondSender)
            ->post('/posts/'.$post->uid.'/appreciation', ['type' => 'inspiratif'])
            ->assertRedirect();

        $this->assertDatabaseCount('post_appreciations', 2);
        $this->assertSame(0, (int) $firstSender->pointWallet()->value('balance'));
        $this->assertSame(0, (int) $secondSender->pointWallet()->value('balance'));
        $this->assertSame(2, DB::table('point_transactions')
            ->where('type', 'debit')
            ->where('reason_code', 'post_appreciation')
            ->count());
        $this->assertSame(2, DB::table('point_transactions')
            ->where('reason_code', 'post_appreciation')
            ->distinct()
            ->count('wallet_id'));
    }

    public function test_each_locked_appreciation_type_is_accepted(): void
    {
        $sender = User::factory()->create();
        $owner = User::factory()->create();
        $this->credit($sender, 15);

        foreach (['bermanfaat', 'inspiratif', 'solutif'] as $index => $type) {
            $post = $this->createPost($owner, ['content' => 'Allowed type target '.$index]);

            $this->actingAs($sender)
                ->post('/posts/'.$post->uid.'/appreciation', ['type' => $type])
                ->assertRedirect();
        }

        $this->assertDatabaseCount('post_appreciations', 3);
        $this->assertSame(
            ['bermanfaat', 'inspiratif', 'solutif'],
            DB::table('post_appreciations')->orderBy('id')->pluck('type')->all(),
        );
        $this->assertSame(0, (int) $sender->pointWallet()->value('balance'));
    }

    public function test_invalid_appreciation_type_is_rejected_without_spending_points(): void
    {
        $sender = User::factory()->create();
        $post = $this->createPost(User::factory()->create());
        $this->credit($sender, 10);

        $this->actingAs($sender)
            ->post('/posts/'.$post->uid.'/appreciation', ['type' => 'recognition'])
            ->assertSessionHasErrors(['type']);

        $this->assertDatabaseCount('post_appreciations', 0);
        $this->assertDatabaseCount('point_transactions', 1);
        $this->assertSame(10, (int) $sender->pointWallet()->value('balance'));
    }

    public function test_client_supplied_amount_fields_never_change_the_server_owned_five_point_cost(): void
    {
        $sender = User::factory()->create();
        $owner = User::factory()->create();
        $this->credit($sender, 25);

        foreach ([0, 1, 999, -5, 'custom'] as $index => $submittedAmount) {
            $post = $this->createPost($owner, ['content' => 'Cost target '.$index]);

            $this->actingAs($sender)
                ->post('/posts/'.$post->uid.'/appreciation', [
                    'type' => ['bermanfaat', 'inspiratif', 'solutif'][$index % 3],
                    'user_id' => $owner->getKey(),
                    'points_spent' => $submittedAmount,
                    'amount' => $submittedAmount,
                    'cost' => $submittedAmount,
                ])
                ->assertRedirect();
        }

        $this->assertDatabaseCount('post_appreciations', 5);
        $this->assertSame([5, 5, 5, 5, 5], DB::table('post_appreciations')->orderBy('id')->pluck('points_spent')->all());
        $this->assertSame(
            array_fill(0, 5, $sender->getKey()),
            DB::table('post_appreciations')->orderBy('id')->pluck('user_id')->map(static fn ($id): int => (int) $id)->all(),
        );
        $this->assertSame(-25, (int) DB::table('point_transactions')->where('type', 'debit')->sum('amount'));
        $this->assertSame(0, (int) $sender->pointWallet()->value('balance'));
    }

    public function test_duplicate_and_different_type_retries_keep_the_original_appreciation_and_debit(): void
    {
        $sender = User::factory()->create();
        $post = $this->createPost(User::factory()->create());
        $this->credit($sender, 15);
        $url = '/posts/'.$post->uid.'/appreciation';

        $this->actingAs($sender)->post($url, ['type' => 'bermanfaat'])->assertRedirect();
        $this->post($url, ['type' => 'solutif'])->assertRedirect();

        $this->assertDatabaseCount('post_appreciations', 1);
        $this->assertDatabaseHas('post_appreciations', [
            'user_id' => $sender->getKey(),
            'post_id' => $post->getKey(),
            'type' => 'bermanfaat',
            'points_spent' => 5,
        ]);
        $this->assertSame(10, (int) $sender->pointWallet()->value('balance'));
        $this->assertSame(2, DB::table('point_transactions')->where('wallet_id', $sender->pointWallet()->value('id'))->count());
    }

    public function test_database_unique_constraint_rejects_a_second_sender_post_pair(): void
    {
        $sender = User::factory()->create();
        $post = $this->createPost(User::factory()->create());
        $this->credit($sender, 5);
        $this->actingAs($sender)->post('/posts/'.$post->uid.'/appreciation', ['type' => 'bermanfaat'])->assertRedirect();

        try {
            DB::table('post_appreciations')->insert([
                'user_id' => $sender->getKey(),
                'post_id' => $post->getKey(),
                'type' => 'solutif',
                'points_spent' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('The database must enforce one Appreciation per sender and post.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseCount('post_appreciations', 1);
        }
    }

    public function test_self_appreciation_is_rejected_without_a_row_or_debit(): void
    {
        $owner = User::factory()->create();
        $post = $this->createPost($owner);
        $this->credit($owner, 10);

        $this->actingAs($owner)
            ->post('/posts/'.$post->uid.'/appreciation', ['type' => 'inspiratif'])
            ->assertRedirect();

        $this->assertDatabaseCount('post_appreciations', 0);
        $this->assertDatabaseCount('point_transactions', 1);
        $this->assertSame(10, (int) $owner->pointWallet()->value('balance'));
    }

    public function test_insufficient_balance_rolls_back_the_appreciation_and_keeps_balance_nonnegative(): void
    {
        $sender = User::factory()->create();
        $post = $this->createPost(User::factory()->create());

        $this->actingAs($sender)
            ->post('/posts/'.$post->uid.'/appreciation', ['type' => 'solutif'])
            ->assertRedirect();

        $this->assertDatabaseCount('post_appreciations', 0);
        $this->assertDatabaseCount('point_transactions', 0);
        $this->assertNull($sender->pointWallet()->value('balance'));
    }

    public function test_exactly_enough_points_for_one_post_cannot_fund_a_second_appreciation(): void
    {
        $sender = User::factory()->create();
        $owner = User::factory()->create();
        $firstPost = $this->createPost($owner, ['content' => 'First limited balance target']);
        $secondPost = $this->createPost($owner, ['content' => 'Second limited balance target']);
        $this->credit($sender, 5);

        $this->actingAs($sender)->post('/posts/'.$firstPost->uid.'/appreciation', ['type' => 'bermanfaat'])->assertRedirect();
        $this->post('/posts/'.$secondPost->uid.'/appreciation', ['type' => 'inspiratif'])->assertRedirect();

        $this->assertDatabaseCount('post_appreciations', 1);
        $this->assertSame(0, (int) $sender->pointWallet()->value('balance'));
        $this->assertSame(-5, (int) DB::table('point_transactions')->where('type', 'debit')->sum('amount'));
    }

    public function test_get_requests_never_create_appreciations_or_spend_points(): void
    {
        $viewer = User::factory()->create();
        $post = $this->createPost(User::factory()->create());

        $this->actingAs($viewer)->get(route('feed'))->assertOk();
        $this->get(route('posts.show', $post))->assertOk();
        $this->get('/posts/'.$post->uid.'/appreciation')->assertStatus(405);

        $this->assertDatabaseCount('post_appreciations', 0);
        $this->assertDatabaseCount('point_transactions', 0);
    }

    public function test_ledger_failure_rolls_back_the_appreciation_record(): void
    {
        $sender = User::factory()->create();
        $post = $this->createPost(User::factory()->create());
        $this->credit($sender, 10);
        $ledger = new class extends ApplyPointTransaction
        {
            public function handle(
                User $owner,
                PointTransactionType $type,
                mixed $amount,
                string $reasonCode,
                string $referenceType,
                string $referenceKey,
                string $idempotencyKey,
            ): \App\Models\PointTransaction {
                throw new \RuntimeException('Simulated ledger failure.');
            }
        };

        try {
            (new GivePostAppreciation($ledger))->handle($sender, $post, AppreciationType::Bermanfaat);
            $this->fail('A ledger failure must abort Appreciation creation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated ledger failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('post_appreciations', 0);
        $this->assertDatabaseCount('point_transactions', 1);
        $this->assertSame(10, (int) $sender->pointWallet()->value('balance'));
    }

    public function test_appreciation_debit_has_a_localized_private_history_label(): void
    {
        $sender = User::factory()->create(['locale' => 'id']);
        $post = $this->createPost(User::factory()->create());
        $this->credit($sender, 5);

        $response = $this->actingAs($sender)->postJson("/posts/{$post->uid}/appreciation", [
            'type' => 'solutif',
        ]);

        $response->assertCreated();
        $this->actingAs($sender)
            ->get(route('points.history'))
            ->assertOk()
            ->assertSee('Apresiasi Postingan')
            ->assertSee('−5 Poin')
            ->assertDontSee('pa-v1-');
    }

    private function credit(User $user, int $amount): void
    {
        app(ApplyPointTransaction::class)->handle(
            $user,
            PointTransactionType::Credit,
            $amount,
            'test_credit',
            'test',
            'post-appreciation-credit-'.$user->getKey(),
            'r6c:test:credit:'.$user->getKey(),
        );
    }

    private function createPost(User $user, array $attributes = []): Post
    {
        return Post::query()->create(array_merge([
            'user_id' => $user->getKey(),
            'content' => 'Post appreciation target.',
        ], $attributes));
    }
}
