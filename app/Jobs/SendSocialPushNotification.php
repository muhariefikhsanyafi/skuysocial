<?php

namespace App\Jobs;

use App\Enums\ModerationState;
use App\Enums\OneSignalPushOutcome;
use App\Models\Conversation;
use App\Models\Post;
use App\Models\User;
use App\Services\LocalePreference;
use App\Services\OneSignalPushService;
use App\Support\OneSignalExternalId;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SendSocialPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const LIKE = 'like';
    public const COMMENT = 'comment';
    public const REPLY = 'reply';
    public const FOLLOW = 'follow';
    public const DIRECT_MESSAGE = 'direct_message';

    public int $tries = 1;
    public int $timeout = 8;
    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $recipientId,
        public readonly string $event,
        public readonly int $actorId,
        public readonly string $actorName,
        public readonly ?string $postUid = null,
        public readonly ?int $conversationId = null,
    ) {
    }

    public static function dispatchAfterCommit(
        int $recipientId,
        string $event,
        int $actorId,
        string $actorName,
        ?string $postUid = null,
        ?int $conversationId = null,
    ): void {
        DB::afterCommit(static function () use (
            $recipientId,
            $event,
            $actorId,
            $actorName,
            $postUid,
            $conversationId,
        ): void {
            self::dispatch(
                $recipientId,
                $event,
                $actorId,
                $actorName,
                $postUid,
                $conversationId,
            )->onConnection('deferred')->afterCommit();
        });
    }

    public function handle(
        OneSignalPushService $pushes,
        ?LocalePreference $locales = null,
    ): void {
        $locales ??= app(LocalePreference::class);
        try {
            $recipient = User::query()->find($this->recipientId);
            if ($recipient === null
                || (int) $recipient->getKey() === $this->actorId
                || ! in_array($recipient->moderationState(), [ModerationState::Active, ModerationState::Restricted], true)) {
                return;
            }

            OneSignalExternalId::forUser($recipient);
            $actor = User::query()->find($this->actorId);
            $locale = $locales->supported($recipient->locale)
                ?? $locales->configuredLocale();
            $actorName = $this->safeActorName($actor?->name ?? $this->actorName, $actor?->email, $locale);
            $copyKey = match ($this->event) {
                self::LIKE => 'like',
                self::COMMENT => 'comment',
                self::REPLY => 'reply',
                self::FOLLOW => 'follow',
                self::DIRECT_MESSAGE => 'direct_message',
                default => null,
            };

            if ($copyKey === null) {
                return;
            }

            $url = $this->destination($recipient, $actor);

            if ($url === null) {
                return;
            }

            $contents = [
                $locale => (string) trans(
                    'push.social.'.$copyKey,
                    ['actor' => $actorName],
                    $locale,
                ),
            ];

            $outcome = $pushes->sendToUsers(
                [$recipient],
                [$locale => (string) trans('push.social.heading', [], $locale)],
                $contents,
                $url,
            );

            if ($outcome === OneSignalPushOutcome::Failed) {
                Log::warning('Social push delivery failed.', [
                    'event' => $this->event,
                    'recipient_id' => $this->recipientId,
                    'outcome' => $outcome->value,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Social push delivery failed safely.', [
                'event' => $this->event,
                'recipient_id' => $this->recipientId,
                'exception' => $exception::class,
            ]);
        }
    }

    private function destination(User $recipient, ?User $actor): ?string
    {
        return match ($this->event) {
            self::LIKE, self::COMMENT, self::REPLY => $this->postDestination($recipient),
            self::FOLLOW => $actor === null
                ? null
                : $this->safeDestination(route('user.profile', ['username' => $actor->username], false)),
            self::DIRECT_MESSAGE => $this->conversationDestination($recipient),
            default => null,
        };
    }

    private function postDestination(User $recipient): ?string
    {
        if (! is_string($this->postUid) || $this->postUid === '') {
            return null;
        }

        $post = Post::query()->where('uid', $this->postUid)->first();

        if ($post === null || (int) $post->user_id !== (int) $recipient->getKey()) {
            return null;
        }

        return $this->safeDestination(route('posts.show', $post, false));
    }

    private function conversationDestination(User $recipient): ?string
    {
        if ($this->conversationId === null) {
            return null;
        }

        $conversation = Conversation::query()->direct()->find($this->conversationId);

        if ($conversation === null
            || ! $conversation->participants()->where('user_id', $recipient->getKey())->exists()
            || ! $conversation->participants()->where('user_id', $this->actorId)->exists()) {
            return null;
        }

        return $this->safeDestination(route('messages.show', $conversation, false));
    }

    private function safeDestination(string $destination): ?string
    {
        $base = (string) config('app.url');
        $scheme = parse_url($base, PHP_URL_SCHEME);
        $host = parse_url($base, PHP_URL_HOST);
        $path = parse_url($destination, PHP_URL_PATH);

        if (! in_array($scheme, ['http', 'https'], true)
            || ! is_string($host)
            || $host === ''
            || preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1
            || parse_url($base, PHP_URL_USER) !== null
            || parse_url($base, PHP_URL_PASS) !== null
            || ! is_string($path)
            || ! str_starts_with($path, '/')) {
            return null;
        }

        $origin = $scheme.'://'.$host;
        $port = parse_url($base, PHP_URL_PORT);

        if (is_int($port)) {
            $origin .= ':'.$port;
        }

        $basePath = parse_url($base, PHP_URL_PATH);
        $prefix = is_string($basePath) ? rtrim($basePath, '/') : '';

        return $origin.$prefix.$path;
    }

    private function safeActorName(string $name, ?string $email, string $locale): string
    {
        $name = Str::squish(strip_tags($name));

        if ($email !== null && $email !== '') {
            $name = str_replace($email, '', $name);
        }

        $name = preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u', '', $name) ?? '';
        $name = preg_replace('/[\p{C}\p{Zl}\p{Zp}]+/u', ' ', $name) ?? '';
        $name = Str::limit(Str::squish($name), 80, '');

        return $name !== '' ? $name : (string) trans('push.social.actor_fallback', [], $locale);
    }

}
