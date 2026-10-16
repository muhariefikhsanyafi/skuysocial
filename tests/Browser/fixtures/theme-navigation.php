<?php

use App\Actions\ApplyPointTransaction;
use App\Actions\GivePostAppreciation;
use App\Enums\AppreciationType;
use App\Enums\PointTransactionType;
use App\Enums\PostAppreciationStatus;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Hashtag;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostLiked;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = realpath(config('database.connections.sqlite.database'));
$temporaryRoot = realpath(sys_get_temp_dir());
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || ! $database || ! str_starts_with($database, $temporaryRoot.DIRECTORY_SEPARATOR.'skuy-theme-browser-')) {
    throw new RuntimeException('Browser fixtures require their own temporary SQLite database.');
}

if (($argv[1] ?? '') === 'unread') {
    User::query()->where('username', 'qa.theme.'.(int) $argv[2])->firstOrFail()
        ->notifications()->update(['read_at' => null]);
    exit;
}

if (($argv[1] ?? '') === 'social-state') {
    $viewer = User::query()->where('username', 'qa.theme.'.(int) $argv[2])->firstOrFail();
    $post = Post::query()->where('uid', $argv[3])->firstOrFail();
    echo json_encode([
        'liked' => $post->likes()->where('user_id', $viewer->id)->exists(),
        'bookmarked' => $post->bookmarks()->where('user_id', $viewer->id)->exists(),
    ], JSON_THROW_ON_ERROR);
    exit;
}

if (($argv[1] ?? '') === 'appreciation-state') {
    $viewer = User::query()->where('username', 'qa.theme.'.(int) $argv[2])->firstOrFail();
    $post = Post::query()->where('uid', $argv[3])->firstOrFail();
    echo json_encode([
        'balance' => (int) $viewer->pointWallet()->value('balance'),
        'appreciation' => $post->appreciations()->where('user_id', $viewer->id)->value('type'),
        'pointsSpent' => $post->appreciations()->where('user_id', $viewer->id)->value('points_spent'),
    ], JSON_THROW_ON_ERROR);
    exit;
}

