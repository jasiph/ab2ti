<?php

namespace Tests\Feature\Knowledge;

use App\Models\File;
use App\Models\KnowledgeArticle;
use App\Models\MembershipApplication;
use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class KnowledgeMediaTest extends TestCase
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
            'knowledge-articles.view',
            'knowledge-articles.create',
            'knowledge-articles.update',
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
            ->post(route('admin.knowledge.articles.store'), [
                'title' => 'Dengan sampul',
                'existing_cover_asset_id' => $asset->id,
            ])
            ->assertRedirect();

        $article = KnowledgeArticle::query()->first();
        $this->assertTrue($article?->hasMedia('knowledge-cover'));

        $this->actingAs($staff)
            ->get(route('admin.knowledge.articles.edit', $article))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Knowledge/Articles/Form')
                ->where('article.cover_url', fn ($url) => is_string($url) && $url !== '')
                ->missing('article.disk')
                ->missing('article.object_key')
                ->missing('article.media'));
    }

    public function test_invalid_and_unknown_media_ids_are_rejected(): void
    {
        $staff = $this->userWithPermissions([
            'knowledge-articles.view',
            'knowledge-articles.create',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.knowledge.articles.store'), [
                'title' => 'Media tidak valid',
                'existing_cover_asset_id' => 999999,
            ])
            ->assertSessionHasErrors('existing_cover_asset_id');

        $this->assertDatabaseCount('knowledge_articles', 0);
    }

    public function test_membership_private_documents_cannot_be_selected_as_knowledge_media(): void
    {
        $staff = $this->userWithPermissions([
            'knowledge-articles.view',
            'knowledge-articles.create',
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
            ->post(route('admin.knowledge.articles.store'), [
                'title' => 'Tidak boleh memakai dokumen anggota',
                'existing_cover_asset_id' => $document->id,
            ])
            ->assertSessionHasErrors('existing_cover_asset_id');

        $this->assertDatabaseCount('knowledge_articles', 0);
        $this->assertTrue(Storage::disk('membership_documents')->exists($key));
    }

    public function test_direct_cover_upload_is_copied_onto_the_article(): void
    {
        $staff = $this->userWithPermissions([
            'knowledge-articles.view',
            'knowledge-articles.create',
            'media.create',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.knowledge.articles.store'), [
                'title' => 'Unggah langsung',
                'new_cover' => UploadedFile::fake()->image('langsung.jpg', 800, 600),
            ])
            ->assertRedirect();

        $article = KnowledgeArticle::query()->first();
        $this->assertTrue($article?->hasMedia('knowledge-cover'));
        $this->assertDatabaseHas('files', ['name' => 'langsung']);
    }

    public function test_publishing_media_behavior_does_not_regress(): void
    {
        $staff = $this->userWithPermissions([
            'publishing-publications.view',
            'publishing-publications.create',
            'media.create',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.store'), [
                'type' => 'berita',
                'title' => 'Publikasi tetap',
                'new_cover' => UploadedFile::fake()->image('pub.jpg', 800, 600),
            ])
            ->assertRedirect();

        $publication = Publication::query()->first();
        $this->assertTrue($publication?->hasMedia('publication-cover'));
        $this->assertFalse($publication->hasMedia('knowledge-cover'));
    }
}
