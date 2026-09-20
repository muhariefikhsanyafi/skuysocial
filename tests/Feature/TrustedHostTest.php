<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

class TrustedHostTest extends TestCase
{
    public function test_legacy_dot_com_host_is_rejected(): void
    {
        $response = $this->withProductionTrustedHosts(function () {
            return $this->get('http://skuysocial.com/login');
        });

        $response->assertStatus(400);
    }

    public function test_canonical_application_host_is_allowed(): void
    {
        $response = $this->withProductionTrustedHosts(function () {
            return $this->get('http://skuysocial.id/login');
        });

        $response->assertOk();
    }

    public function test_untrusted_host_is_rejected(): void
    {
        $response = $this->withProductionTrustedHosts(function () {
            return $this->get('http://untrusted.example.test/login');
        });

        $response->assertStatus(400);
    }

    public function test_application_subdomains_are_not_implicitly_trusted(): void
    {
        $response = $this->withProductionTrustedHosts(function () {
            return $this->get('http://preview.skuysocial.id/login');
        });

        $response->assertStatus(400);
    }

    public function test_canonical_host_preserves_password_reset_url_behavior(): void
    {
        $this->withProductionTrustedHosts(function (): void {
            $response = $this->get('http://skuysocial.id/forgot-password');

            $response
                ->assertOk()
                ->assertSee('action="'.route('password.email').'"', false);
        });
    }

    private function withProductionTrustedHosts(callable $callback): mixed
    {
        $previousEnvironment = app()->environment();

        app()->instance('env', 'production');

        try {
            return $callback();
        } finally {
            app()->instance('env', $previousEnvironment);
            Request::setTrustedHosts([]);
        }
    }
}
