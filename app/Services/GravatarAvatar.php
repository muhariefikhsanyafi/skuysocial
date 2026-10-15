<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GravatarAvatar
{
    public const STYLES = ['wavatar', 'robohash', 'retro', 'monsterid', 'identicon'];

    public const MAX_BYTES = 262144;

    public function hash(User $user): string
    {
        // The provider receives a site-scoped pseudonym, never an email hash.
        return hash_hmac('sha256', 'skuysocial:avatar:v1:'.$user->getKey(), (string) config('app.key'));
    }

    public function previewUrl(User $user, string $style): string
    {
        return route('avatar.preview', ['style' => $style, 'v' => $this->hash($user)]);
    }

    public function image(User $user, string $style): string
    {
        if (! in_array($style, self::STYLES, true)) {
            throw new RuntimeException('Unsupported avatar style.');
        }

        $hash = $this->hash($user);
        $key = 'avatar-studio:v1:'.$hash.':'.$style;
        $cache = Cache::store('file');
        $cached = $cache->get($key);
        if (is_string($cached)) {
            return $cached;
        }
        if ($cached === false) {
            throw new RuntimeException('Avatar temporarily unavailable.');
        }

        $lock = $cache->lock($key.':lock', 15);
        if (! $lock->get()) {
            throw new RuntimeException('Avatar generation in progress.');
        }

        try {
            $cached = $cache->get($key);
            if (is_string($cached)) {
                return $cached;
            }
            if ($cached === false) {
                throw new RuntimeException('Avatar temporarily unavailable.');
            }
            $response = Http::accept('image/png')
                ->connectTimeout(2)
                ->timeout(8)
                ->withoutRedirecting()
                ->withOptions([
                    'on_headers' => static function ($response): void {
                        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                            throw new RuntimeException('Avatar response too large.');
                        }
                    },
                    'progress' => static function ($total, $downloaded): void {
                        if ($total > self::MAX_BYTES || $downloaded > self::MAX_BYTES) {
                            throw new RuntimeException('Avatar response too large.');
                        }
                    },
                ])
                ->get('https://www.gravatar.com/avatar/'.$hash, [
                    'd' => $style, 'f' => 'y', 'r' => 'g', 's' => 256,
                ]);

            $body = $response->body();
            $info = @getimagesizefromstring($body);
            if ($response->status() !== 200
                || strtolower(trim(explode(';', $response->header('Content-Type'))[0])) !== 'image/png'
                || strlen($body) > self::MAX_BYTES
                || $info === false
                || ($info['mime'] ?? '') !== 'image/png'
                || $info[0] !== 256 || $info[1] !== 256) {
                throw new RuntimeException('Invalid avatar response.');
            }

            $cache->put($key, $body, now()->addDays(7));

            return $body;
        } catch (Throwable $exception) {
            $cache->put($key, false, now()->addMinutes(5));
            throw new RuntimeException('Avatar temporarily unavailable.', previous: $exception);
        } finally {
            $lock->release();
        }
    }
}
