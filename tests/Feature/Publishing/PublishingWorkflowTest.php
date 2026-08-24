<?php

namespace Tests\Feature\Publishing;

use App\Enums\PublicationStatus;
use App\Enums\PublicationType;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class PublishingWorkflowTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_authorized_actor_can_create_a_draft(): void
    {
        $staff = $this->editor();

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.store'), [
                'type' => 'berita',
                'title' => 'Benih untuk musim tanam',
                'excerpt' => 'Ringkasan singkat.',
                'content' => '<p>Isi berita.</p>',
                'status' => 'published',
                'published_at' => '2020-01-01 00:00:00',
                'created_by' => User::factory()->create()->id,
            ])
            ->assertRedirect();

        $publication = Publication::query()->first();

        $this->assertNotNull($publication);
        $this->assertSame(PublicationStatus::Draft, $publication->status);
        $this->assertSame(PublicationType::Berita, $publication->type);
        $this->assertSame('benih-untuk-musim-tanam', $publication->slug);
        $this->assertNull($publication->published_at);
        $this->assertSame($staff->id, $publication->created_by);
        $this->assertSame($staff->id, $publication->updated_by);
        $this->assertSame('<p>Isi berita.</p>', $publication->content);
    }

    public function test_draft_can_be_updated_without_changing_status(): void
    {
        $staff = $this->editor();
        $publication = Publication::factory()->create([
            'created_by' => $staff->id,
            'title' => 'Judul lama',
            'slug' => 'judul-lama',
        ]);

        $this->actingAs($staff)
            ->put(route('admin.publishing.publications.update', $publication), [
                'type' => 'kegiatan',
                'title' => 'Judul baru',
                'slug' => 'judul-baru',
                'excerpt' => 'Ringkasan baru',
                'content' => '<p>Isi baru</p>',
                'status' => 'published',
                'published_at' => '2019-05-05 12:00:00',
            ])
            ->assertRedirect(route('admin.publishing.publications.edit', $publication));

        $publication->refresh();
        $this->assertSame(PublicationStatus::Draft, $publication->status);
        $this->assertSame(PublicationType::Kegiatan, $publication->type);
        $this->assertSame('Judul baru', $publication->title);
        $this->assertNull($publication->published_at);
        $this->assertSame($staff->id, $publication->updated_by);
    }

    public function test_valid_draft_can_be_published_with_system_timestamp(): void
    {
        Carbon::setTestNow('2026-08-20 10:15:00');

        $staff = $this->editor();
        $publication = Publication::factory()->create([
            'created_by' => $staff->id,
            'title' => 'Siap terbit',
            'slug' => 'siap-terbit',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.publish', $publication), [
                'published_at' => '2018-01-01 00:00:00',
            ])
            ->assertRedirect(route('admin.publishing.publications.edit', $publication));

        $publication->refresh();
        $this->assertSame(PublicationStatus::Published, $publication->status);
        $this->assertTrue($publication->published_at->equalTo(Carbon::parse('2026-08-20 10:15:00')));
        $this->assertSame($staff->id, $publication->updated_by);
    }

    public function test_publish_is_rejected_when_title_is_missing(): void
    {
        $staff = $this->editor();
        $publication = Publication::factory()->create([
            'created_by' => $staff->id,
            'title' => 'Ada judul',
            'slug' => 'ada-judul',
        ]);
        $publication->forceFill(['title' => ''])->save();

        $this->from(route('admin.publishing.publications.edit', $publication))
            ->actingAs($staff)
            ->post(route('admin.publishing.publications.publish', $publication))
            ->assertRedirect(route('admin.publishing.publications.edit', $publication))
            ->assertSessionHas('error');

        $this->assertSame('draft', $publication->fresh()->status->value);
        $this->assertNull($publication->fresh()->published_at);
    }

    public function test_unpublish_returns_to_draft_and_keeps_published_at(): void
    {
        $staff = $this->editor();
        $publishedAt = Carbon::parse('2026-07-01 08:00:00');
        $publication = Publication::factory()->published()->create([
            'created_by' => $staff->id,
            'published_at' => $publishedAt,
        ]);

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.unpublish', $publication))
            ->assertRedirect(route('admin.publishing.publications.edit', $publication));

        $publication->refresh();
        $this->assertSame(PublicationStatus::Draft, $publication->status);
        $this->assertTrue($publication->published_at->equalTo($publishedAt));

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.publish', $publication))
            ->assertRedirect();

        $this->assertTrue($publication->fresh()->published_at->equalTo($publishedAt));
    }

    public function test_slug_collision_is_resolved_server_side(): void
    {
        $staff = $this->editor();
        Publication::factory()->create([
            'created_by' => $staff->id,
            'slug' => 'hari-pangan',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.store'), [
                'type' => 'berita',
                'title' => 'Hari Pangan',
                'slug' => 'hari-pangan',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('publications', ['slug' => 'hari-pangan']);
        $this->assertDatabaseHas('publications', ['slug' => 'hari-pangan-2']);
    }

    public function test_invalid_publication_type_is_rejected(): void
    {
        $staff = $this->editor();

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.store'), [
                'type' => 'knowledge_article',
                'title' => 'Bukan publikasi',
            ])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseCount('publications', 0);
    }

    public function test_draft_can_be_deleted_and_published_cannot(): void
    {
        $staff = $this->editor();
        $draft = Publication::factory()->create(['created_by' => $staff->id]);
        $published = Publication::factory()->published()->create(['created_by' => $staff->id]);

        $this->actingAs($staff)
            ->delete(route('admin.publishing.publications.destroy', $draft))
            ->assertRedirect(route('admin.publishing.publications.index'));

        $this->from(route('admin.publishing.publications.edit', $published))
            ->actingAs($staff)
            ->delete(route('admin.publishing.publications.destroy', $published))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('publications', ['id' => $draft->id]);
        $this->assertDatabaseHas('publications', ['id' => $published->id, 'status' => 'published']);
    }

    private function editor(): User
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