$migrationExit = Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]);
if ($migrationExit !== 0) {
    throw new RuntimeException(Artisan::output());
}
$author = User::factory()->create([
    'name' => 'QA Theme Author', 'username' => 'qa.theme.author', 'email' => 'author@example.test',
    'is_verified' => true, 'cover' => null,
]);
$emptyVerified = User::factory()->create([
    'name' => 'QA Empty Verified Profile', 'username' => 'qa.theme.empty.verified',
    'email' => 'empty-verified@example.test', 'cover' => null, 'bio' => null,
    'is_verified' => true,
]);
$post = null;
$savedPost = null;
$posts = [];
for ($index = 0; $index < 24; $index++) {
    $post = Post::query()->create([
        'user_id' => $author->id, 'content' => 'Synthetic theme and navigation regression post '.($index + 1),
    ]);
    $posts[] = $post;

    if ($index === 0) {
        $savedPost = $post;
    }
}
$accounts = [];
$flowEditPost = null;
$flowConversation = null;
foreach ([0, 1, 2, 12] as $count) {
    $viewer = User::factory()->create([
        'name' => 'QA Theme '.$count, 'username' => 'qa.theme.'.$count, 'email' => 'theme'.$count.'@example.test',
    ]);
    $viewer->forceFill(['locale' => $count === 12 ? 'en' : 'id'])->save();
    if ($count === 2) {
        Bookmark::query()->create(['user_id' => $viewer->id, 'post_id' => $savedPost->id]);
    }
    if (in_array($count, [0, 2], true)) {
        foreach ([23, 12, 0] as $bookmarkOrder => $postIndex) {
            if ($count === 2 && $postIndex === 0) {
                continue;
            }

            Bookmark::query()->create([
                'user_id' => $viewer->id,
                'post_id' => $posts[$postIndex]->id,
                'created_at' => now()->addSeconds($bookmarkOrder + ($count === 2 ? 10 : 0)),
            ]);
        }

        app(ApplyPointTransaction::class)->handle(
            $viewer,
            PointTransactionType::Credit,
            100,
            'test_credit',
            'test',
            'theme-browser-credit-'.$viewer->id,
            'r6c:theme-browser:credit:'.$viewer->id,
        );
    }
    $ownPost = Post::query()->create([
        'user_id' => $viewer->id,
        'content' => 'Synthetic own profile post',
        'topic' => $count === 2 ? 'flowqa' : null,
    ]);
    if ($count === 2) {
        $flowEditPost = $ownPost;
        $flowTag = Hashtag::query()->firstOrCreate(['name' => 'flowqa']);
        $flowEditPost->hashtags()->sync([$flowTag->id]);
    }
    Post::query()->create(['user_id' => $viewer->id, 'content' => '', 'repost_id' => $post->id]);
    $conversation = Conversation::query()->create([
        'type' => Conversation::TYPE_DIRECT, 'direct_key' => $author->id.':'.$viewer->id,
    ]);
    $conversation->users()->attach([$author->id, $viewer->id]);
    if ($count === 2) {
        $flowConversation = $conversation;
    }
    for ($index = 0; $index < $count; $index++) {
        $viewer->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => PostLiked::class,
            'data' => (new PostLiked($author, $post))->toArray($viewer),
        ]);
        Message::query()->create([
            'conversation_id' => $conversation->id, 'sender_id' => $author->id, 'body' => 'Synthetic unread message',
        ]);
    }
    $accounts[$count] = [
        'email' => $viewer->email, 'profile' => route('user.profile', $viewer->username, false),
        'settings' => route('user.edit', $viewer->username, false),
        'account' => route('profile.edit', $viewer->username, false),
    ];
}
$commenter = User::factory()->create([
    'name' => 'QA Commenter', 'username' => 'qa.theme.commenter', 'email' => 'commenter@example.test',
]);
foreach ([0, 12] as $postIndex) {
    Comment::query()->create([
        'post_id' => $posts[$postIndex]->id,
        'user_id' => $commenter->id,
        'content' => 'Synthetic comment preview for Appreciation layout QA.',
    ]);
}
$statusViewer = User::query()->where('username', 'qa.theme.2')->firstOrFail();
$appreciationResult = app(GivePostAppreciation::class)->handle($statusViewer, $posts[12], AppreciationType::Solutif);
if ($appreciationResult->status !== PostAppreciationStatus::Appreciated) {
    throw new RuntimeException('Could not seed the isolated Appreciation status fixture.');
}
echo json_encode([
    'accounts' => $accounts, 'otherProfile' => route('user.profile', $author->username, false),
    'emptyVerifiedProfile' => route('user.profile', $emptyVerified->username, false),
    'post' => route('posts.show', $posts[22], false), // Not seeded as bookmarked: browser exercises save -> unsave.
    'savedPost' => route('posts.show', $savedPost, false),
    'appreciationStatusPost' => $posts[12]->uid,
    'appreciationChoicePosts' => [$posts[0]->uid, $posts[12]->uid, $posts[23]->uid],
    'savedPage' => route('bookmarks.index', [], false),
    'searchResults' => route('search', ['q' => 'theme'], false),
    'routes' => array_map(fn ($name) => route($name, [], false), [
        'feed', 'search', 'bookmarks.index', 'activity.index', 'messages.index', 'points.history', 'dashboard',
    ]),
    // Browser screenshot acceptance matrix: each route uses a real auth/session/fixture.
    'flowRoutes' => [
        ['key' => 'inbox', 'url' => route('messages.index', [], false), 'scope' => 'messages'],
        ['key' => 'conversation', 'url' => route('messages.show', $flowConversation, false), 'scope' => 'messages'],
        ['key' => 'activity', 'url' => route('activity.index', [], false), 'scope' => 'activity'],
        ['key' => 'announcements', 'url' => route('announcements.index', [], false), 'scope' => 'activity'],
        ['key' => 'dashboard', 'url' => route('dashboard', [], false), 'scope' => 'feed'],
        ['key' => 'account-settings', 'url' => $accounts[2]['account'], 'scope' => 'profile'],
        ['key' => 'points', 'url' => route('points.history', [], false), 'scope' => 'feed'],
        ['key' => 'post-detail', 'url' => route('posts.show', $flowEditPost, false), 'scope' => 'feed'],
        ['key' => 'edit-thread', 'url' => route('posts.edit', $flowEditPost, false), 'scope' => 'feed'],
        ['key' => 'topic', 'url' => route('topik.show', 'flowqa', false), 'scope' => 'feed'],
        ['key' => 'hashtag', 'url' => route('tags.show', 'flowqa', false), 'scope' => 'feed'],
    ],
], JSON_THROW_ON_ERROR);
