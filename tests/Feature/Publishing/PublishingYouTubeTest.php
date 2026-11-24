<?php

namespace Tests\Feature\Publishing;

use App\Enums\PublicationType;
use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class PublishingYouTubeTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_publication_can_be_created_without_a_youtube_url(): void
    {
        $staff = $this->editor();

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.store'), [
                'type' => 'berita',
                'title' => 'Tanpa video',
            ])
            ->assertRedirect();

        $publication = Publication::query()->first();

        $this->assertNotNull($publication);
        $this->assertNull($publication->youtube_url);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function acceptedAdminUrlProvider(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'berita'],
            'short link' => ['https://youtu.be/dQw4w9WgXcQ', 'kegiatan'],
            'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'siaran_pers'],
            'live' => ['https://www.youtube.com/live/dQw4w9WgXcQ', 'liputan_media'],
        ];
    }

    #[DataProvider('acceptedAdminUrlProvider')]
    public function test_publication_can_be_created_with_a_supported_youtube_url(string $url, string $type): void
    {
        $staff = $this->editor();

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.store'), [
                'type' => $type,
                'title' => 'Dengan video',
                'youtube_url' => $url,
            ])
            ->assertRedirect();

        $publication = Publication::query()->first();

        $this->assertNotNull($publication);
        $this->assertSame($type, $publication->type->value);
        $this->assertSame($url, $publication->youtube_url);
    }

    public function test_non_youtube_url_is_rejected(): void
    {
        $staff = $this->editor();

        $this->actingAs($staff)
            ->from(route('admin.publishing.publications.create'))
            ->post(route('admin.publishing.publications.store'), [
                'type' => 'berita',
                'title' => 'URL asing',
                'youtube_url' => 'https://vimeo.com/123456789',
            ])
            ->assertRedirect(route('admin.publishing.publications.create'))
            ->assertSessionHasErrors('youtube_url')
            ->assertSessionHasInput('youtube_url', 'https://vimeo.com/123456789');

        $this->assertDatabaseCount('publications', 0);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function deceptiveHostProvider(): array
    {
        return [
            'subdomain lookalike' => ['https://youtube.example.com/watch?v=dQw4w9WgXcQ'],
            'hyphen lookalike' => ['https://evil-youtube.com/watch?v=dQw4w9WgXcQ'],
        ];
    }

    #[DataProvider('deceptiveHostProvider')]
    public function test_deceptive_youtube_looking_hosts_are_rejected(string $url): void
    {
        $staff = $this->editor();

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.store'), [
                'type' => 'berita',
                'title' => 'Host menipu',
                'youtube_url' => $url,
            ])
            ->assertSessionHasErrors('youtube_url');

        $this->assertDatabaseCount('publications', 0);
    }

    public function test_existing_youtube_url_can_be_changed_or_cleared(): void
    {
        $staff = $this->editor();
        $publication = Publication::factory()->create([
            'created_by' => $staff->id,
            'title' => 'Judul video',
            'slug' => 'judul-video',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $this->actingAs($staff)
            ->put(route('admin.publishing.publications.update', $publication), [
                'type' => 'berita',
                'title' => 'Judul video',
                'slug' => 'judul-video',
                'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
            ])
            ->assertRedirect(route('admin.publishing.publications.edit', $publication));

        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', $publication->fresh()->youtube_url);

        $this->actingAs($staff)
            ->put(route('admin.publishing.publications.update', $publication), [
                'type' => 'berita',
                'title' => 'Judul video',
                'slug' => 'judul-video',
                'youtube_url' => '',
            ])
            ->assertRedirect(route('admin.publishing.publications.edit', $publication));

        $this->assertNull($publication->fresh()->youtube_url);
    }

    public function test_invalid_replacement_youtube_url_is_rejected_and_existing_value_is_kept(): void
    {
        $staff = $this->editor();
        $publication = Publication::factory()->create([
            'created_by' => $staff->id,
            'title' => 'Judul video',
            'slug' => 'judul-video',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $this->from(route('admin.publishing.publications.edit', $publication))
            ->actingAs($staff)
            ->put(route('admin.publishing.publications.update', $publication), [
                'type' => 'berita',
                'title' => 'Judul video',
                'slug' => 'judul-video',
                'youtube_url' => 'https://example.com/watch?v=dQw4w9WgXcQ',
            ])
            ->assertRedirect(route('admin.publishing.publications.edit', $publication))
            ->assertSessionHasErrors('youtube_url');

        $this->assertSame(
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            $publication->fresh()->youtube_url,
        );
    }

    public function test_admin_edit_form_exposes_the_stored_youtube_url(): void
    {
        $staff = $this->editor();
        $publication = Publication::factory()->create([
            'created_by' => $staff->id,
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.publishing.publications.edit', $publication))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Publishing/Publications/Form')
                ->where('publication.youtube_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function test_public_publication_view_exposes_derived_youtube_embed_data(): void
    {
        Publication::factory()->published()->create([
            'type' => PublicationType::Berita,
            'title' => 'Liputan panen',
            'slug' => 'liputan-panen',
            'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);

        $this->get('/berita/liputan-panen')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Publishing/Show')
                ->where('publication.youtube.url', 'https://youtu.be/dQw4w9WgXcQ')
                ->where('publication.youtube.video_id', 'dQw4w9WgXcQ')
                ->where('publication.youtube.embed_url', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
                ->missing('publication.youtube.html'));
    }

    public function test_published_publication_without_youtube_url_exposes_null_youtube_payload(): void
    {
        Publication::factory()->published()->create([
            'type' => PublicationType::Berita,
            'title' => 'Tanpa video',
            'slug' => 'tanpa-video',
        ]);

        $this->get('/berita/tanpa-video')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Publishing/Show')
                ->where('publication.youtube', null)
                ->where('publication.title', 'Tanpa video'));
    }

    public function test_unpublished_publication_with_youtube_url_remains_private(): void
    {
        $draft = Publication::factory()->create([
            'type' => PublicationType::Berita,
            'slug' => 'draf-video',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $this->get('/berita/draf-video')->assertNotFound();
        $this->get('/berita/'.$draft->id)->assertNotFound();
    }

    private function editor()
    {
        return $this->userWithPermissions([
            'publishing-publications.view',
            'publishing-publications.create',
            'publishing-publications.update',
            'publishing-publications.publish',
            'publishing-publications.delete',
        ]);
    }
}
