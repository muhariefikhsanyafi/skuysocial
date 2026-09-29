<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\OneSignalExternalId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneSignalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_settings_degrades_to_unavailable_without_client_configuration(): void
    {
        config(['services.onesignal' => [
            'enabled' => true,
            'app_id' => '',
            'rest_api_key' => '',
        ]]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('profile.edit', ['username' => $user->username]));

        $response->assertOk()
            ->assertSee(__('settings.push.heading'))
            ->assertSee(__('settings.push.state.unavailable'))
            ->assertDontSee('data-onesignal-bootstrap', false)
            ->assertDontSee('data-app-id=', false)
            ->assertSee('data-onesignal-logout', false);
    }

    public function test_account_settings_exposes_only_the_public_app_id_and_stable_external_id(): void
    {
        $user = User::factory()->create(['username' => 'private_handle', 'email' => 'private@example.test']);
        $appKey = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';
        $rotatedAppKey = 'synthetic-app-key-after-rotation';
        $serverKey = 'synthetic-server-only-key-must-never-render';
        config([
            'app.key' => $appKey,
            'services.onesignal' => [
                'enabled' => true,
                'app_id' => 'public-app-id-for-tests',
                'rest_api_key' => $serverKey,
            ],
        ]);
        $externalId = OneSignalExternalId::forUser($user);

        $response = $this->actingAs($user)->get(route('profile.edit', ['username' => $user->username]));

        $response->assertOk()
            ->assertSee('data-onesignal-bootstrap', false)
            ->assertSee('data-app-id="public-app-id-for-tests"', false)
            ->assertSee('data-external-id="'.OneSignalExternalId::forUser($user).'"', false)
            ->assertSee(__('settings.push.enable'))
            ->assertSee(__('settings.push.disable'))
            ->assertDontSee($serverKey, false)
            ->assertDontSee($appKey, false)
            ->assertDontSee($rotatedAppKey, false)
            ->assertDontSee('data-external-id="private@example.test"', false);

        config(['app.key' => $rotatedAppKey]);
        $afterKeyRotation = $this->actingAs($user)->get(route('profile.edit', ['username' => $user->username]));

        $afterKeyRotation->assertOk()
            ->assertSee('data-external-id="'.$externalId.'"', false);
    }

    public function test_disabled_integration_does_not_expose_the_app_id_or_load_identity_bootstrap(): void
    {
        config(['services.onesignal' => [
            'enabled' => false,
            'app_id' => 'public-app-id-for-tests',
            'rest_api_key' => 'synthetic-server-only-key',
        ]]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('profile.edit', ['username' => $user->username]));

        $response->assertOk()
            ->assertSee(__('settings.push.state.unavailable'))
            ->assertDontSee('public-app-id-for-tests', false)
            ->assertDontSee('synthetic-server-only-key', false)
            ->assertDontSee('data-onesignal-bootstrap', false);
    }

    public function test_push_settings_copy_is_localized_for_english_and_indonesian_users(): void
    {
        foreach (['en', 'id'] as $locale) {
            $user = User::factory()->create(['locale' => $locale]);
            $response = $this->actingAs($user)
                ->get(route('profile.edit', ['username' => $user->username]));

            $response->assertOk()
                ->assertSee(__('settings.push.enable', [], $locale))
                ->assertSee(__('settings.push.state.unavailable', [], $locale));
        }
    }

    public function test_laravel_logout_completes_without_relying_on_the_client_sdk(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
