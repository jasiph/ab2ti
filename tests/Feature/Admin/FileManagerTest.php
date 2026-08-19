<?php

namespace Tests\Feature\Admin;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class FileManagerTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->user = $this->userWithPermissions([
            'media.view',
            'media.create',
            'media.update',
            'media.delete',
        ]);
    }

    public function test_can_access_file_manager_index()
    {
        $response = $this->actingAs($this->user)->get(route('admin.files.index'));

        $response->assertStatus(200);
    }

    public function test_can_upload_files()
    {
        $file = UploadedFile::fake()->image('test_image.jpg');

        $response = $this->actingAs($this->user)->post(route('admin.files.store'), [
            'files' => [$file],
        ]);

        $response->assertRedirect(route('admin.files.index'));
        $this->assertDatabaseHas('files', [
            'name' => 'test_image.jpg',
            'user_id' => $this->user->id,
        ]);

        $uploadedFile = File::first();
        $this->assertTrue($uploadedFile->hasMedia('default'));
    }

    public function test_can_soft_delete_file()
    {
        $fileModel = File::create(['user_id' => $this->user->id, 'name' => 'delete_me.jpg']);

        $file = UploadedFile::fake()->image('delete_me.jpg');
        $fileModel->addMedia($file)->toMediaCollection('default');

        $response = $this->actingAs($this->user)->delete(route('admin.files.destroy', $fileModel));

        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('files', ['id' => $fileModel->id]);
        $this->assertSoftDeleted('files', ['id' => $fileModel->id]);
        $this->assertTrue($fileModel->fresh()->hasMedia('default'));
    }

    public function test_can_restore_soft_deleted_file()
    {
        $fileModel = File::create(['user_id' => $this->user->id, 'name' => 'restore_me.jpg']);

        $file = UploadedFile::fake()->image('restore_me.jpg');
        $fileModel->addMedia($file)->toMediaCollection('default');

        $fileModel->delete();
        $this->assertSoftDeleted('files', ['id' => $fileModel->id]);

        $response = $this->actingAs($this->user)->post(route('admin.files.restore', $fileModel->id));

        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('files', ['id' => $fileModel->id, 'deleted_at' => null]);
        $this->assertTrue($fileModel->fresh()->hasMedia('default'));
        $this->assertSame(1, $fileModel->fresh()->getMedia('default')->count());
    }

    public function test_restore_returns_not_found_for_missing_or_live_files()
    {
        $live = File::create(['user_id' => $this->user->id, 'name' => 'live.jpg']);

        $this->actingAs($this->user)
            ->post(route('admin.files.restore', $live->id))
            ->assertNotFound();

        $this->actingAs($this->user)
            ->post(route('admin.files.restore', 999999))
            ->assertNotFound();
    }

    public function test_can_force_delete_file()
    {
        $fileModel = File::create(['user_id' => $this->user->id, 'name' => 'gone_forever.jpg']);

        $file = UploadedFile::fake()->image('gone_forever.jpg');
        $fileModel->addMedia($file)->toMediaCollection('default');
        $mediaId = $fileModel->getFirstMedia('default')?->id;

        $fileModel->delete();

        $response = $this->actingAs($this->user)->delete(route('admin.files.force-delete', $fileModel->id));

        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('files', ['id' => $fileModel->id]);
        $this->assertDatabaseMissing('media', ['id' => $mediaId]);
    }

    public function test_force_delete_does_not_remove_unrelated_files()
    {
        $keep = File::create(['user_id' => $this->user->id, 'name' => 'keep.jpg']);
        $keep->addMedia(UploadedFile::fake()->image('keep.jpg'))->toMediaCollection('default');

        $gone = File::create(['user_id' => $this->user->id, 'name' => 'gone.jpg']);
        $gone->addMedia(UploadedFile::fake()->image('gone.jpg'))->toMediaCollection('default');
        $gone->delete();

        $this->actingAs($this->user)->delete(route('admin.files.force-delete', $gone->id));

        $this->assertDatabaseHas('files', ['id' => $keep->id, 'deleted_at' => null]);
        $this->assertTrue($keep->fresh()->hasMedia('default'));
        $this->assertDatabaseMissing('files', ['id' => $gone->id]);
    }

    public function test_force_delete_of_a_live_file_is_not_found()
    {
        $live = File::create(['user_id' => $this->user->id, 'name' => 'live.jpg']);

        $this->actingAs($this->user)
            ->delete(route('admin.files.force-delete', $live->id))
            ->assertNotFound();
    }
}
