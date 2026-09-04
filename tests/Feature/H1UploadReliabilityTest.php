<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class H1UploadReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_profile_save_false_preserves_media_and_cleans_new_files(): void
    {
        $this->assertProfileSaveFalseIsAtomic('profile.update', 'patch');
    }

    public function test_custom_profile_save_false_preserves_media_and_cleans_new_files(): void
    {
        $this->assertProfileSaveFalseIsAtomic('user.update', 'post');
    }

    public function test_standard_profile_post_write_event_failure_rolls_back_media_update(): void
    {
        $this->assertProfilePostWriteFailureIsAtomic('profile.update', 'patch');
    }

    public function test_custom_profile_post_write_event_failure_rolls_back_media_update(): void
    {
        $this->assertProfilePostWriteFailureIsAtomic('user.update', 'post');
    }

    public function test_standard_profile_avatar_only_failure_preserves_cover(): void
    {
        $this->assertProfileUploadFailureIsAtomic('profile.update', 'patch', [
            'avatar' => UploadedFile::fake()->image('new-avatar.jpg'),
        ]);
    }

    public function test_custom_profile_avatar_only_failure_preserves_cover(): void
    {
        $this->assertProfileUploadFailureIsAtomic('user.update', 'post', [
            'avatar' => UploadedFile::fake()->image('new-avatar.jpg'),
        ]);
    }

    public function test_standard_profile_cover_only_failure_preserves_avatar(): void
    {
        $this->assertProfileUploadFailureIsAtomic('profile.update', 'patch', [
            'cover' => UploadedFile::fake()->image('new-cover.jpg'),
        ]);
    }

    public function test_custom_profile_cover_only_failure_preserves_avatar(): void
    {
        $this->assertProfileUploadFailureIsAtomic('user.update', 'post', [
            'cover' => UploadedFile::fake()->image('new-cover.jpg'),
        ]);
    }

    public function test_standard_profile_avatar_replacement_removes_old_file_after_success(): void
    {
        $this->assertSuccessfulProfileReplacement('profile.update', 'patch', 'avatar');
    }

    public function test_custom_profile_avatar_replacement_removes_old_file_after_success(): void
    {
        $this->assertSuccessfulProfileReplacement('user.update', 'post', 'avatar');
    }

    public function test_standard_profile_cover_replacement_removes_old_file_after_success(): void
    {
        $this->assertSuccessfulProfileReplacement('profile.update', 'patch', 'cover');
    }

    public function test_custom_profile_cover_replacement_removes_old_file_after_success(): void
    {
        $this->assertSuccessfulProfileReplacement('user.update', 'post', 'cover');
    }

    public function test_custom_profile_update_preserves_bio_when_it_is_omitted(): void
    {
        $user = User::factory()->create(['bio' => 'Existing bio']);

        $this->actingAs($user)
            ->post(route('user.update'), ['name' => 'Updated Name'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.profile', $user));

        $this->assertSame('Existing bio', $user->refresh()->bio);
    }

    public function test_post_files_and_database_rows_are_rolled_back_when_hashtag_sync_fails(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER fail_hashtag_sync
            BEFORE INSERT ON hashtag_post
            BEGIN
                SELECT RAISE(ABORT, 'forced hashtag sync failure');
            END
            SQL);

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'content' => 'Post that must roll back #failure',
            'images' => [UploadedFile::fake()->image('rollback.jpg')],
        ]);

        $response->assertServerError();
        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('hashtags', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('posts'));
    }

    private function assertProfileSaveFalseIsAtomic(string $routeName, string $method): void
    {
        [$user, $oldAvatar, $oldCover] = $this->createProfileWithMedia();

        User::saving(function (User $savingUser) use ($user) {
            if ($savingUser->is($user)) {
                return false;
            }

            return null;
        });

        try {
            $this->assertProfileRequestFails(
                $user,
                $routeName,
                $method,
                [
                    'avatar' => UploadedFile::fake()->image('new-avatar.jpg'),
                    'cover' => UploadedFile::fake()->image('new-cover.jpg'),
                ],
                'Failed to save profile changes.',
            );
        } finally {
            User::flushEventListeners();
        }

        $this->assertOriginalProfileMediaRemains($user, $oldAvatar, $oldCover);
    }

    private function assertProfilePostWriteFailureIsAtomic(string $routeName, string $method): void
    {
        [$user, $oldAvatar, $oldCover] = $this->createProfileWithMedia();

        User::saved(function (User $savedUser) use ($user): void {
            if ($savedUser->is($user)) {
                throw new RuntimeException('forced post-write profile failure');
            }
        });

        try {
            $this->assertProfileRequestFails(
                $user,
                $routeName,
                $method,
                [
                    'avatar' => UploadedFile::fake()->image('new-avatar.jpg'),
                    'cover' => UploadedFile::fake()->image('new-cover.jpg'),
                ],
                'forced post-write profile failure',
            );
        } finally {
            User::flushEventListeners();
        }

        $this->assertOriginalProfileMediaRemains($user, $oldAvatar, $oldCover);
    }

    private function assertProfileUploadFailureIsAtomic(string $routeName, string $method, array $files): void
    {
        [$user, $oldAvatar, $oldCover] = $this->createProfileWithMedia();

        User::saving(function (User $savingUser) use ($user): void {
            if ($savingUser->is($user)) {
                throw new RuntimeException('forced profile save failure');
            }
        });

        try {
            $this->assertProfileRequestFails(
                $user,
                $routeName,
                $method,
                $files,
                'forced profile save failure',
            );
        } finally {
            User::flushEventListeners();
        }

        $this->assertOriginalProfileMediaRemains($user, $oldAvatar, $oldCover);
    }

    private function assertSuccessfulProfileReplacement(string $routeName, string $method, string $field): void
    {
        [$user, $oldAvatar, $oldCover] = $this->createProfileWithMedia();
        $newFile = UploadedFile::fake()->image("replacement-{$field}.jpg");

        $response = $this->submitProfileUpdate($user, $routeName, $method, [
            $field => $newFile,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(
            $routeName === 'profile.update'
                ? route('profile.edit')
                : route('user.profile', $user),
        );

        $user->refresh();
        $newPath = $user->{$field};

        $this->assertIsString($newPath);
        $this->assertNotSame($field === 'avatar' ? $oldAvatar : $oldCover, $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($field === 'avatar' ? $oldAvatar : $oldCover);

        if ($field === 'avatar') {
            $this->assertSame($oldCover, $user->cover);
            Storage::disk('public')->assertExists($oldCover);
        } else {
            $this->assertSame($oldAvatar, $user->avatar);
            Storage::disk('public')->assertExists($oldAvatar);
        }
    }

    private function assertProfileRequestFails(
        User $user,
        string $routeName,
        string $method,
        array $files,
        string $message,
    ): void {
        $this->withoutExceptionHandling();

        try {
            $this->submitProfileUpdate($user, $routeName, $method, $files);
            $this->fail('Expected the profile update to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    private function submitProfileUpdate(User $user, string $routeName, string $method, array $overrides = [])
    {
        $payload = [
            'name' => $user->name,
        ];

        if ($routeName === 'profile.update') {
            $payload['username'] = $user->username;
            $payload['email'] = $user->email;
        }

        $payload = array_merge($payload, $overrides);
        $request = $this->actingAs($user);

        return $method === 'patch'
            ? $request->patch(route($routeName), $payload)
            : $request->post(route($routeName), $payload);
    }

    private function createProfileWithMedia(): array
    {
        Storage::fake('public');

        $oldAvatar = 'avatars/original-avatar.jpg';
        $oldCover = 'covers/original-cover.jpg';
        Storage::disk('public')->put($oldAvatar, 'old avatar');
        Storage::disk('public')->put($oldCover, 'old cover');

        $user = User::factory()->create([
            'avatar' => $oldAvatar,
            'cover' => $oldCover,
        ]);

        return [$user, $oldAvatar, $oldCover];
    }

    private function assertOriginalProfileMediaRemains(User $user, string $oldAvatar, string $oldCover): void
    {
        $user->refresh();

        $this->assertSame($oldAvatar, $user->avatar);
        $this->assertSame($oldCover, $user->cover);
        Storage::disk('public')->assertExists($oldAvatar);
        Storage::disk('public')->assertExists($oldCover);
        $this->assertSame(
            [$oldAvatar, $oldCover],
            collect(Storage::disk('public')->allFiles())->sort()->values()->all(),
        );
    }
}
