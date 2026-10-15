<?php

namespace Tests\Feature;

use Tests\TestCase;

class FlowErrorPresentationTest extends TestCase
{
    public function test_missing_public_page_keeps_a_real_404_with_flow_error_presentation(): void
    {
        $this->get('/page-does-not-exist-404-flow-check')
            ->assertNotFound()
            ->assertSee('data-flow-guest="error"', false)
            ->assertSee('class="skuy-flow-error-card', false)
            ->assertSee('id="error-title"', false);
    }
}
