<?php

namespace Tests\Feature\Knowledge;

use App\Models\KnowledgeArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class KnowledgeEditorialSafetyTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_knowledge_article_uses_shared_pipeline_for_scripts_and_known_blocks(): void
    {
        $staff = $this->userWithPermissions([
            'knowledge-articles.view',
            'knowledge-articles.create',
            'knowledge-articles.update',
            'knowledge-articles.publish',
        ]);

        $content = <<<'HTML'
<p>Halo <strong>AB2TI</strong></p>
<script>alert(1)</script>
<p><img src="https://example.com/a.jpg" onerror="alert(2)"><a href="javascript:alert(3)">tautan</a></p>
<iframe src="https://evil.example/embed"></iframe>
<div class="tiptap-block-wrapper" data-block-type="Two Columns Feature" onclick="alert(9)">
  <div class="tiptap-block-content">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 my-8">
      <div><h4>Visi Kami</h4><p>Teks visi.</p></div>
    </div>
  </div>
</div>
<div class="tiptap-block-wrapper" data-block-type="Beautiful Image with Caption">
  <div class="tiptap-block-content">
    <figure>
      <img src="https://ab2ti.test/storage/example.jpg" alt="Lanskap" />
      <figcaption>Lanskap Biodiversitas</figcaption>
    </figure>
  </div>
</div>
<div class="tiptap-block-wrapper" data-block-type="Unknown Knowledge Block">
  <div class="tiptap-block-content"><p>Teks tetap</p><script>steal()</script></div>
</div>
HTML;

        $this->actingAs($staff)
            ->post(route('admin.knowledge.articles.store'), [
                'title' => 'Konten editor',
                'slug' => 'konten-editor',
                'content' => $content,
            ])
            ->assertRedirect();

        $article = KnowledgeArticle::query()->first();
        $this->assertNotNull($article);
        $this->assertStringContainsString('Halo', $article->content);
        $this->assertStringContainsString('AB2TI', $article->content);
        $this->assertStringContainsString('data-block-type="Two Columns Feature"', $article->content);
        $this->assertStringContainsString('Visi Kami', $article->content);
        $this->assertStringContainsString('Beautiful Image with Caption', $article->content);
        $this->assertStringContainsString('Lanskap Biodiversitas', $article->content);
        $this->assertStringNotContainsString('<script', $article->content);
        $this->assertStringNotContainsString('alert(1)', $article->content);
        $this->assertStringNotContainsString('onerror', $article->content);
        $this->assertStringNotContainsString('onclick', $article->content);
        $this->assertStringNotContainsString('javascript:', $article->content);
        $this->assertStringNotContainsString('<iframe', $article->content);
        $this->assertStringNotContainsString('Unknown Knowledge Block', $article->content);
        $this->assertStringContainsString('Teks tetap', $article->content);

        $this->actingAs($staff)
            ->post(route('admin.knowledge.articles.publish', $article))
            ->assertRedirect();

        $this->get('/teknologi-tani/konten-editor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Knowledge/Show')
                ->where('article.content', fn ($html) => is_string($html)
                    && str_contains($html, 'Halo')
                    && str_contains($html, 'Two Columns Feature')
                    && str_contains($html, 'Visi Kami')
                    && str_contains($html, 'Lanskap Biodiversitas')
                    && ! str_contains($html, '<script')
                    && ! str_contains($html, 'javascript:')
                    && ! str_contains($html, 'Unknown Knowledge Block')));
    }
}
