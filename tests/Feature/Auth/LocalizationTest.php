<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.turnstile.enabled', false);
    }

    public function test_login_and_registration_copy_is_formal_indonesian(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('ALAMAT EMAIL')
            ->assertSee('KATA SANDI')
            ->assertSee('Lupa kata sandi?')
            ->assertSee('Belum memiliki akun?')
            ->assertSee('MASUK')
            ->assertSee('DAFTAR');

        $this->get('/register')
            ->assertOk()
            ->assertSee('NAMA')
            ->assertSee('NAMA PENGGUNA')
            ->assertSee('ALAMAT EMAIL')
            ->assertSee('KATA SANDI')
            ->assertSee('KONFIRMASI KATA SANDI')
            ->assertSee('Sudah memiliki akun?')
            ->assertSee('DAFTAR');
    }

    public function test_forgot_and_reset_password_copy_is_formal_indonesian(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Masukkan alamat email Anda. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi.')
            ->assertSee('ALAMAT EMAIL')
            ->assertSee('Kembali ke Halaman Masuk')
            ->assertSee('KIRIM TAUTAN ATUR ULANG');

        $this->get('/reset-password/test-token')
            ->assertOk()
            ->assertSee('ALAMAT EMAIL')
            ->assertSee('KATA SANDI BARU')
            ->assertSee('KONFIRMASI KATA SANDI')
            ->assertSee('ATUR ULANG KATA SANDI');
    }

    public function test_password_reset_notification_uses_skuy_social_copy_and_existing_reset_url(): void
    {
        Notification::fake();
        Config::set('auth.passwords.users.expire', 45);
        Config::set('services.turnstile.secret_key', 'test-turnstile-secret');

        $user = User::factory()->create();

        $this->from('/forgot-password')->post('/forgot-password', [
            'email' => $user->email,
        ])->assertRedirect('/forgot-password');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);
            $expectedUrl = url(route('password.reset', [
                'token' => $notification->token,
                'email' => $user->email,
            ], false));
            $html = view($mail->view['html'], $mail->viewData)->render();
            $text = view($mail->view['text'], $mail->viewData)->render();

            $this->assertSame('Atur Ulang Kata Sandi SkuySocial', $mail->subject);
            $this->assertSame($expectedUrl, $mail->viewData['url']);
            $this->assertSame(45, $mail->viewData['expiration']);
            $this->assertStringContainsString('Atur Ulang Kata Sandi', $html);
            $this->assertStringContainsString('Kami menerima permintaan untuk mengatur ulang kata sandi akun SkuySocial Anda.', $html);
            $this->assertStringContainsString('ATUR ULANG KATA SANDI', $html);
            $this->assertStringContainsString('Tautan ini berlaku selama 45 menit.', $html);
            $this->assertStringContainsString('Jika Anda tidak meminta pengaturan ulang kata sandi, abaikan email ini.', $html);
            $this->assertStringContainsString('Atur Ulang Kata Sandi', $text);
            $this->assertStringContainsString('Tautan ini berlaku selama 45 menit.', $text);
            $this->assertStringContainsString('skuysocial.com', $html);
            $this->assertStringNotContainsString('Your password has been reset.', $html);
            $this->assertStringNotContainsString('test-turnstile-secret', $html);
            $this->assertStringNotContainsString('test-turnstile-secret', $text);

            return true;
        });
    }

    public function test_password_reset_success_status_is_formal_indonesian(): void
    {
        $user = User::factory()->create();
        $token = app('auth.password.broker')->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Kata sandi Anda berhasil diatur ulang.');
    }
}
