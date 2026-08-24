<?php

namespace Tests\Feature\Lapak;

use App\Models\File;
use App\Models\Lapak;
use App\Models\LapakCategory;
use App\Models\LapakListing;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class OwnerLapakMediaSecurityTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->syncAclFromConfig();
    }

    public function test_owner_can_upload_own_cover_but_cannot_attach_library_or_browse_files(): void
    {
        $staff = $this->userWithPermissions(['media.view', 'media.delete', 'lapak.manage']);
        $foreign = File::query()->create(['user_id' => $staff->id, 'name' => 'rahasia.jpg']);
        $foreign->addMedia(UploadedFile::fake()->image('rahasia.jpg', 800, 400))
            ->toMediaCollection('default');

        $owner = User::factory()->create();
        $member = Member::factory()->linked($owner)->create();

        $this->actingAs($owner)
            ->get(route('admin.files.index'))
            ->assertForbidden();
        $this->actingAs($owner)
            ->get(route('admin.files.json'))
            ->assertForbidden();
        $this->actingAs($owner)
            ->delete(route('admin.files.destroy', $foreign))
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('account.lapak.store'), [
                'name' => 'Lapak tanpa pustaka',
                'existing_cover_asset_id' => $foreign->id,
            ])
            ->assertRedirect(route('account.lapak.index'));

        $lapak = $member->fresh()->lapak;
        $this->assertNotNull($lapak);
        $this->assertFalse($lapak->hasMedia('lapak-cover'));

        $this->actingAs($owner)
            ->post(route('account.lapak.update', $lapak), [
                '_method' => 'PUT',
                'name' => 'Lapak dengan unggahan',
                'new_cover' => UploadedFile::fake()->image('sampul-saya.jpg', 800, 400),
            ])
            ->assertRedirect(route('account.lapak.index'));

        $this->assertTrue($lapak->fresh()->hasMedia('lapak-cover'));
        $this->assertTrue($foreign->fresh()->hasMedia('default'));
    }

    public function test_owner_cannot_attach_or_destroy_unrelated_listing_media(): void
    {
        $staff = $this->userWithPermissions(['media.view']);
        $library = File::query()->create(['user_id' => $staff->id, 'name' => 'staf.jpg']);
        $library->addMedia(UploadedFile::fake()->image('staf.jpg'))->toMediaCollection('default');

        $owner = User::factory()->create();
        $member = Member::factory()->linked($owner)->create();
        $lapak = Lapak::factory()->create(['member_id' => $member->id]);
        $category = LapakCategory::factory()->create();

        $otherOwner = User::factory()->create();
        $otherMember = Member::factory()->linked($otherOwner)->create();
        $otherLapak = Lapak::factory()->create(['member_id' => $otherMember->id]);
        $otherListing = LapakListing::factory()->create(['lapak_id' => $otherLapak->id]);
        $otherAsset = File::query()->create(['user_id' => $otherOwner->id, 'name' => 'orang-lain.jpg']);
        $otherMedia = $otherAsset->addMedia(UploadedFile::fake()->image('orang-lain.jpg'))
            ->toMediaCollection('default');
        $otherMedia->copy($otherListing, 'listing-images');
        $foreignListingMediaId = $otherListing->fresh()->getFirstMedia('listing-images')?->id;

        $this->actingAs($owner)
            ->post(route('account.lapak.listings.store'), [
                'lapak_id' => $otherLapak->id,
                'category_id' => $category->id,
                'title' => 'Produk unggahan',
                'price_mode' => 'negotiable',
                'availability' => 'available',
                'existing_image_asset_ids' => [$library->id],
                'new_image' => UploadedFile::fake()->image('produk-saya.jpg'),
            ])
            ->assertRedirect(route('account.lapak.listings.index'));

        $listing = LapakListing::query()->where('title', 'Produk unggahan')->first();
        $this->assertNotNull($listing);
        $this->assertSame($lapak->id, $listing->lapak_id);
        $this->assertSame(1, $listing->getMedia('listing-images')->count());

        $this->actingAs($owner)
            ->post(route('account.lapak.listings.update', $listing), [
                '_method' => 'PUT',
                'category_id' => $category->id,
                'title' => 'Produk unggahan',
                'price_mode' => 'negotiable',
                'availability' => 'available',
                'existing_image_asset_ids' => [$library->id],
                'remove_media_ids' => [$foreignListingMediaId],
            ])
            ->assertRedirect();

        $this->assertSame(1, $otherListing->fresh()->getMedia('listing-images')->count());
        $this->assertSame(1, $listing->fresh()->getMedia('listing-images')->count());
        $this->assertFalse($owner->fresh()->can('media.view'));
        $this->assertFalse($owner->fresh()->can('media.delete'));
        $this->assertFalse($owner->fresh()->can('cms.view'));
    }
}
