<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
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
            ->assertSee('Masuk ke SkuySocial')
            ->assertSee('Lanjutkan percakapan yang kamu ikuti.')
            ->assertSee('Email atau nama pengguna')
            ->assertSee('Kata sandi')
            ->assertSee('aria-label="Tampilkan kata sandi"', false)
            ->assertSee('data-hide-label="Sembunyikan kata sandi"', false)
            ->assertSee('Lupa kata sandi?')
            ->assertSee('Belum memiliki akun?')
            ->assertSee('Masuk')
            ->assertSee('Buat akun');

        $this->get('/register')
            ->assertOk()
            ->assertSee('Buat akun SkuySocial')
            ->assertSee('Mulai percakapan dan temukan topik yang ingin kamu ikuti.')
            ->assertSee('Nama')
            ->assertSee('Nama pengguna')
            ->assertSee('(tanpa spasi)')
            ->assertSee('Alamat email')
            ->assertSee('Kata sandi')
            ->assertSee('Konfirmasi kata sandi')
            ->assertSee('Sudah punya akun?')
            ->assertSee('Masuk')
            ->assertSee('Buat akun');
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

    public function test_b3_catalogs_have_matching_indonesian_and_english_keys(): void
    {
        foreach (['auth', 'passwords', 'validation', 'pagination'] as $catalog) {
            $indonesian = require lang_path("id/{$catalog}.php");
            $english = require lang_path("en/{$catalog}.php");

            $this->assertSame(
                array_keys(Arr::dot($indonesian)),
                array_keys(Arr::dot($english)),
                "The {$catalog} catalogs must expose the same keys.",
            );
        }

        $indonesianJson = json_decode(
            file_get_contents(lang_path('id.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $englishJson = json_decode(
            file_get_contents(lang_path('en.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame(array_keys($indonesianJson), array_keys($englishJson));
    }

    public function test_all_active_auth_views_render_indonesian_copy(): void
    {
        $this->withSession(['locale' => 'id'])
            ->get('/login')
            ->assertOk()
            ->assertSee('<html lang="id">', false)
            ->assertSeeText('Masuk ke SkuySocial')
            ->assertSeeText('Lanjutkan percakapan yang kamu ikuti.')
            ->assertSeeText('Email atau nama pengguna')
            ->assertSeeText('Kata sandi')
            ->assertSee('aria-label="Tampilkan kata sandi"', false)
            ->assertSee('data-hide-label="Sembunyikan kata sandi"', false)
            ->assertSeeText('Lupa kata sandi?')
            ->assertSeeText('Belum memiliki akun?')
            ->assertSeeText('Masuk')
            ->assertSeeText('Buat akun')
            ->assertDontSeeText('Forgot password?');

        $this->get('/register')
            ->assertOk()
            ->assertSeeText('Buat akun SkuySocial')
            ->assertSeeText('Mulai percakapan dan temukan topik yang ingin kamu ikuti.')
            ->assertSeeText('Nama')
            ->assertSeeText('Nama pengguna')
            ->assertSeeText('(tanpa spasi)')
            ->assertSeeText('Alamat email')
            ->assertSeeText('Kata sandi')
            ->assertSeeText('Konfirmasi kata sandi')
            ->assertSeeText('Sudah punya akun?')
            ->assertSeeText('Masuk')
            ->assertSeeText('Buat akun');

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSeeText('Masukkan alamat email Anda. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi.')
            ->assertSeeText('Kembali ke Halaman Masuk')
            ->assertSeeText('KIRIM TAUTAN ATUR ULANG');

        $this->get('/reset-password/test-token')
            ->assertOk()
            ->assertSeeText('KATA SANDI BARU')
            ->assertSeeText('KONFIRMASI KATA SANDI')
            ->assertSeeText('ATUR ULANG KATA SANDI');

        $user = User::factory()->unverified()->create(['locale' => 'id']);

        $this->actingAs($user)
            ->get('/confirm-password')
            ->assertOk()
            ->assertSeeText('Ini adalah area aman. Konfirmasikan kata sandi Anda sebelum melanjutkan.')
            ->assertSeeText('KATA SANDI')
            ->assertSeeText('KONFIRMASI');

        $this->withSession(['status' => 'verification-link-sent'])
            ->get('/verify-email')
            ->assertOk()
            ->assertSeeText('Sebelum melanjutkan, silakan verifikasi alamat email Anda melalui tautan yang telah kami kirimkan. Jika email tersebut belum Anda terima, kami dapat mengirimkannya kembali.')
            ->assertSeeText('Tautan verifikasi baru telah dikirim ke alamat email Anda.')
            ->assertSeeText('KIRIM ULANG EMAIL VERIFIKASI')
            ->assertSeeText('KELUAR');
    }

    public function test_all_active_auth_views_render_english_copy(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/login')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSeeText('Sign in to SkuySocial')
            ->assertSeeText('Continue the conversations you follow.')
            ->assertSeeText('Email or username')
            ->assertSeeText('Password')
            ->assertSee('aria-label="Show password"', false)
            ->assertSee('data-hide-label="Hide password"', false)
            ->assertSeeText('Forgot password?')
            ->assertSeeText("Don't have an account?")
            ->assertSeeText('Sign in')
            ->assertSeeText('Create account')
            ->assertDontSeeText('Lupa kata sandi?');

        $this->get('/register')
            ->assertOk()
            ->assertSeeText('Create your SkuySocial account')
            ->assertSeeText('Start conversations and find topics you want to follow.')
            ->assertSeeText('Name')
            ->assertSeeText('Username')
            ->assertSeeText('(no spaces)')
            ->assertSeeText('Email address')
            ->assertSeeText('Password')
            ->assertSeeText('Confirm password')
            ->assertSeeText('Already have an account?')
            ->assertSeeText('Sign in')
            ->assertSeeText('Create account');

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSeeText('Enter your email address and we will send you a link to reset your password.')
            ->assertSeeText('Back to Sign In')
            ->assertSeeText('SEND RESET LINK');

        $this->get('/reset-password/test-token')
            ->assertOk()
            ->assertSeeText('NEW PASSWORD')
            ->assertSeeText('CONFIRM PASSWORD')
            ->assertSeeText('RESET PASSWORD');

        $user = User::factory()->unverified()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->get('/confirm-password')
            ->assertOk()
            ->assertSeeText('This is a secure area. Please confirm your password before continuing.')
            ->assertSeeText('PASSWORD')
            ->assertSeeText('CONFIRM');

        $this->withSession(['status' => 'verification-link-sent'])
            ->get('/verify-email')
            ->assertOk()
            ->assertSeeText('Before continuing, please verify your email address using the link we sent you. If you did not receive the email, we can send another.')
            ->assertSeeText('A new verification link has been sent to your email address.')
            ->assertSeeText('RESEND VERIFICATION EMAIL')
            ->assertSeeText('LOG OUT');
    }

    public function test_login_failure_and_throttle_messages_follow_the_guest_session_locale(): void
    {
        $cases = [
            'id' => [
                'failed' => 'Kredensial yang diberikan tidak cocok dengan data kami.',
                'throttle' => 'Terlalu banyak percobaan masuk. Silakan coba lagi dalam ',
                'suffix' => ' detik.',
            ],
            'en' => [
                'failed' => 'The provided credentials do not match our records.',
                'throttle' => 'Too many sign-in attempts. Please try again in ',
                'suffix' => ' seconds.',
            ],
        ];

        foreach ($cases as $locale => $copy) {
            $email = "failed-{$locale}@example.com";
            $firstResponse = $this->withSession(['locale' => $locale])
                ->from('/login')
                ->post('/login', [
                    'email' => $email,
                    'password' => 'wrong-password',
                ]);

            $firstResponse->assertSessionHasErrors(['email' => $copy['failed']]);

            for ($attempt = 2; $attempt <= 5; $attempt++) {
                $this->withSession(['locale' => $locale])->post('/login', [
                    'email' => $email,
                    'password' => 'wrong-password',
                ])->assertRedirect();
            }

            $throttledResponse = $this->withSession(['locale' => $locale])
                ->post('/login', [
                    'email' => $email,
                    'password' => 'wrong-password',
                ]);

            $throttledResponse->assertSessionHasErrors('email');
            $message = session('errors')->first('email');

            $this->assertStringStartsWith($copy['throttle'], $message);
            $this->assertStringEndsWith($copy['suffix'], $message);
        }
    }

    public function test_confirm_password_error_follows_the_authenticated_account_locale(): void
    {
        foreach ([
            'id' => 'Kata sandi yang diberikan tidak benar.',
            'en' => 'The provided password is incorrect.',
        ] as $locale => $expected) {
            $user = User::factory()->create(['locale' => $locale]);
            $conflictingLocale = $locale === 'id' ? 'en' : 'id';

            $this->actingAs($user)
                ->withSession(['locale' => $conflictingLocale])
                ->from('/confirm-password')
                ->post('/confirm-password', ['password' => 'wrong-password'])
                ->assertSessionHasErrors(['password' => $expected]);
        }
    }

    public function test_password_broker_success_is_bilingual_and_enumeration_safe(): void
    {
        Notification::fake();

        foreach ([
            'id' => 'Tautan pengaturan ulang kata sandi telah dikirim ke alamat email Anda.',
            'en' => 'A password reset link has been sent to your email address.',
        ] as $locale => $expected) {
            $user = User::factory()->create([
                'email' => "registered-{$locale}@example.com",
                'locale' => $locale,
            ]);

            $unknownResponse = $this->withSession(['locale' => $locale])
                ->from('/forgot-password')
                ->post('/forgot-password', [
                    'email' => "unknown-{$locale}@example.com",
                ]);
            $knownResponse = $this->withSession(['locale' => $locale])
                ->from('/forgot-password')
                ->post('/forgot-password', ['email' => $user->email]);

            foreach ([$unknownResponse, $knownResponse] as $response) {
                $response
                    ->assertRedirect('/forgot-password')
                    ->assertSessionHasNoErrors()
                    ->assertSessionHas('status', $expected);
            }

            Notification::assertSentTo($user, ResetPassword::class);
        }
    }

    public function test_password_reset_success_and_invalid_token_messages_follow_the_guest_locale(): void
    {
        foreach ([
            'id' => [
                'reset' => 'Kata sandi Anda berhasil diatur ulang.',
                'token' => 'Token pengaturan ulang kata sandi tidak valid.',
            ],
            'en' => [
                'reset' => 'Your password has been reset.',
                'token' => 'This password reset token is invalid.',
            ],
        ] as $locale => $copy) {
            $user = User::factory()->create(['locale' => $locale]);
            $token = app('auth.password.broker')->createToken($user);

            $this->withSession(['locale' => $locale])
                ->post('/reset-password', [
                    'token' => $token,
                    'email' => $user->email,
                    'password' => 'new-password',
                    'password_confirmation' => 'new-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHas('status', $copy['reset']);

            $this->withSession(['locale' => $locale])
                ->from('/reset-password/invalid-token')
                ->post('/reset-password', [
                    'token' => 'invalid-token',
                    'email' => $user->email,
                    'password' => 'another-password',
                    'password_confirmation' => 'another-password',
                ])
                ->assertRedirect('/reset-password/invalid-token')
                ->assertSessionHasErrors(['email' => $copy['token']]);
        }
    }

    public function test_representative_validation_rules_and_attributes_are_bilingual(): void
    {
        User::factory()->create(['username' => 'existing_user']);

        $expected = [
            'id' => [
                'required' => 'Nama wajib diisi.',
                'email' => 'Alamat email harus berupa alamat email yang valid.',
                'min' => 'Kata sandi harus terdiri dari minimal 8 karakter.',
                'confirmed' => 'Kata sandi dan konfirmasinya tidak cocok.',
                'max' => 'Nama pengguna tidak boleh terdiri dari lebih dari 3 karakter.',
                'unique' => 'Nama pengguna sudah digunakan.',
                'file' => 'Foto profil harus berupa berkas.',
                'image' => 'Foto profil harus berupa gambar.',
                'mimes' => 'Foto profil harus berupa berkas dengan jenis: jpeg, png.',
                'array' => 'Gambar harus berupa daftar.',
                'exists' => 'Balasan induk yang dipilih tidak valid.',
            ],
            'en' => [
                'required' => 'The name field is required.',
                'email' => 'The email field must be a valid email address.',
                'min' => 'The password field must be at least 8 characters.',
                'confirmed' => 'The password field confirmation does not match.',
                'max' => 'The username field must not be greater than 3 characters.',
                'unique' => 'The username has already been taken.',
                'file' => 'The profile photo field must be a file.',
                'image' => 'The profile photo field must be an image.',
                'mimes' => 'The profile photo field must be a file of type: jpeg, png.',
                'array' => 'The images field must be an array.',
                'exists' => 'The selected parent reply is invalid.',
            ],
        ];

        foreach ($expected as $locale => $copy) {
            $this->post('/locale', ['locale' => $locale])->assertRedirect('/');

            $this->assertValidationMessage([], ['name' => ['required']], 'name', $copy['required']);
            $this->assertValidationMessage(['email' => 'invalid'], ['email' => ['email']], 'email', $copy['email']);
            $this->assertValidationMessage(['password' => 'short'], ['password' => ['min:8']], 'password', $copy['min']);
            $this->assertValidationMessage(
                ['password' => 'password', 'password_confirmation' => 'different'],
                ['password' => ['confirmed']],
                'password',
                $copy['confirmed'],
            );
            $this->assertValidationMessage(['username' => 'long'], ['username' => ['max:3']], 'username', $copy['max']);
            $this->assertValidationMessage(
                ['username' => 'existing_user'],
                ['username' => ['unique:users,username']],
                'username',
                $copy['unique'],
            );
            $this->assertValidationMessage(['avatar' => 'not-a-file'], ['avatar' => ['file']], 'avatar', $copy['file']);
            $this->assertValidationMessage(
                ['avatar' => UploadedFile::fake()->create('avatar.txt', 1, 'text/plain')],
                ['avatar' => ['image']],
                'avatar',
                $copy['image'],
            );
            $this->assertValidationMessage(
                ['avatar' => UploadedFile::fake()->create('avatar.pdf', 1, 'application/pdf')],
                ['avatar' => ['mimes:jpeg,png']],
                'avatar',
                $copy['mimes'],
            );
            $this->assertValidationMessage(['images' => 'not-an-array'], ['images' => ['array']], 'images', $copy['array']);
            $this->assertValidationMessage(
                ['parent_id' => PHP_INT_MAX],
                ['parent_id' => ['exists:comments,id']],
                'parent_id',
                $copy['exists'],
            );
        }
    }

    public function test_current_password_validation_message_follows_account_locale(): void
    {
        foreach ([
            'id' => 'Kata sandi saat ini tidak benar.',
            'en' => 'The current password is incorrect.',
        ] as $locale => $expected) {
            $user = User::factory()->create(['locale' => $locale]);

            $this->actingAs($user)
                ->from('/profile')
                ->put('/password', [
                    'current_password' => 'wrong-password',
                    'password' => 'new-password',
                    'password_confirmation' => 'new-password',
                ])
                ->assertRedirect('/profile')
                ->assertSessionHasErrorsIn('updatePassword', [
                    'current_password' => $expected,
                ]);
        }
    }

    public function test_turnstile_application_error_follows_the_guest_locale(): void
    {
        Config::set([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.secret_key' => 'test-secret-key',
            'services.turnstile.hostname' => 'skuysocial.com',
            'services.turnstile.verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        ]);

        foreach ([
            'id' => 'Verifikasi keamanan gagal. Silakan coba lagi.',
            'en' => 'Security verification failed. Please try again.',
        ] as $locale => $expected) {
            Http::fake([
                'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                    'success' => false,
                ]),
            ]);

            $this->withSession(['locale' => $locale])
                ->from('/login')
                ->post('/login', [
                    'email' => "turnstile-{$locale}@example.com",
                    'password' => 'password',
                    'cf-turnstile-response' => "invalid-{$locale}-token",
                ])
                ->assertRedirect('/login')
                ->assertSessionHasErrors(['cf-turnstile-response' => $expected]);
        }
    }

    public function test_pagination_text_and_accessibility_copy_follow_the_guest_locale(): void
    {
        $expected = [
            'id' => [
                'navigation' => 'Navigasi halaman',
                'previous' => 'Sebelumnya',
                'next' => 'Berikutnya',
                'showing' => 'Menampilkan',
                'to' => 'sampai',
                'of' => 'dari',
                'results' => 'hasil',
                'page' => 'Buka halaman 2',
            ],
            'en' => [
                'navigation' => 'Pagination Navigation',
                'previous' => 'Previous',
                'next' => 'Next',
                'showing' => 'Showing',
                'to' => 'to',
                'of' => 'of',
                'results' => 'results',
                'page' => 'Go to page 2',
            ],
        ];

        foreach ($expected as $locale => $copy) {
            $this->post('/locale', ['locale' => $locale])->assertRedirect('/');

            $paginator = new LengthAwarePaginator(
                range(1, 15),
                30,
                15,
                1,
                ['path' => url('/b3-pagination')],
            );
            $html = (string) $paginator->links();

            $this->assertStringContainsString('aria-label="'.$copy['navigation'].'"', $html);
            $this->assertStringContainsString($copy['previous'], $html);
            $this->assertStringContainsString($copy['next'], $html);
            $this->assertStringContainsString($copy['showing'], $html);
            $this->assertStringContainsString($copy['to'], $html);
            $this->assertStringContainsString($copy['of'], $html);
            $this->assertStringContainsString($copy['results'], $html);
            $this->assertStringContainsString('aria-label="'.$copy['page'].'"', $html);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     */
    private function assertValidationMessage(
        array $data,
        array $rules,
        string $attribute,
        string $expected,
    ): void {
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertSame($expected, $validator->errors()->first($attribute));
    }
}
