<?php

namespace Tests\Feature\Publishing;

use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class PublishingTiptapTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_valid_editor_html_persists_and_malicious_markup_is_stripped(): void
    {
        $staff = $this->userWithPermissions([
            'publishing-publications.view',
            'publishing-publications.create',
            'publishing-publications.update',
            'publishing-publications.publish',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.store'), [
                'type' => 'berita',
                'title' => 'Konten editor',
                'slug' => 'konten-editor',
                'content' => '<p>Halo <strong>AB2TI</strong></p><script>alert(1)</script><p><img src="https://example.com/a.jpg" onerror="alert(2)"><a href="javascript:alert(3)">tautan</a></p>',
            ])
            ->assertRedirect();

        $publication = Publication::query()->first();
        $this->assertNotNull($publication);
        $this->assertStringContainsString('Halo', $publication->content);
        $this->assertStringContainsString('AB2TI', $publication->content);
        $this->assertStringNotContainsString('<script', $publication->content);
        $this->assertStringNotContainsString('alert(1)', $publication->content);
        $this->assertStringNotContainsString('onerror', $publication->content);
        $this->assertStringNotContainsString('javascript:', $publication->content);
        $this->assertStringContainsString('https://example.com/a.jpg', $publication->content);

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.publish', $publication))
            ->assertRedirect();

        $this->get('/berita/konten-editor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Publishing/Show')
                ->where('publication.content', fn ($content) => is_string($content)
                    && str_contains($content, 'Halo')
                    && ! str_contains($content, '<script')
                    && ! str_contains($content, 'javascript:')));
    }

    public function test_body_is_not_silently_stripped_on_update(): void
    {
        $staff = $this->userWithPermissions([
            'publishing-publications.view',
            'publishing-publications.update',
        ]);
        $publication = Publication::factory()->create([
            'created_by' => $staff->id,
            'content' => '<p>Awal</p>',
        ]);

        $this->actingAs($staff)
            ->put(route('admin.publishing.publications.update', $publication), [
                'type' => 'berita',
                'title' => $publication->title,
                'slug' => $publication->slug,
                'content' => '<h2>Judul isi</h2><p>Paragraf tetap ada.</p>',
            ])
            ->assertRedirect();

        $fresh = $publication->fresh()->content;
        $this->assertStringContainsString('Judul isi', $fresh);
        $this->assertStringContainsString('Paragraf tetap ada.', $fresh);
        $this->assertNotSame('', $fresh);
        $this->assertNotSame('<p>Awal</p>', $fresh);
    }
}
