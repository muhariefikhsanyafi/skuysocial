<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReleaseSmokeTest extends TestCase
{
    public function test_release_health_endpoint_boots_the_application(): void
    {
        $this->get('/up')->assertOk();
    }
}
