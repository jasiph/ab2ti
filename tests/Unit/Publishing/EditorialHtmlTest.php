<?php

namespace Tests\Unit\Publishing;

use App\Support\Html\EditorialHtml;
use Tests\TestCase;

class EditorialHtmlTest extends TestCase
{
    public function test_it_keeps_supported_editorial_markup(): void
    {
        $html = app(EditorialHtml::class)->sanitize(
            '<h2>Judul</h2><p>Halo <strong>AB2TI</strong></p><ul><li>Satu</li></ul>',
        );

        $this->assertStringContainsString('<h2>Judul</h2>', $html);
        $this->assertStringContainsString('<strong>AB2TI</strong>', $html);
        $this->assertStringContainsString('<li>Satu</li>', $html);
    }

    public function test_it_removes_scripts_event_handlers_and_javascript_urls(): void
    {
        $html = app(EditorialHtml::class)->sanitize(
            '<p>Aman</p><script>alert(1)</script><p><img src="https://ab2ti.test/a.jpg" onerror="alert(2)"><a href="javascript:alert(3)">x</a></p>',
        );

        $this->assertStringContainsString('Aman', $html);
        $this->assertStringContainsString('https://ab2ti.test/a.jpg', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_it_removes_iframes_and_inline_styles(): void
    {
        $html = app(EditorialHtml::class)->sanitize(
            '<p class="lead">Teks</p><iframe src="https://evil.example/embed"></iframe><div style="background:url(javascript:alert(1))" class="card">Isi</div>',
        );

        $this->assertStringContainsString('Teks', $html);
        $this->assertStringContainsString('Isi', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('style=', $html);
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', app(EditorialHtml::class)->sanitize(null));
        $this->assertSame('', app(EditorialHtml::class)->sanitize('   '));
    }
}
