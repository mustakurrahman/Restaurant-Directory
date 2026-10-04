<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AdminComponentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function render(string $blade, array $errors = []): string
    {
        view()->share('errors', (new ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag($errors)));

        return Blade::render($blade);
    }

    public function test_status_badge_colours_follow_the_status(): void
    {
        $expected = [
            'published' => 'text-green-400', 'approved' => 'text-green-400',
            'pending' => 'text-gold-500', 'rejected' => 'text-red-400',
            'draft' => 'text-cream/70', 'something-new' => 'text-cream/70',
        ];

        foreach ($expected as $status => $colour) {
            $html = Blade::render('<x-status-badge :status="$status" />', ['status' => $status]);

            $this->assertStringContainsString($colour, $html, "{$status} has the wrong colour");
            $this->assertStringContainsString('>'.ucfirst($status).'<', $html);
        }
    }

    public function test_form_field_shows_label_hint_and_error(): void
    {
        $html = $this->render('<x-form-field name="opens_at" label="Opens" hint="24-hour clock"><input id="opens_at"></x-form-field>');
        $this->assertStringContainsString('for="opens_at"', $html);
        $this->assertStringContainsString('24-hour clock', $html);
        $this->assertStringContainsString('<input id="opens_at">', $html);

        $html = $this->render('<x-form-field name="opens_at" label="Opens" hint="24-hour clock"><input id="opens_at"></x-form-field>', ['opens_at' => 'Required.']);
        $this->assertStringContainsString('Required.', $html);
        $this->assertStringNotContainsString('24-hour clock', $html); // the hint makes room for the error
    }

    public function test_the_style_guide_page_renders_every_component(): void
    {
        $html = view('admin.components')->render();

        foreach (['Save changes', 'Text input', 'Select', 'Textarea', 'Checkbox: Free Wi-Fi', 'Pending', 'Trattoria Bella Luna', 'Showing 31 to 40 of 95', 'This is what an error message looks like.'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        // The code samples are shown as text, not run
        $this->assertStringContainsString('&lt;x-button type="submit"&gt;', $html);
    }

    public function test_the_style_guide_route_only_exists_on_a_local_computer(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map->uri();

        $this->assertFalse($uris->contains('admin/components')); // tests run in the "testing" environment
    }
}
