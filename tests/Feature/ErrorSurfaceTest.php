<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorSurfaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([403, 404, 419, 429, 500] as $status) {
            Route::middleware('web')->get('/__error-surfaces/'.$status, static function () use ($status): never {
                abort($status);
            });
        }
    }

    public function test_error_surfaces_render_with_indonesian_product_copy_and_statuses(): void
    {
        $expectedTitles = [
            403 => trans('moderation.forbidden.title', [], 'id'),
            404 => trans('errors.404.title', [], 'id'),
            419 => trans('errors.419.title', [], 'id'),
            429 => trans('errors.429.title', [], 'id'),
            500 => trans('errors.500.title', [], 'id'),
        ];

        foreach ($expectedTitles as $status => $title) {
            $response = $this
                ->withSession(['locale' => 'id'])
                ->get('/__error-surfaces/'.$status);

            $response
                ->assertStatus($status)
                ->assertSeeText('SkuySocial')
                ->assertSeeText($title)
                ->assertSee('lang="id"', false);

            $this->assertModernErrorMarkup($response->getContent());
        }
    }

    public function test_error_surfaces_render_with_english_product_copy(): void
    {
        $expectedTitles = [
            403 => trans('moderation.forbidden.title', [], 'en'),
            404 => trans('errors.404.title', [], 'en'),
            419 => trans('errors.419.title', [], 'en'),
            429 => trans('errors.429.title', [], 'en'),
            500 => trans('errors.500.title', [], 'en'),
        ];

        foreach ($expectedTitles as $status => $title) {
            $response = $this
                ->withSession(['locale' => 'en'])
                ->get('/__error-surfaces/'.$status);

            $response
                ->assertStatus($status)
                ->assertSeeText($title)
                ->assertSee('lang="en"', false);
        }
    }

    public function test_forbidden_surface_keeps_generic_moderation_copy_without_internal_details(): void
    {
        $response = $this
            ->withSession(['locale' => 'en'])
            ->get('/__error-surfaces/403');

        $response
            ->assertForbidden()
            ->assertSeeText(trans('moderation.forbidden.generic_body', [], 'en'))
            ->assertDontSeeText(trans('moderation.restricted.denial_body', [], 'en'));
    }

    public function test_internal_error_surface_does_not_render_exception_details(): void
    {
        $content = view('errors.500', [
            'exception' => new \RuntimeException('internal-debug-secret'),
        ])->render();

        $this->assertStringContainsString(trans('errors.500.title'), $content);
        $this->assertStringNotContainsString('internal-debug-secret', $content);
    }

    private function assertModernErrorMarkup(string $content): void
    {
        foreach ([
            'border-' . '4',
            'border-' . '8',
            'rotate' . '-',
            'translate-' . 'x',
            'translate-' . 'y',
            'bg-red-' . '400',
            'bg-yellow-' . '300',
            'bg-cyan-' . '400',
            'border-' . 'black',
            'font-' . 'black',
            'upper' . 'case',
            'tracking-' . 'tighter',
            'tracking-' . 'wide',
        ] as $legacyPattern) {
            $this->assertStringNotContainsString($legacyPattern, $content);
        }

        $this->assertDoesNotMatchRegularExpression('/shadow-\[[^\]]*(?:px|rgba\()/', $content);
    }
}
