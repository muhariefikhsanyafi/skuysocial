<?php

use App\Models\Bookmark;
use App\Models\Conversation;
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

$migrationExit = Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]);
if ($migrationExit !== 0) {
    throw new RuntimeException(Artisan::output());
}
$author = User::factory()->create([
    'name' => 'QA Theme Author', 'username' => 'qa.theme.author', 'email' => 'author@example.test',
]);
$post = null;
$savedPost = null;
for ($index = 0; $index < 24; $index++) {
    $post = Post::query()->create([
        'user_id' => $author->id, 'content' => 'Synthetic theme and navigation regression post '.($index + 1),
    ]);

    if ($index === 0) {
        $savedPost = $post;
    }
}
$accounts = [];
foreach ([0, 1, 2, 12] as $count) {
    $viewer = User::factory()->create([
        'name' => 'QA Theme '.$count, 'username' => 'qa.theme.'.$count, 'email' => 'theme'.$count.'@example.test',
    ]);
    $viewer->forceFill(['locale' => $count === 12 ? 'en' : 'id'])->save();
    if ($count === 2) {
        Bookmark::query()->create(['user_id' => $viewer->id, 'post_id' => $savedPost->id]);
    }
    Post::query()->create(['user_id' => $viewer->id, 'content' => 'Synthetic own profile post']);
    Post::query()->create(['user_id' => $viewer->id, 'content' => '', 'repost_id' => $post->id]);
    $conversation = Conversation::query()->create([
        'type' => Conversation::TYPE_DIRECT, 'direct_key' => $author->id.':'.$viewer->id,
    ]);
    $conversation->users()->attach([$author->id, $viewer->id]);
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
echo json_encode([
    'accounts' => $accounts, 'otherProfile' => route('user.profile', $author->username, false),
    'post' => route('posts.show', $post, false),
    'savedPost' => route('posts.show', $savedPost, false),
    'savedPage' => route('bookmarks.index', [], false),
    'searchResults' => route('search', ['q' => 'theme'], false),
    'routes' => array_map(fn ($name) => route($name, [], false), [
        'feed', 'search', 'bookmarks.index', 'activity.index', 'messages.index', 'points.history', 'dashboard',
    ]),
], JSON_THROW_ON_ERROR);
