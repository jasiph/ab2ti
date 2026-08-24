<?php

namespace Tests\Feature\Cms;

use App\Models\BlockTemplate;
use App\Models\Page;
use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class CmsEditorialContentTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_cms_page_save_strips_executable_markup_and_keeps_headings(): void
    {
        $user = $this->userWithPermissions(['cms.create', 'cms.view']);

        $this->actingAs($user)
            ->post(route('admin.pages.store'), [
                'title' => 'Tentang',
                'slug' => 'tentang-aman',
                'is_active' => true,
                'content' => '<h2>Profil</h2><p>Halo</p><script>alert(1)</script><iframe src="https://evil.example"></iframe>',
            ])
            ->assertRedirect(route('admin.pages.index'));

        $page = Page::query()->where('slug', 'tentang-aman')->first();
        $this->assertNotNull($page);
        $this->assertStringContainsString('Profil', $page->content);
        $this->assertStringContainsString('Halo', $page->content);
        $this->assertStringNotContainsString('<script', $page->content);
        $this->assertStringNotContainsString('<iframe', $page->content);
    }

    public function test_legacy_unsaved_cms_content_cannot_execute_through_the_public_renderer(): void
    {
        Page::query()->create([
            'title' => 'Legacy',
            'slug' => 'legacy-unsafe',
            'is_active' => true,
            'content' => '<h2>Judul lama</h2><p>Teks sah.</p><script>alert("xss")</script><p onclick="alert(1)">klik</p><iframe src="https://evil.example"></iframe>',
        ]);

        $this->get('/legacy-unsafe')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Page/Show')
                ->where('page.slug', 'legacy-unsafe')
                ->where('page.content', fn ($content) => is_string($content)
                    && str_contains($content, 'Judul lama')
                    && str_contains($content, 'Teks sah.')
                    && ! str_contains($content, '<script')
                    && ! str_contains($content, 'onclick')
                    && ! str_contains($content, '<iframe')));
    }

    public function test_known_template_block_survives_cms_save(): void
    {
        $user = $this->userWithPermissions(['cms.create']);

        $this->actingAs($user)
            ->post(route('admin.pages.store'), [
                'title' => 'Dengan blok',
                'slug' => 'dengan-blok',
                'is_active' => true,
                'content' => '<div class="tiptap-block-wrapper" data-block-type="Two Columns Feature"><div class="tiptap-block-content"><h4>Visi Kami</h4><p>Teks.</p></div></div>',
            ])
            ->assertRedirect();

        $page = Page::query()->where('slug', 'dengan-blok')->first();
        $this->assertStringContainsString('data-block-type="Two Columns Feature"', (string) $page?->content);
        $this->assertStringContainsString('Visi Kami', (string) $page?->content);
    }

    public function test_block_template_library_html_is_normalized_on_save(): void
    {
        $user = $this->userWithPermissions(['cms.create']);

        $this->actingAs($user)
            ->post(route('admin.block-templates.store'), [
                'name' => 'Kartu Uji',
                'description' => 'Uji',
                'html_content' => '<h3>Judul blok</h3><script>alert(1)</script><p>Isi</p>',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.block-templates.index'));

        $block = BlockTemplate::query()->where('name', 'Kartu Uji')->first();
        $this->assertNotNull($block);
        $this->assertStringContainsString('Judul blok', $block->html_content);
        $this->assertStringNotContainsString('<script', $block->html_content);
    }

    public function test_active_block_picker_payload_does_not_include_scripts(): void
    {
        BlockTemplate::query()->create([
            'name' => 'Legacy Snippet',
            'description' => 'lama',
            'html_content' => '<p>Sah</p><script>alert(1)</script>',
            'is_active' => true,
        ]);

        $user = $this->userWithPermissions(['cms.view']);

        $this->actingAs($user)
            ->getJson(route('admin.block-templates.active'))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Legacy Snippet'])
            ->assertJsonMissing(['html_content' => '<p>Sah</p><script>alert(1)</script>']);

        $payload = $this->actingAs($user)
            ->getJson(route('admin.block-templates.active'))
            ->json();

        $html = collect($payload)->firstWhere('name', 'Legacy Snippet')['html_content'] ?? '';
        $this->assertStringContainsString('Sah', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_legacy_published_publication_is_safe_on_public_render_without_resave(): void
    {
        Publication::factory()->published()->create([
            'slug' => 'lama-berita',
            'content' => '<p>Berita lama.</p><script>alert(1)</script>',
        ]);

        $this->get('/berita/lama-berita')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('publication.content', fn ($content) => is_string($content)
                    && str_contains($content, 'Berita lama.')
                    && ! str_contains($content, '<script')));
    }
}
