<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_admin_login(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_login_form_renders_without_registration_or_password_reset(): void
    {
        $response = $this->get('/admin/login');

        $response
            ->assertOk()
            ->assertSee('SkuySocial Admin')
            ->assertDontSee('register', false)
            ->assertDontSee('password-reset', false);

        $this->assertSame(Login::class, Filament::getPanel('admin')->getLoginRouteAction());
        $this->assertNull(Route::getRoutes()->getByName('filament.admin.auth.register'));
        $this->assertNull(Route::getRoutes()->getByName('filament.admin.auth.password-reset.request'));
        $this->get('/admin/register')->assertNotFound();
    }

    public function test_admin_panel_configuration_is_explicit_and_strict(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame('admin', $panel->getId());
        $this->assertSame('admin', $panel->getPath());
        $this->assertSame('SkuySocial Admin', $panel->getBrandName());
        $this->assertSame('web', $panel->getAuthGuard());
        $this->assertTrue($panel->isAuthorizationStrict());
    }

    public function test_admin_login_uses_the_existing_turnstile_verifier_when_enabled(): void
    {
        Config::set([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'admin-test-site-key',
            'services.turnstile.secret_key' => 'admin-test-secret-key',
            'services.turnstile.hostname' => 'skuysocial.com',
            'services.turnstile.verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        ]);
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => false,
            ]),
        ]);
        Filament::setCurrentPanel('admin');
        $administrator = User::factory()->administrator()->create();

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('admin-test-site-key')
            ->assertSee('challenges.cloudflare.com/turnstile/v0/api.js', false);

        Livewire::test(Login::class)
            ->set('data.email', $administrator->email)
            ->set('data.password', 'password')
            ->set('data.turnstile_token', 'invalid-admin-token')
            ->call('authenticate')
            ->assertHasErrors(['data.turnstile_token']);

        $this->assertGuest();
        Http::assertSentCount(1);
    }

    public function test_admin_login_enters_the_shared_two_factor_challenge_without_authenticating_first(): void
    {
        Config::set('services.turnstile.enabled', false);
        Filament::setCurrentPanel('admin');
        $administrator = User::factory()->administrator()->create();
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $twoFactor->beginSetup($administrator);
        $secret = $administrator->twoFactorAuthentication()->firstOrFail()->pending_secret;
        $code = app(Google2FA::class)->oathTotp($secret, intdiv(now()->timestamp, 30));
        $this->assertTrue(is_array($twoFactor->confirmSetup($administrator, $code)));
        $this->get('/admin/login')->assertOk();

        Livewire::test(Login::class)
            ->set('data.email', $administrator->email)
            ->set('data.password', 'password')
            ->set('data.remember', true)
            ->call('authenticate')
            ->assertRedirect('/two-factor/challenge');

        $this->assertGuest();
        $this->assertDatabaseHas('user_two_factor_login_challenges', [
            'user_id' => $administrator->id,
            'source' => 'admin',
            'intended_url' => '/admin',
            'remember' => true,
        ]);
    }

    public function test_verified_administrator_can_sign_in_through_the_admin_login(): void
    {
        Config::set([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'admin-test-site-key',
            'services.turnstile.secret_key' => 'admin-test-secret-key',
            'services.turnstile.hostname' => 'skuysocial.com',
            'services.turnstile.verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        ]);
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'skuysocial.com',
                'action' => 'login',
            ]),
        ]);
        Filament::setCurrentPanel('admin');
        $administrator = User::factory()->administrator()->create();

        Livewire::test(Login::class)
            ->set('data.email', $administrator->email)
            ->set('data.password', 'password')
            ->set('data.turnstile_token', 'valid-admin-token')
            ->call('authenticate')
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($administrator);
        Http::assertSentCount(1);
    }

    public function test_ordinary_authenticated_user_is_denied_admin_access(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_unverified_administrator_is_denied_admin_access(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->unverified()
            ->create();

        $this->actingAs($administrator)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_verified_administrator_can_access_the_admin_dashboard(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get('/admin')
            ->assertOk()
            ->assertSee('SkuySocial Admin');
    }

    public function test_panel_access_uses_the_filament_contract_and_defaults_to_deny(): void
    {
        $panel = Filament::getPanel('admin');
        $administrator = User::factory()->administrator()->create();
        $ordinaryUser = User::factory()->create();
        $unknownRole = User::factory()->create();
        $unknownRole->forceFill(['role' => 'unknown']);

        $this->assertInstanceOf(FilamentUser::class, $administrator);
        $this->assertTrue($administrator->canAccessPanel($panel));
        $this->assertFalse($ordinaryUser->canAccessPanel($panel));
        $this->assertFalse($unknownRole->canAccessPanel($panel));
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $user = new User;
        $user->fill([
            'name' => 'Mass Assignment Test',
            'role' => UserRole::Administrator->value,
        ]);

        $this->assertNull($user->role);
    }

    public function test_registration_cannot_create_an_administrator(): void
    {
        Notification::fake();
        Config::set('mail.default', 'array');

        $this->post('/register', [
            'name' => 'Registered User',
            'username' => 'registered_user',
            'email' => 'registered@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Administrator->value,
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'email' => 'registered@example.com',
            'role' => UserRole::User->value,
        ]);
    }

    public function test_profile_update_cannot_promote_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => UserRole::Administrator->value,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit', $user->username));

        $this->assertSame(UserRole::User->value, $user->refresh()->role);
    }

    public function test_custom_settings_update_cannot_promote_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('user.update'), [
                'name' => $user->name,
                'bio' => $user->bio,
                'role' => UserRole::Administrator->value,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.profile', $user));

        $this->assertSame(UserRole::User->value, $user->refresh()->role);
    }

    public function test_public_routes_continue_to_render(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }
}
