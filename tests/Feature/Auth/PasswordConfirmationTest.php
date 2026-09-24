<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/confirm-password');

        $response->assertStatus(200);
    }

    public function test_confirm_password_screen_renders_a_focusable_masked_current_password_input(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/confirm-password');

        $response->assertOk();
        $document = $this->documentFromHtml($response->getContent());

        $input = $document->getElementById('password');
        $this->assertNotNull($input);
        $this->assertSame('password', $input->getAttribute('type'));
        $this->assertSame('password', $input->getAttribute('name'));
        $this->assertSame('current-password', $input->getAttribute('autocomplete'));
        $this->assertFalse($input->hasAttribute('disabled'));
        $this->assertFalse($input->hasAttribute('readonly'));
        $this->assertTrue($input->hasAttribute('autofocus'));

        $label = (new DOMXPath($document))->query('//label[@for="password"]');
        $this->assertSame(1, $label->length);

        $confirmAction = route('password.confirm');
        $xpath = new DOMXPath($document);
        $form = $xpath->query('//form[@action="'.$confirmAction.'"]');
        $submitButton = $xpath->query(
            '//form[@action="'.$confirmAction.'"]/button[@type="submit"]',
        );

        $this->assertSame(1, $form->length);
        $this->assertSame('POST', $form->item(0)->getAttribute('method'));
        $this->assertSame(1, $submitButton->length);
    }

    public function test_invalid_password_error_keeps_the_input_present_and_associated(): void
    {
        $user = User::factory()->create();

        $response = $this->followingRedirects()
            ->actingAs($user)
            ->from('/confirm-password')
            ->post('/confirm-password', ['password' => 'wrong-password']);

        $response->assertOk()
            ->assertSee(__('auth.password'))
            ->assertSee('id="password-error"', false)
            ->assertSee('aria-describedby="password-error"', false)
            ->assertSee('aria-invalid="true"', false);

        $document = $this->documentFromHtml($response->getContent());

        $input = $document->getElementById('password');
        $this->assertNotNull($input);
        $this->assertSame('password', $input->getAttribute('type'));
        $this->assertFalse($input->hasAttribute('disabled'));
        $this->assertFalse($input->hasAttribute('readonly'));
    }

    public function test_confirming_password_returns_to_two_factor_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/pengaturan/keamanan/2fa')
            ->assertRedirect('/confirm-password')
            ->assertSessionHas('url.intended', '/pengaturan/keamanan/2fa');

        $this->post('/confirm-password', ['password' => 'password'])
            ->assertRedirect('/pengaturan/keamanan/2fa');

        $this->get('/pengaturan/keamanan/2fa')->assertOk();
    }

    public function test_password_can_be_confirmed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors();
    }

    private function documentFromHtml(string $html): DOMDocument
    {
        $document = new DOMDocument();
        $previousErrorHandling = libxml_use_internal_errors(true);

        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
        }

        return $document;
    }
}
