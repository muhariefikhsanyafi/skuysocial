<?php

namespace App\Services;

use App\Enums\OneSignalPushOutcome;
use App\Models\User;
use App\Support\OneSignalExternalId;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class OneSignalPushService
{
    private const API_URL = 'https://api.onesignal.com/notifications';

    private const MAX_TARGETS = 20000;

    public function isConfigured(): bool
    {
        $configuration = config('services.onesignal');

        return is_array($configuration)
            && ($configuration['enabled'] ?? false) === true
            && is_string($configuration['app_id'] ?? null)
            && trim($configuration['app_id']) !== ''
            && is_string($configuration['rest_api_key'] ?? null)
            && trim($configuration['rest_api_key']) !== '';
    }

    /**
     * @param  iterable<User>  $users
     * @param  array<string, string>  $headings
     * @param  array<string, string>  $contents
     */
    public function sendToUsers(iterable $users, array $headings, array $contents, string $url): OneSignalPushOutcome
    {
        if (! $this->isConfigured()) {
            return OneSignalPushOutcome::ConfigurationMissing;
        }

        $externalIds = [];

        try {
            foreach ($users as $user) {
                if (! $user instanceof User) {
                    return OneSignalPushOutcome::Failed;
                }

                $externalIds[] = OneSignalExternalId::forUser($user);

                if (count($externalIds) > self::MAX_TARGETS) {
                    return OneSignalPushOutcome::Failed;
                }
            }
        } catch (InvalidArgumentException) {
            return OneSignalPushOutcome::Failed;
        }

        $externalIds = array_values(array_unique($externalIds));

        if ($externalIds === [] || ! $this->hasLocalizedText($headings) || ! $this->hasLocalizedText($contents)) {
            return OneSignalPushOutcome::Failed;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return OneSignalPushOutcome::Failed;
        }

        $configuration = config('services.onesignal');

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->withHeaders([
                    'Authorization' => 'Key '.$configuration['rest_api_key'],
                ])
                ->post(self::API_URL, [
                    'app_id' => $configuration['app_id'],
                    'include_aliases' => [
                        'external_id' => $externalIds,
                    ],
                    'target_channel' => 'push',
                    'headings' => $headings,
                    'contents' => $contents,
                    'url' => $url,
                ]);
        } catch (ConnectionException) {
            return OneSignalPushOutcome::Failed;
        }

        if (! $response->successful()) {
            return OneSignalPushOutcome::Failed;
        }

        $notificationId = $response->json('id');

        return is_string($notificationId) && $notificationId !== ''
            ? OneSignalPushOutcome::Sent
            : OneSignalPushOutcome::NoSubscribers;
    }

    /**
     * @param  array<string, string>  $messages
     */
    private function hasLocalizedText(array $messages): bool
    {
        if ($messages === []) {
            return false;
        }

        foreach ($messages as $locale => $message) {
            if (! is_string($locale) || trim($locale) === '' || ! is_string($message) || trim($message) === '') {
                return false;
            }
        }

        return true;
    }
}
