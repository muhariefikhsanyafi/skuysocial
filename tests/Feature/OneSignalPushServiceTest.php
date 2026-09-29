<?php

namespace Tests\Feature;

use App\Enums\OneSignalPushOutcome;
use App\Models\User;
use App\Services\OneSignalPushService;
use App\Support\OneSignalExternalId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OneSignalPushServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_configuration_returns_a_safe_unconfigured_result_without_a_request(): void
    {
        $user = User::factory()->create();
        config(['services.onesignal' => [
            'enabled' => true,
            'app_id' => '',
            'rest_api_key' => '',
        ]]);
        Http::fake();

        $result = app(OneSignalPushService::class)->sendToUsers(
            [$user],
            ['en' => 'Test'],
            ['en' => 'Safe body'],
            'https://skuysocial.test/aktivitas',
        );

        $this->assertSame(OneSignalPushOutcome::ConfigurationMissing, $result);
        Http::assertNothingSent();
    }

    public function test_missing_application_key_does_not_prevent_server_targeting(): void
    {
        $user = User::factory()->create();
        $this->configureOneSignal();
        config(['app.key' => '']);
        Http::fake([
            'https://api.onesignal.com/notifications' => Http::response(['id' => 'synthetic-notification-id'], 200),
        ]);

        $result = app(OneSignalPushService::class)->sendToUsers(
            [$user], ['en' => 'Test'], ['en' => 'Safe body'], 'https://skuysocial.test/aktivitas',
        );

        $this->assertSame(OneSignalPushOutcome::Sent, $result);
        Http::assertSent(static fn (ClientRequest $request): bool =>
            ($request->data()['include_aliases']['external_id'] ?? null) === [
                OneSignalExternalId::forUser($user),
            ]
        );
    }

    public function test_send_targets_only_external_aliases_on_the_push_channel(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $testKey = 'synthetic-rest-key-for-request-matching';
        config(['services.onesignal' => [
            'enabled' => true,
            'app_id' => '11111111-1111-4111-8111-111111111111',
            'rest_api_key' => $testKey,
        ]]);
        Http::fake([
            'https://api.onesignal.com/notifications' => Http::response(['id' => 'synthetic-notification-id'], 200),
        ]);

        $result = app(OneSignalPushService::class)->sendToUsers(
            [$first, $second],
            ['en' => 'SkuySocial notifications', 'id' => 'Notifikasi SkuySocial'],
            ['en' => 'Test message', 'id' => 'Pesan uji'],
            'https://skuysocial.test/aktivitas',
        );

        $this->assertSame(OneSignalPushOutcome::Sent, $result);
        Http::assertSent(static function (ClientRequest $request) use ($first, $second, $testKey): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.onesignal.com/notifications'
                && $request->hasHeader('Authorization', 'Key '.$testKey)
                && ($payload['app_id'] ?? null) === '11111111-1111-4111-8111-111111111111'
                && ($payload['target_channel'] ?? null) === 'push'
                && ($payload['include_aliases']['external_id'] ?? null) === [
                    OneSignalExternalId::forUser($first),
                    OneSignalExternalId::forUser($second),
                ]
                && ! array_key_exists('included_segments', $payload)
                && ($payload['url'] ?? null) === 'https://skuysocial.test/aktivitas';
        });
    }

    public function test_success_without_a_notification_id_is_reported_as_no_subscribers(): void
    {
        $user = User::factory()->create();
        $this->configureOneSignal();
        Http::fake([
            'https://api.onesignal.com/notifications' => Http::response(['id' => ''], 200),
        ]);

        $result = app(OneSignalPushService::class)->sendToUsers(
            [$user], ['en' => 'Test'], ['en' => 'Body'], 'https://skuysocial.test/aktivitas',
        );

        $this->assertSame(OneSignalPushOutcome::NoSubscribers, $result);
    }

    public function test_non_success_responses_fail_without_exposing_the_response_body(): void
    {
        $user = User::factory()->create();
        $this->configureOneSignal();
        Http::fake([
            'https://api.onesignal.com/notifications' => Http::response(['error' => 'synthetic-private-response'], 500),
        ]);

        $result = app(OneSignalPushService::class)->sendToUsers(
            [$user], ['en' => 'Test'], ['en' => 'Body'], 'https://skuysocial.test/aktivitas',
        );

        $this->assertSame(OneSignalPushOutcome::Failed, $result);
        $this->assertStringNotContainsString('synthetic-private-response', $result->value);
    }

    public function test_connection_timeout_or_transport_failure_returns_a_safe_failure(): void
    {
        $user = User::factory()->create();
        $this->configureOneSignal();
        Http::fake(static function (): never {
            throw new ConnectionException('synthetic transport failure');
        });

        $result = app(OneSignalPushService::class)->sendToUsers(
            [$user], ['en' => 'Test'], ['en' => 'Body'], 'https://skuysocial.test/aktivitas',
        );

        $this->assertSame(OneSignalPushOutcome::Failed, $result);
    }

    private function configureOneSignal(): void
    {
        config(['services.onesignal' => [
            'enabled' => true,
            'app_id' => '11111111-1111-4111-8111-111111111111',
            'rest_api_key' => 'synthetic-rest-key-not-a-real-credential',
        ]]);
    }
}
