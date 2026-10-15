<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowGuestPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_uses_the_flow_guest_palette_without_changing_auth_destinations(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('data-flow-guest="welcome"', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('register').'"', false);
    }

    public function test_login_and_registration_preserve_actual_form_destinations(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('data-flow-guest="auth"', false)
            ->assertSee('action="'.route('login').'"', false);

        $this->get(route('register'))->assertOk()
            ->assertSee('data-flow-guest="auth"', false)
            ->assertSee('action="'.route('register').'"', false);
    }
}
