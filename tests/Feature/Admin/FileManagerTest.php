<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class FileManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Setup any roles if needed, or just regular user
        Storage::fake('public');
    }

    public function test_can_access_file_manager_index()
    {
        $this->withoutExceptionHandling();
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get(route('admin.files.index'));

        $response->assertStatus(200);
    }

    public function test_can_upload_files()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('test_image.jpg');

        $response = $this->actingAs($user)->post(route('admin.files.store'), [
            'files' => [$file],
        ]);

        $response->assertRedirect(route('admin.files.index'));
        $this->assertDatabaseHas('files', [
            'name' => 'test_image.jpg',
            'user_id' => $user->id,
        ]);

        // Assert Spatie Media
        $uploadedFile = File::first();
        $this->assertTrue($uploadedFile->hasMedia('default'));
    }

    public function test_can_delete_file()
    {
        $user = User::factory()->create();
        $fileModel = File::create(['user_id' => $user->id, 'name' => 'delete_me.jpg']);

        // Attach media
        $file = UploadedFile::fake()->image('delete_me.jpg');
        $fileModel->addMedia($file)->toMediaCollection('default');

        $response = $this->actingAs($user)->delete(route('admin.files.destroy', $fileModel));

        $response->assertRedirect();
        $this->assertDatabaseMissing('files', ['id' => $fileModel->id]);
    }
}
