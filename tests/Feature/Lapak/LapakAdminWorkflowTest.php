<?php

namespace Tests\Feature\Lapak;

use App\Enums\LapakListingAvailability;
use App\Enums\LapakModerationStatus;
use App\Enums\LapakPriceMode;
use App\Models\Lapak;
use App\Models\LapakCategory;
use App\Models\LapakListing;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class LapakAdminWorkflowTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_authorized_staff_can_create_and_view_a_draft_lapak(): void
    {
        $staff = $this->lapakStaff();
        $member = Member::factory()->create(['legal_name' => 'Siti Tani']);

        $this->actingAs($staff)
            ->post(route('admin.lapak.storefronts.store'), [
                'member_id' => $member->id,
                'name' => 'Lapak Siti',
                'contact_phone' => '081234567890',
            ])
            ->assertRedirect();

        $lapak = Lapak::query()->first();
        $this->assertNotNull($lapak);
        $this->assertSame($member->id, $lapak->member_id);
        $this->assertSame(LapakModerationStatus::Draft, $lapak->moderation_status);
        $this->assertSame('lapak-siti', $lapak->slug);
        $this->assertSame($staff->id, $lapak->created_by);

        $this->actingAs($staff)
            ->get(route('admin.lapak.storefronts.show', $lapak))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Lapak/Storefronts/Show')
                ->where('lapak.member_name', 'Siti Tani')
                ->where('lapak.moderation_status', 'draft')
                ->where('lapak.public_eligible', false));
    }

    public function test_second_lapak_for_the_same_member_is_rejected(): void
    {
        $staff = $this->lapakStaff();
        $member = Member::factory()->create();
        Lapak::factory()->create(['member_id' => $member->id]);

        $this->actingAs($staff)
            ->post(route('admin.lapak.storefronts.store'), [
                'member_id' => $member->id,
                'name' => 'Lapak kedua',
            ])
            ->assertSessionHasErrors('member_id');

        $this->assertDatabaseCount('lapaks', 1);
    }

    public function test_invalid_member_is_rejected(): void
    {
        $staff = $this->lapakStaff();

        $this->actingAs($staff)
            ->post(route('admin.lapak.storefronts.store'), [
                'member_id' => 999999,
                'name' => 'Tanpa anggota',
            ])
            ->assertSessionHasErrors('member_id');
    }

    public function test_lapak_cannot_be_published_without_a_contact_channel(): void
    {
        $staff = $this->lapakStaff();
        $lapak = Lapak::factory()->create([
            'contact_phone' => null,
            'contact_whatsapp' => null,
            'contact_email' => null,
        ]);

        $this->actingAs($staff)
            ->post(route('admin.lapak.storefronts.moderate', $lapak), [
                'status' => 'published',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(LapakModerationStatus::Draft, $lapak->fresh()->moderation_status);
    }

    public function test_authorized_staff_can_publish_and_suspend_a_lapak(): void
    {
        $staff = $this->lapakStaff();
        $lapak = Lapak::factory()->create(['contact_phone' => '0812']);

        $this->actingAs($staff)
            ->post(route('admin.lapak.storefronts.moderate', $lapak), [
                'status' => 'published',
            ])
            ->assertRedirect(route('admin.lapak.storefronts.show', $lapak));

        $this->assertSame(LapakModerationStatus::Published, $lapak->fresh()->moderation_status);
        $this->assertTrue($lapak->fresh()->isPublicEligible());

        $this->actingAs($staff)
            ->post(route('admin.lapak.storefronts.moderate', $lapak), [
                'status' => 'suspended',
                'note' => 'Verifikasi ulang',
            ])
            ->assertRedirect();

        $this->assertSame(LapakModerationStatus::Suspended, $lapak->fresh()->moderation_status);
        $this->assertFalse($lapak->fresh()->isPublicEligible());
    }

    public function test_reject_and_suspend_require_a_note(): void
    {
        $staff = $this->lapakStaff();
        $lapak = Lapak::factory()->pendingReview()->create();

        $this->actingAs($staff)
            ->post(route('admin.lapak.storefronts.moderate', $lapak), [
                'status' => 'rejected',
            ])
            ->assertSessionHas('error');

        $this->assertSame(LapakModerationStatus::PendingReview, $lapak->fresh()->moderation_status);
    }

    public function test_staff_can_create_update_and_moderate_a_listing(): void
    {
        $staff = $this->lapakStaff();
        $lapak = Lapak::factory()->published()->create();
        $category = LapakCategory::factory()->create(['name' => 'Benih']);

        $this->actingAs($staff)
            ->post(route('admin.lapak.listings.store'), [
                'lapak_id' => $lapak->id,
                'category_id' => $category->id,
                'title' => 'Benih jagung',
                'price_mode' => 'fixed',
                'price_amount' => 25000,
                'price_unit' => 'kg',
                'availability' => 'available',
            ])
            ->assertRedirect();

        $listing = LapakListing::query()->first();
        $this->assertSame(LapakModerationStatus::Draft, $listing->moderation_status);
        $this->assertSame($lapak->id, $listing->lapak_id);

        $this->actingAs($staff)
            ->put(route('admin.lapak.listings.update', $listing), [
                'category_id' => $category->id,
                'title' => 'Benih jagung hibrida',
                'slug' => $listing->slug,
                'price_mode' => 'from',
                'price_amount' => 20000,
                'price_unit' => 'kg',
                'availability' => 'available',
            ])
            ->assertRedirect();

        $this->assertSame('Benih jagung hibrida', $listing->fresh()->title);
        $this->assertSame(LapakPriceMode::From, $listing->fresh()->price_mode);

        $this->actingAs($staff)
            ->post(route('admin.lapak.listings.moderate', $listing), [
                'status' => 'published',
            ])
            ->assertRedirect();

        $this->assertTrue($listing->fresh()->isPublicEligible());
    }

    public function test_fixed_price_requires_an_amount_and_hidden_price_does_not(): void
    {
        $staff = $this->lapakStaff();
        $lapak = Lapak::factory()->create();
        $category = LapakCategory::factory()->create();

        $this->actingAs($staff)
            ->post(route('admin.lapak.listings.store'), [
                'lapak_id' => $lapak->id,
                'category_id' => $category->id,
                'title' => 'Tanpa harga',
                'price_mode' => 'fixed',
                'availability' => 'available',
            ])
            ->assertSessionHasErrors('price_amount');

        $this->actingAs($staff)
            ->post(route('admin.lapak.listings.store'), [
                'lapak_id' => $lapak->id,
                'category_id' => $category->id,
                'title' => 'Harga disembunyikan',
                'price_mode' => 'hidden',
                'price_amount' => 999,
                'availability' => 'available',
            ])
            ->assertRedirect();

        $listing = LapakListing::query()->where('title', 'Harga disembunyikan')->first();
        $this->assertSame(LapakPriceMode::Hidden, $listing->price_mode);
        $this->assertNull($listing->price_amount);
    }

    public function test_inactive_category_cannot_be_used_for_a_new_listing(): void
    {
        $staff = $this->lapakStaff();
        $lapak = Lapak::factory()->create();
        $category = LapakCategory::factory()->inactive()->create();

        $this->actingAs($staff)
            ->post(route('admin.lapak.listings.store'), [
                'lapak_id' => $lapak->id,
                'category_id' => $category->id,
                'title' => 'Kategori nonaktif',
                'price_mode' => 'negotiable',
                'availability' => 'available',
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_listing_slug_collision_inside_the_same_lapak_is_suffixed(): void
    {
        $staff = $this->lapakStaff();
        $lapak = Lapak::factory()->create();
        $category = LapakCategory::factory()->create();
        LapakListing::factory()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $category->id,
            'slug' => 'beras-organik',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.lapak.listings.store'), [
                'lapak_id' => $lapak->id,
                'category_id' => $category->id,
                'title' => 'Beras organik',
                'slug' => 'beras-organik',
                'price_mode' => 'negotiable',
                'availability' => 'available',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lapak_listings', [
            'lapak_id' => $lapak->id,
            'slug' => 'beras-organik-2',
        ]);
    }

    public function test_availability_can_change_without_changing_moderation(): void
    {
        $staff = $this->lapakStaff();
        $listing = LapakListing::factory()->published()->create([
            'lapak_id' => Lapak::factory()->published(),
        ]);

        $this->actingAs($staff)
            ->post(route('admin.lapak.listings.availability', $listing), [
                'availability' => 'unavailable',
            ])
            ->assertRedirect();

        $listing->refresh();
        $this->assertSame(LapakModerationStatus::Published, $listing->moderation_status);
        $this->assertSame(LapakListingAvailability::Unavailable, $listing->availability);
        $this->assertFalse($listing->isPublicEligible());
    }

    public function test_staff_can_create_and_deactivate_categories_without_deleting_listings(): void
    {
        $staff = $this->lapakStaff();

        $this->actingAs($staff)
            ->post(route('admin.lapak.categories.store'), [
                'name' => 'Hasil kebun',
            ])
            ->assertRedirect(route('admin.lapak.categories.index'));

        $category = LapakCategory::query()->first();
        $listing = LapakListing::factory()->create(['category_id' => $category->id]);

        $this->actingAs($staff)
            ->patch(route('admin.lapak.categories.toggle', $category))
            ->assertRedirect();

        $this->assertFalse($category->fresh()->is_active);
        $this->assertDatabaseHas('lapak_listings', [
            'id' => $listing->id,
            'category_id' => $category->id,
        ]);
        $this->assertFalse(Route::has('admin.lapak.categories.destroy'));
        $this->assertFalse(Route::has('admin.lapak.storefronts.destroy'));
    }

    public function test_admin_lists_filter_listings_by_lapak_and_status(): void
    {
        $staff = $this->lapakStaff();
        $target = Lapak::factory()->create(['name' => 'Lapak Target']);
        $other = Lapak::factory()->create();
        LapakListing::factory()->create(['lapak_id' => $target->id, 'title' => 'Produk target']);
        LapakListing::factory()->published()->create(['lapak_id' => $other->id, 'title' => 'Produk lain']);

        $this->actingAs($staff)
            ->get(route('admin.lapak.listings.index', [
                'lapak_id' => $target->id,
                'status' => 'draft',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Lapak/Listings/Index')
                ->has('listings.data', 1)
                ->where('listings.data.0.title', 'Produk target')
                ->where('listings.data.0.lapak_name', 'Lapak Target'));
    }

    public function test_updating_a_title_does_not_rewrite_an_existing_slug(): void
    {
        $staff = $this->lapakStaff();
        $lapak = Lapak::factory()->create(['slug' => 'toko-lama', 'name' => 'Toko Lama']);

        $this->actingAs($staff)
            ->put(route('admin.lapak.storefronts.update', $lapak), [
                'name' => 'Toko Baru',
                'slug' => 'toko-lama',
                'contact_phone' => '0812',
            ])
            ->assertRedirect();

        $this->assertSame('toko-lama', $lapak->fresh()->slug);
        $this->assertSame('Toko Baru', $lapak->fresh()->name);
    }

    /**
     * @return list<string>
     */
    private function lapakPermissions(): array
    {
        return ['lapak.view', 'lapak.manage', 'lapak.moderate'];
    }

    private function lapakStaff(): User
    {
        return $this->userWithPermissions($this->lapakPermissions());
    }
}
