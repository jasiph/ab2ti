<?php

namespace Tests\Unit\Editorial;

use App\Support\Editorial\EditorialContent;
use Tests\TestCase;

class EditorialContentTest extends TestCase
{
    public function test_known_highlight_card_survives_with_inner_html_sanitized(): void
    {
        $input = <<<'HTML'
<div class="tiptap-block-wrapper" data-block-type="Highlight Card (Android Style)" onclick="alert(1)" data-renderer="App\Blocks\Evil">
  <div class="tiptap-block-content" data-disk="secret">
    <h3 class="text-xl font-bold">Informasi Penting</h3>
    <p>Isi kartu.</p>
    <script>alert(1)</script>
    <iframe src="https://evil.example/embed"></iframe>
    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" onload="alert(2)"><path stroke-linecap="round" d="M13 16h-1"/></svg>
  </div>
</div>
HTML;

        $html = app(EditorialContent::class)->normalize($input);

        $this->assertStringContainsString('data-block-type="Highlight Card (Android Style)"', $html);
        $this->assertStringContainsString('tiptap-block-wrapper', $html);
        $this->assertStringContainsString('Informasi Penting', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('data-renderer', $html);
        $this->assertStringNotContainsString('data-disk', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('onload', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_unknown_block_type_cannot_invoke_a_renderer_and_is_sanitized(): void
    {
        $input = <<<'HTML'
<div class="tiptap-block-wrapper" data-block-type="App\Blocks\EvilRenderer">
  <div class="tiptap-block-content">
    <p>Tetap tampil</p>
    <script>document.cookie</script>
  </div>
</div>
HTML;

        $html = app(EditorialContent::class)->render($input);

        $this->assertStringNotContainsString('App\\Blocks\\EvilRenderer', $html);
        $this->assertStringNotContainsString('data-block-type="App', $html);
        $this->assertStringContainsString('Tetap tampil', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('document.cookie', $html);
    }

    public function test_two_column_and_image_caption_templates_keep_structure(): void
    {
        $pipeline = app(EditorialContent::class);

        $columns = $pipeline->normalize(<<<'HTML'
<div class="tiptap-block-wrapper" data-block-type="Two Columns Feature">
  <div class="tiptap-block-content">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 my-8">
      <div class="bg-background rounded-xl p-5"><h4 class="text-primary font-bold">Visi Kami</h4><p>Teks visi.</p></div>
    </div>
  </div>
</div>
HTML);

        $this->assertStringContainsString('data-block-type="Two Columns Feature"', $columns);
        $this->assertStringContainsString('Visi Kami', $columns);
        $this->assertStringContainsString('grid', $columns);

        $figure = $pipeline->normalize(<<<'HTML'
<div class="tiptap-block-wrapper" data-block-type="Beautiful Image with Caption">
  <div class="tiptap-block-content">
    <figure class="relative overflow-hidden">
      <img src="https://ab2ti.test/storage/example.jpg" alt="Lanskap" class="w-full" />
      <figcaption><h4>Lanskap Biodiversitas</h4></figcaption>
    </figure>
  </div>
</div>
HTML);

        $this->assertStringContainsString('data-block-type="Beautiful Image with Caption"', $figure);
        $this->assertStringContainsString('https://ab2ti.test/storage/example.jpg', $figure);
        $this->assertStringContainsString('Lanskap Biodiversitas', $figure);
        $this->assertStringContainsString('<figcaption', $figure);
    }

    public function test_javascript_and_storage_path_payloads_cannot_pass_through_block_attributes(): void
    {
        $html = app(EditorialContent::class)->render(<<<'HTML'
<div class="tiptap-block-wrapper" data-block-type="Highlight Card (Android Style)" data-object-key="secret/path" data-disk="membership_documents">
  <div class="tiptap-block-content">
    <img src="javascript:alert(1)" alt="x">
    <img src="file:///etc/passwd" alt="y">
    <a href="javascript:alert(2)">tautan</a>
    <p>Aman</p>
  </div>
</div>
HTML);

        $this->assertStringNotContainsString('data-object-key', $html);
        $this->assertStringNotContainsString('membership_documents', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('file://', $html);
        $this->assertStringContainsString('Aman', $html);
    }

    public function test_standard_tiptap_markup_is_preserved(): void
    {
        $html = app(EditorialContent::class)->normalize(
            '<h2>Judul</h2><p>Paragraf <a href="https://ab2ti.test/about">tautan</a>.</p><ul><li>Satu</li></ul>',
        );

        $this->assertStringContainsString('<h2>Judul</h2>', $html);
        $this->assertStringContainsString('https://ab2ti.test/about', $html);
        $this->assertStringContainsString('<li>Satu</li>', $html);
    }
}
