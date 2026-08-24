<?php

namespace Tests\Feature\Lapak;

use App\Models\File;
use App\Models\Lapak;
use App\Models\LapakCategory;
use App\Models\LapakListing;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class LapakMediaTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_staff_can_attach_library_cover_to_a_lapak(): void
    {
        $staff = $this->userWithPermissions(['lapak.view', 'lapak.manage', 'media.view']);
        $member = Member::factory()->create();
        $asset = File::query()->create([
            'user_id' => $staff->id,
            'name' => 'sampul-lapak.jpg',
        ]);
        $asset->addMedia(UploadedFile::fake()->image('sampul-lapak.jpg', 800, 400))
            ->toMediaCollection('default');

        $this->actingAs($staff)
            ->post(route('admin.lapak.storefronts.store'), [
                'member_id' => $member->id,
                'name' => 'Lapak bersampul',
                'existing_cover_asset_id' => $asset->id,
            ])
            ->assertRedirect();

        $lapak = Lapak::query()->first();
        $this->assertTrue($lapak?->hasMedia('lapak-cover'));

        $this->actingAs($staff)
            ->get(route('admin.lapak.storefronts.edit', $lapak))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Lapak/Storefronts/Form')
                ->where('lapak.cover_url', fn ($url) => is_string($url) && $url !== '')
                ->missing('lapak.disk'));
    }

    public function test_listing_can_receive_multiple_library_images(): void
    {
        $staff = $this->userWithPermissions(['lapak.view', 'lapak.manage', 'media.view']);
        $lapak = Lapak::factory()->create();
        $category = LapakCategory::factory()->create();

        $first = File::query()->create(['user_id' => $staff->id, 'name' => 'satu.jpg']);
        $first->addMedia(UploadedFile::fake()->image('satu.jpg'))->toMediaCollection('default');
        $second = File::query()->create(['user_id' => $staff->id, 'name' => 'dua.jpg']);
        $second->addMedia(UploadedFile::fake()->image('dua.jpg'))->toMediaCollection('default');

        $this->actingAs($staff)
            ->post(route('admin.lapak.listings.store'), [
                'lapak_id' => $lapak->id,
                'category_id' => $category->id,
                'title' => 'Produk bergambar',
                'price_mode' => 'negotiable',
                'availability' => 'available',
                'existing_image_asset_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect();

        $listing = LapakListing::query()->first();
        $this->assertSame(2, $listing?->getMedia('listing-images')->count());
    }

    public function test_unknown_media_ids_are_rejected(): void
    {
        $staff = $this->userWithPermissions(['lapak.view', 'lapak.manage']);
        $member = Member::factory()->create();

        $this->actingAs($staff)
            ->post(route('admin.lapak.storefronts.store'), [
                'member_id' => $member->id,
                'name' => 'Media tidak valid',
                'existing_cover_asset_id' => 999999,
            ])
            ->assertSessionHasErrors('existing_cover_asset_id');
    }
}
