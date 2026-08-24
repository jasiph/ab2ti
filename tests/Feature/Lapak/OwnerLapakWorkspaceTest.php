<?php

namespace Tests\Feature\Lapak;

use App\Enums\LapakListingAvailability;
use App\Enums\LapakModerationStatus;
use App\Models\Lapak;
use App\Models\LapakCategory;
use App\Models\LapakListing;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class OwnerLapakWorkspaceTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_linked_owner_can_create_edit_and_submit_own_lapak(): void
    {
        [$owner, $member] = $this->linkedOwner();

        $this->actingAs($owner)
            ->get(route('account.lapak.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Account/Lapak/Empty')
                ->where('auth.linked_to_member', true));

        $this->actingAs($owner)
            ->post(route('account.lapak.store'), [
                'member_id' => Member::factory()->create()->id,
                'name' => 'Lapak Siti',
                'contact_phone' => '081234567890',
            ])
            ->assertRedirect(route('account.lapak.index'));

        $lapak = $member->fresh()->lapak;
        $this->assertNotNull($lapak);
        $this->assertSame($member->id, $lapak->member_id);
        $this->assertSame(LapakModerationStatus::Draft, $lapak->moderation_status);
        $this->assertSame('081234567890', $lapak->contact_phone);
        $this->assertNull($lapak->contact_email);
        $this->assertNotSame($member->email, $lapak->contact_email);

        $this->actingAs($owner)
            ->post(route('account.lapak.submit', $lapak))
            ->assertRedirect(route('account.lapak.index'));

        $this->assertSame(LapakModerationStatus::PendingReview, $lapak->fresh()->moderation_status);

        $this->actingAs($owner)
            ->post(route('account.lapak.submit', $lapak))
            ->assertForbidden();
        $this->assertSame(LapakModerationStatus::PendingReview, $lapak->fresh()->moderation_status);
    }

    public function test_pending_review_locks_content_but_allows_availability(): void
    {
        [$owner] = $this->linkedOwner();
        $lapak = Lapak::factory()->pendingReview()->create([
            'member_id' => $owner->linkedMember()->id,
            'name' => 'Lapak pending',
        ]);
        $listing = LapakListing::factory()->create([
            'lapak_id' => $lapak->id,
            'moderation_status' => LapakModerationStatus::PendingReview,
        ]);

        $this->actingAs($owner)
            ->get(route('account.lapak.edit', $lapak))
            ->assertForbidden();
        $this->actingAs($owner)
            ->put(route('account.lapak.update', $lapak), ['name' => 'Diubah diam-diam'])
            ->assertForbidden();
        $this->actingAs($owner)
            ->post(route('account.lapak.submit', $lapak))
            ->assertForbidden();
        $this->actingAs($owner)
            ->get(route('account.lapak.listings.edit', $listing))
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('account.lapak.listings.availability', $listing), [
                'availability' => 'unavailable',
            ])
            ->assertRedirect();

        $this->assertSame(LapakListingAvailability::Unavailable, $listing->fresh()->availability);
        $this->assertSame(LapakModerationStatus::PendingReview, $listing->fresh()->moderation_status);
        $this->assertSame('Lapak pending', $lapak->fresh()->name);
    }

    public function test_rejected_can_be_corrected_and_resubmitted(): void
    {
        [$owner] = $this->linkedOwner();
        $lapak = Lapak::factory()->rejected()->create([
            'member_id' => $owner->linkedMember()->id,
            'name' => 'Lapak ditolak',
        ]);

        $this->actingAs($owner)
            ->get(route('account.lapak.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Account/Lapak/Overview')
                ->where('lapak.moderation_status', 'rejected')
                ->where('lapak.can_edit', true)
                ->where('lapak.can_submit', true));

        $this->actingAs($owner)
            ->put(route('account.lapak.update', $lapak), [
                'name' => 'Lapak diperbaiki',
                'contact_phone' => '0812',
            ])
            ->assertRedirect(route('account.lapak.index'));

        $this->actingAs($owner)
            ->post(route('account.lapak.submit', $lapak))
            ->assertRedirect();

        $this->assertSame(LapakModerationStatus::PendingReview, $lapak->fresh()->moderation_status);
        $this->assertSame('Lapak diperbaiki', $lapak->fresh()->name);
    }

    public function test_published_content_edit_returns_to_pending_review_while_availability_stays_independent(): void
    {
        [$owner] = $this->linkedOwner();
        $lapak = Lapak::factory()->published()->create([
            'member_id' => $owner->linkedMember()->id,
            'name' => 'Lapak tayang',
            'contact_phone' => '0812',
        ]);
        $listing = LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'title' => 'Produk tayang',
        ]);

        $this->actingAs($owner)
            ->put(route('account.lapak.update', $lapak), [
                'name' => 'Lapak diubah',
                'contact_phone' => '0812',
            ])
            ->assertRedirect(route('account.lapak.index'));

        $this->assertSame(LapakModerationStatus::PendingReview, $lapak->fresh()->moderation_status);
        $this->assertSame('Lapak diubah', $lapak->fresh()->name);

        $this->actingAs($owner)
            ->post(route('account.lapak.listings.availability', $listing), [
                'availability' => 'unavailable',
            ])
            ->assertRedirect();

        $this->assertSame(LapakListingAvailability::Unavailable, $listing->fresh()->availability);
        $this->assertSame(LapakModerationStatus::Published, $listing->fresh()->moderation_status);

        $this->actingAs($owner)
            ->put(route('account.lapak.listings.update', $listing), [
                'category_id' => $listing->category_id,
                'title' => 'Produk diubah',
                'price_mode' => 'negotiable',
                'availability' => 'unavailable',
            ])
            ->assertRedirect();

        $this->assertSame(LapakModerationStatus::PendingReview, $listing->fresh()->moderation_status);
        $this->assertSame('Produk diubah', $listing->fresh()->title);
        $this->assertSame($listing->slug, $listing->fresh()->slug);
    }

    public function test_suspended_cannot_self_publish_or_edit(): void
    {
        [$owner] = $this->linkedOwner();
        $lapak = Lapak::factory()->suspended()->create([
            'member_id' => $owner->linkedMember()->id,
        ]);
        $listing = LapakListing::factory()->create([
            'lapak_id' => $lapak->id,
            'moderation_status' => LapakModerationStatus::Suspended,
            'moderation_note' => 'Ditangguhkan',
        ]);

        $this->actingAs($owner)
            ->get(route('account.lapak.edit', $lapak))
            ->assertForbidden();
        $this->actingAs($owner)
            ->post(route('account.lapak.submit', $lapak))
            ->assertForbidden();
        $this->actingAs($owner)
            ->post(route('admin.lapak.storefronts.moderate', $lapak), [
                'status' => 'published',
            ])
            ->assertForbidden();
        $this->actingAs($owner)
            ->post(route('account.lapak.listings.availability', $listing), [
                'availability' => 'available',
            ])
            ->assertForbidden();

        $this->assertSame(LapakModerationStatus::Suspended, $lapak->fresh()->moderation_status);
    }

    public function test_owner_cannot_access_another_members_lapak_or_listings(): void
    {
        [$ownerA] = $this->linkedOwner();
        [$ownerB, $memberB] = $this->linkedOwner();
        $lapakB = Lapak::factory()->create(['member_id' => $memberB->id, 'name' => 'Milik B']);
        $listingB = LapakListing::factory()->create(['lapak_id' => $lapakB->id]);
        $category = LapakCategory::factory()->create();

        $this->actingAs($ownerA)
            ->get(route('account.lapak.edit', $lapakB))
            ->assertForbidden();
        $this->actingAs($ownerA)
            ->put(route('account.lapak.update', $lapakB), ['name' => 'Direbut'])
            ->assertForbidden();
        $this->actingAs($ownerA)
            ->get(route('account.lapak.listings.edit', $listingB))
            ->assertForbidden();
        $this->actingAs($ownerA)
            ->put(route('account.lapak.listings.update', $listingB), [
                'category_id' => $category->id,
                'title' => 'Direbut',
                'price_mode' => 'negotiable',
                'availability' => 'available',
            ])
            ->assertForbidden();
        $this->actingAs($ownerA)
            ->post(route('account.lapak.submit', $lapakB))
            ->assertForbidden();
        $this->actingAs($ownerA)
            ->post(route('account.lapak.listings.submit', $listingB))
            ->assertForbidden();
        $this->actingAs($ownerA)
            ->post(route('account.lapak.listings.availability', $listingB), [
                'availability' => 'unavailable',
            ])
            ->assertForbidden();

        Lapak::factory()->create(['member_id' => $ownerA->linkedMember()->id]);

        $this->actingAs($ownerA)
            ->post(route('account.lapak.listings.store'), [
                'lapak_id' => $lapakB->id,
                'category_id' => $category->id,
                'title' => 'Nemplet di B',
                'price_mode' => 'negotiable',
                'availability' => 'available',
            ])
            ->assertRedirect(route('account.lapak.listings.index'));

        $created = LapakListing::query()->where('title', 'Nemplet di B')->first();
        $this->assertNotNull($created);
        $this->assertSame($ownerA->linkedMember()->lapak->id, $created->lapak_id);
        $this->assertNotSame($lapakB->id, $created->lapak_id);
        $this->assertSame('Milik B', $lapakB->fresh()->name);
    }

    public function test_owner_cannot_use_admin_lapak_routes_or_alter_categories(): void
    {
        [$owner] = $this->linkedOwner();
        $lapak = Lapak::factory()->create(['member_id' => $owner->linkedMember()->id]);
        $category = LapakCategory::factory()->create();

        $this->actingAs($owner)
            ->get(route('admin.lapak.storefronts.index'))
            ->assertForbidden();
        $this->actingAs($owner)
            ->post(route('admin.lapak.storefronts.moderate', $lapak), [
                'status' => 'published',
            ])
            ->assertForbidden();
        $this->actingAs($owner)
            ->post(route('admin.lapak.categories.store'), [
                'name' => 'Kategori sendiri',
            ])
            ->assertForbidden();
        $this->actingAs($owner)
            ->patch(route('admin.lapak.categories.toggle', $category))
            ->assertForbidden();

        $this->assertFalse($owner->fresh()->can('lapak.view'));
        $this->assertFalse($owner->fresh()->can('lapak.manage'));
        $this->assertFalse($owner->fresh()->can('lapak.moderate'));
        $this->assertFalse(Role::query()->where('name', 'member')->exists());
        $this->assertSame($category->name, $category->fresh()->name);
    }

    public function test_owner_cannot_select_inactive_category_except_current(): void
    {
        [$owner] = $this->linkedOwner();
        $lapak = Lapak::factory()->create(['member_id' => $owner->linkedMember()->id]);
        $active = LapakCategory::factory()->create(['is_active' => true]);
        $inactive = LapakCategory::factory()->create(['is_active' => false]);
        $currentInactive = LapakCategory::factory()->create(['is_active' => false]);
        $listing = LapakListing::factory()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $currentInactive->id,
        ]);

        $this->actingAs($owner)
            ->post(route('account.lapak.listings.store'), [
                'category_id' => $inactive->id,
                'title' => 'Tidak boleh',
                'price_mode' => 'negotiable',
                'availability' => 'available',
            ])
            ->assertSessionHasErrors('category_id');

        $this->actingAs($owner)
            ->put(route('account.lapak.listings.update', $listing), [
                'category_id' => $inactive->id,
                'title' => $listing->title,
                'price_mode' => 'negotiable',
                'availability' => 'available',
            ])
            ->assertSessionHasErrors('category_id');

        $this->actingAs($owner)
            ->put(route('account.lapak.listings.update', $listing), [
                'category_id' => $currentInactive->id,
                'title' => 'Tetap kategori lama',
                'price_mode' => 'negotiable',
                'availability' => 'available',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->put(route('account.lapak.listings.update', $listing), [
                'category_id' => $active->id,
                'title' => 'Pindah aktif',
                'price_mode' => 'negotiable',
                'availability' => 'available',
            ])
            ->assertRedirect();

        $this->assertSame($active->id, $listing->fresh()->category_id);
    }

    public function test_linked_member_still_cannot_create_a_second_lapak(): void
    {
        [$owner, $member] = $this->linkedOwner();
        Lapak::factory()->create(['member_id' => $member->id]);

        $this->actingAs($owner)
            ->post(route('account.lapak.store'), [
                'name' => 'Lapak kedua',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('lapaks', 1);
    }

    public function test_staff_cannot_use_member_workspace(): void
    {
        $staff = $this->userWithPermissions(['lapak.view', 'lapak.manage']);
        $staff->assignRole(
            Role::findOrCreate('membership-admin', 'web'),
        );

        $this->actingAs($staff)
            ->get(route('account.lapak.index'))
            ->assertForbidden();
    }

    public function test_public_owner_and_admin_lapak_surfaces_remain_separate(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('lapak.index'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('lapak.show'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('account.lapak.index'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.lapak.storefronts.index'));

        $this->get('/lapak')->assertOk();
        $this->get('/akun/lapak')->assertRedirect();
        $this->get('/admin/lapak/storefronts')->assertRedirect();
    }

    /**
     * @return array{0: User, 1: Member}
     */
    private function linkedOwner(): array
    {
        $this->syncAclFromConfig();
        $account = User::factory()->create();
        $member = Member::factory()->linked($account)->create();

        return [$account, $member];
    }
}
