<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\OneSignalExternalId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OneSignalTestPushCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_push_rejects_missing_server_configuration(): void
    {
        config(['services.onesignal' => [
            'enabled' => true,
            'app_id' => '',
            'rest_api_key' => '',
        ]]);
        Http::fake();

        $this->artisan('push:onesignal-test', ['user' => '1'])
            ->expectsOutput(__('push.command.configuration_missing'))
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_test_push_rejects_invalid_or_nonexistent_internal_user_ids(): void
    {
        $this->configureOneSignal();
        Http::fake();

        $this->artisan('push:onesignal-test', ['user' => 'not-an-id'])
            ->expectsOutput(__('push.command.user_not_found'))
            ->assertExitCode(1);
        $this->artisan('push:onesignal-test', ['user' => '999999'])
            ->expectsOutput(__('push.command.user_not_found'))
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_test_push_targets_only_the_resolved_users_stable_external_id(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $other = User::factory()->create(['locale' => 'id']);
        $this->configureOneSignal();
        Http::fake([
            'https://api.onesignal.com/notifications' => Http::response(['id' => 'synthetic-command-notification'], 200),
        ]);

        $this->artisan('push:onesignal-test', ['user' => (string) $user->getKey()])
            ->expectsOutput(__('push.command.sent'))
            ->assertExitCode(0);

        Http::assertSent(static function (ClientRequest $request) use ($user, $other): bool {
            $payload = $request->data();

            return ($payload['include_aliases']['external_id'] ?? null) === [
                    OneSignalExternalId::forUser($user),
                ]
                && ! in_array(OneSignalExternalId::forUser($other), $payload['include_aliases']['external_id'] ?? [], true)
                && ($payload['url'] ?? null) === route('activity.index');
        });
    }

    public function test_test_push_reports_no_subscribers_when_the_response_has_no_notification_id(): void
    {
        $user = User::factory()->create();
        $this->configureOneSignal();
        Http::fake([
            'https://api.onesignal.com/notifications' => Http::response(['id' => null], 200),
        ]);

        $this->artisan('push:onesignal-test', ['user' => (string) $user->getKey()])
            ->expectsOutput(__('push.command.no_subscribers'))
            ->assertExitCode(1);
    }

    public function test_test_push_reports_api_failure_without_leaking_server_secrets_or_response_text(): void
    {
        $user = User::factory()->create();
        $secret = 'synthetic-rest-key-never-output';
        config(['services.onesignal' => [
            'enabled' => true,
            'app_id' => '11111111-1111-4111-8111-111111111111',
            'rest_api_key' => $secret,
        ]]);
        Http::fake([
            'https://api.onesignal.com/notifications' => Http::response(['error' => $secret], 500),
        ]);

        $this->artisan('push:onesignal-test', ['user' => (string) $user->getKey()])
            ->expectsOutput(__('push.command.failed'))
            ->doesntExpectOutput($secret)
            ->assertExitCode(1);
    }

    public function test_there_is_no_public_http_route_for_test_push(): void
    {
        $matchingRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => str_contains($route->uri(), 'onesignal') || str_contains($route->getName() ?? '', 'onesignal'));

        $this->assertCount(0, $matchingRoutes);
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
