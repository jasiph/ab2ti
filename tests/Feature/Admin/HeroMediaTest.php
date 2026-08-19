<?php

namespace Tests\Feature\Admin;

use App\Models\File;
use App\Models\Hero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class HeroMediaTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_hero_registers_hero_backgrounds_collection_not_conversions(): void
    {
        $hero = Hero::query()->create([
            'title' => 'Landing',
            'is_active' => true,
        ]);

        $names = $hero->getRegisteredMediaCollections()->map->name->all();

        $this->assertContains('hero-backgrounds', $names);
    }

    public function test_hero_can_store_multiple_background_images(): void
    {
        $hero = Hero::query()->create([
            'title' => 'Landing',
            'is_active' => true,
        ]);

        $hero->addMedia(UploadedFile::fake()->image('one.jpg'))->toMediaCollection('hero-backgrounds');
        $hero->addMedia(UploadedFile::fake()->image('two.jpg'))->toMediaCollection('hero-backgrounds');

        $this->assertCount(2, $hero->fresh()->getMedia('hero-backgrounds'));
        $this->assertNotSame('', $hero->getFirstMediaUrl('hero-backgrounds', 'thumb'));
        $this->assertNotSame('', $hero->getFirstMediaUrl('hero-backgrounds', 'large'));
    }

    public function test_removing_hero_media_does_not_delete_unrelated_files(): void
    {
        $actor = $this->userWithPermissions(['heroes.update']);

        $keep = File::query()->create(['user_id' => $actor->id, 'name' => 'keep.jpg']);
        $keep->addMedia(UploadedFile::fake()->image('keep.jpg'))->toMediaCollection('default');
        $keepMediaId = $keep->getFirstMedia('default')?->id;

        $hero = Hero::query()->create([
            'title' => 'Landing',
            'is_active' => true,
        ]);
        $hero->addMedia(UploadedFile::fake()->image('bg.jpg'))->toMediaCollection('hero-backgrounds');
        $heroMediaId = $hero->getFirstMedia('hero-backgrounds')?->id;

        $this->actingAs($actor)
            ->put(route('admin.heroes.update', $hero), [
                'title' => 'Landing',
                'is_active' => true,
                'remove_media_ids' => [$heroMediaId, $keepMediaId],
            ])
            ->assertRedirect(route('admin.heroes.index'));

        $this->assertFalse($hero->fresh()->hasMedia('hero-backgrounds'));
        $this->assertTrue($keep->fresh()->hasMedia('default'));
        $this->assertDatabaseHas('media', ['id' => $keepMediaId]);
    }
}
