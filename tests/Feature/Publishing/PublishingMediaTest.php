<?php

namespace Tests\Feature\Publishing;

use App\Models\File;
use App\Models\MembershipApplication;
use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class PublishingMediaTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('membership_documents');
    }

    public function test_editor_can_attach_library_file_as_cover_without_exposing_storage_paths(): void
    {
        $staff = $this->userWithPermissions([
            'publishing-publications.view',
            'publishing-publications.create',
            'publishing-publications.update',
            'media.view',
            'media.create',
        ]);

        $asset = File::query()->create([
            'user_id' => $staff->id,
            'name' => 'sampul.jpg',
        ]);
        $asset->addMedia(UploadedFile::fake()->image('sampul.jpg', 1200, 800))
            ->toMediaCollection('default');

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.store'), [
                'type' => 'berita',
                'title' => 'Dengan sampul',
                'existing_cover_asset_id' => $asset->id,
            ])
            ->assertRedirect();

        $publication = Publication::query()->first();
        $this->assertTrue($publication?->hasMedia('publication-cover'));

        $this->actingAs($staff)
            ->get(route('admin.publishing.publications.edit', $publication))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Publishing/Publications/Form')
                ->where('publication.cover_url', fn ($url) => is_string($url) && $url !== '')
                ->missing('publication.disk')
                ->missing('publication.object_key')
                ->missing('publication.media'));
    }

    public function test_invalid_and_unknown_media_ids_are_rejected(): void
    {
        $staff = $this->userWithPermissions([
            'publishing-publications.view',
            'publishing-publications.create',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.store'), [
                'type' => 'berita',
                'title' => 'Media tidak valid',
                'existing_cover_asset_id' => 999999,
            ])
            ->assertSessionHasErrors('existing_cover_asset_id');

        $this->assertDatabaseCount('publications', 0);
    }

    public function test_membership_private_documents_cannot_be_selected_as_publication_media(): void
    {
        $staff = $this->userWithPermissions([
            'publishing-publications.view',
            'publishing-publications.create',
            'media.view',
        ]);

        $application = MembershipApplication::factory()->create();
        $file = UploadedFile::fake()->image('ktp.jpg');
        $key = $application->id.'/identity_document/ktp.jpg';
        Storage::disk('membership_documents')->put($key, $file->getContent());

        $document = $application->documents()->create([
            'document_type' => 'identity_document',
            'disk' => 'membership_documents',
            'object_key' => $key,
            'original_filename' => 'ktp.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12,
            'uploaded_at' => now(),
            'uploaded_by_context' => 'applicant',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.store'), [
                'type' => 'berita',
                'title' => 'Tidak boleh memakai dokumen anggota',
                'existing_cover_asset_id' => $document->id,
            ])
            ->assertSessionHasErrors('existing_cover_asset_id');

        $this->assertDatabaseCount('publications', 0);
        $this->assertTrue(Storage::disk('membership_documents')->exists($key));

        $library = $this->actingAs($staff)
            ->getJson(route('admin.files.json'))
            ->assertOk()
            ->json();

        $names = collect($library['data'] ?? $library)->pluck('name')->all();
        $this->assertNotContains('ktp.jpg', $names);
    }

    public function test_direct_cover_upload_is_copied_onto_the_publication(): void
    {
        $staff = $this->userWithPermissions([
            'publishing-publications.view',
            'publishing-publications.create',
            'media.create',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.store'), [
                'type' => 'berita',
                'title' => 'Unggah langsung',
                'new_cover' => UploadedFile::fake()->image('langsung.jpg', 800, 600),
            ])
            ->assertRedirect();

        $publication = Publication::query()->first();
        $this->assertTrue($publication?->hasMedia('publication-cover'));
        $this->assertDatabaseHas('files', ['name' => 'langsung']);
    }
}
