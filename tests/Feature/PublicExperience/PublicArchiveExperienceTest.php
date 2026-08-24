<?php

namespace Tests\Feature\PublicExperience;

use App\Enums\PublicationType;
use App\Models\KnowledgeArticle;
use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicArchiveExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_publication_search_finds_published_title_and_excerpt_only(): void
    {
        Publication::factory()->published()->create([
            'title' => 'Panen jagung manis',
            'slug' => 'panen-jagung',
            'excerpt' => 'Ringkasan jagung.',
        ]);
        Publication::factory()->published()->create([
            'title' => 'Lokakarya padi',
            'slug' => 'lokakarya-padi',
            'excerpt' => 'Ringkasan padi.',
        ]);
        Publication::factory()->create([
            'title' => 'Draf jagung',
            'slug' => 'draf-jagung',
        ]);

        $this->get('/publikasi?q=jagung')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Publishing/Index')
                ->where('filters.q', 'jagung')
                ->has('publications.data', 1)
                ->where('publications.data.0.title', 'Panen jagung manis')
                ->missing('publications.data.0.created_by'));

        $this->get('/publikasi?q=Ringkasan%20padi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('publications.data', 1)
                ->where('publications.data.0.slug', 'lokakarya-padi'));
    }

    public function test_publication_type_filter_and_search_use_query_string_pagination(): void
    {
        Publication::factory()->published()->create([
            'type' => PublicationType::Berita,
            'title' => 'Berita jagung',
            'slug' => 'berita-jagung',
        ]);
        Publication::factory()->published()->create([
            'type' => PublicationType::Kegiatan,
            'title' => 'Kegiatan jagung',
            'slug' => 'kegiatan-jagung',
        ]);

        $this->get('/berita?q=jagung')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeType', 'berita')
                ->where('filters.q', 'jagung')
                ->has('publications.data', 1)
                ->where('publications.data.0.slug', 'berita-jagung')
                ->where('typeOptions.0.value', 'berita')
                ->where('typeOptions.0.path', 'berita'));

        $this->get('/kegiatan/hanya-berita')->assertNotFound();
    }

    public function test_publication_detail_lists_related_published_same_type_only(): void
    {
        $current = Publication::factory()->published()->create([
            'type' => PublicationType::Berita,
            'title' => 'Utama',
            'slug' => 'utama',
            'published_at' => now(),
        ]);
        Publication::factory()->published()->create([
            'type' => PublicationType::Berita,
            'title' => 'Terkait baru',
            'slug' => 'terkait-baru',
            'published_at' => now()->subDay(),
        ]);
        Publication::factory()->published()->create([
            'type' => PublicationType::Kegiatan,
            'title' => 'Jenis lain',
            'slug' => 'jenis-lain',
        ]);
        Publication::factory()->create([
            'type' => PublicationType::Berita,
            'title' => 'Draf terkait',
            'slug' => 'draf-terkait',
        ]);

        $this->get('/berita/utama')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Publishing/Show')
                ->where('publication.title', 'Utama')
                ->has('related', 1)
                ->where('related.0.slug', 'terkait-baru')
                ->where('related.0.type', 'berita')
                ->missing('related.0.status'));

        $this->get('/berita/'.$current->id)->assertNotFound();
        $this->get('/berita/draf-terkait')->assertNotFound();
    }

    public function test_knowledge_search_excludes_drafts_and_preserves_query(): void
    {
        KnowledgeArticle::factory()->published()->create([
            'title' => 'Pengolahan benih jagung',
            'slug' => 'pengolahan-jagung',
            'excerpt' => 'Teknik jagung.',
        ]);
        KnowledgeArticle::factory()->published()->create([
            'title' => 'Pengairan padi',
            'slug' => 'pengairan-padi',
        ]);
        KnowledgeArticle::factory()->create([
            'title' => 'Draf jagung',
            'slug' => 'draf-jagung',
        ]);

        $this->get('/teknologi-tani?q=jagung')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Knowledge/Index')
                ->where('filters.q', 'jagung')
                ->has('articles.data', 1)
                ->where('articles.data.0.slug', 'pengolahan-jagung')
                ->missing('articles.data.0.created_by'));
    }

    public function test_knowledge_detail_further_reading_is_published_and_not_self(): void
    {
        KnowledgeArticle::factory()->published()->create([
            'title' => 'Artikel utama',
            'slug' => 'artikel-utama',
            'published_at' => now(),
        ]);
        KnowledgeArticle::factory()->published()->create([
            'title' => 'Artikel lain',
            'slug' => 'artikel-lain',
            'published_at' => now()->subDay(),
        ]);
        KnowledgeArticle::factory()->create([
            'title' => 'Draf lain',
            'slug' => 'draf-lain',
        ]);

        $this->get('/teknologi-tani/artikel-utama')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Knowledge/Show')
                ->where('article.title', 'Artikel utama')
                ->has('furtherReading', 1)
                ->where('furtherReading.0.slug', 'artikel-lain')
                ->missing('furtherReading.0.status'));

        $this->get('/teknologi-tani/draf-lain')->assertNotFound();
    }

    public function test_empty_search_does_not_error_and_sanitization_still_applies(): void
    {
        Publication::factory()->published()->create([
            'title' => 'Aman',
            'slug' => 'aman',
            'content' => '<p>Teks sah.</p><script>alert(1)</script>',
        ]);

        $this->get('/publikasi?q=tidak-ada-hasil')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('publications.data', 0)
                ->where('filters.q', 'tidak-ada-hasil'));

        $this->get('/berita/aman')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('publication.content', fn ($content) => is_string($content)
                    && str_contains($content, 'Teks sah.')
                    && ! str_contains($content, '<script')));
    }

    public function test_publication_pagination_keeps_search_query(): void
    {
        foreach (range(1, 13) as $i) {
            Publication::factory()->published()->create([
                'title' => "Jagung item {$i}",
                'slug' => "jagung-item-{$i}",
                'published_at' => now()->subDays($i),
            ]);
        }

        $this->get('/publikasi?q=Jagung&page=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.q', 'Jagung')
                ->where('publications.current_page', 2)
                ->has('publications.data', 1));
    }
}
