<?php

namespace Tests\Unit\Lapak;

use App\Actions\Lapak\CreateLapak;
use App\Enums\LapakListingAvailability;
use App\Enums\LapakModerationStatus;
use App\Enums\LapakPriceMode;
use App\Exceptions\UniqueMemberLapakException;
use App\Models\Lapak;
use App\Models\LapakCategory;
use App\Models\LapakListing;
use App\Models\Member;
use App\Models\User;
use App\Support\Lapak\LapakViews;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LapakDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_own_only_one_lapak_at_the_application_layer(): void
    {
        $actor = User::factory()->create();
        $member = Member::factory()->create();

        $create = app(CreateLapak::class);
        $create->handle([
            'member_id' => $member->id,
            'name' => 'Lapak Satu',
            'slug' => 'lapak-satu',
        ], $actor);

        $this->expectException(UniqueMemberLapakException::class);

        $create->handle([
            'member_id' => $member->id,
            'name' => 'Lapak Dua',
            'slug' => 'lapak-dua',
        ], $actor);
    }

    public function test_database_unique_constraint_protects_one_lapak_per_member(): void
    {
        $member = Member::factory()->create();
        Lapak::factory()->create(['member_id' => $member->id]);

        $this->expectException(QueryException::class);

        Lapak::factory()->create(['member_id' => $member->id]);
    }

    public function test_one_lapak_can_own_many_listings_in_different_categories(): void
    {
        $lapak = Lapak::factory()->create();
        $first = LapakCategory::factory()->create(['name' => 'Benih']);
        $second = LapakCategory::factory()->create(['name' => 'Alat']);

        LapakListing::factory()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $first->id,
            'title' => 'Benih padi',
        ]);
        LapakListing::factory()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $second->id,
            'title' => 'Cangkul',
        ]);

        $this->assertSame(2, $lapak->listings()->count());
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $lapak->listings()->pluck('category_id')->all(),
        );
    }

    public function test_category_can_be_reused_across_lapak(): void
    {
        $category = LapakCategory::factory()->create();
        $first = Lapak::factory()->create();
        $second = Lapak::factory()->create();

        LapakListing::factory()->create(['lapak_id' => $first->id, 'category_id' => $category->id]);
        LapakListing::factory()->create(['lapak_id' => $second->id, 'category_id' => $category->id]);

        $this->assertSame(2, $category->listings()->count());
    }

    public function test_deactivating_a_category_keeps_listings(): void
    {
        $category = LapakCategory::factory()->create();
        $listing = LapakListing::factory()->create(['category_id' => $category->id]);

        $category->update(['is_active' => false]);

        $this->assertDatabaseHas('lapak_listings', [
            'id' => $listing->id,
            'category_id' => $category->id,
        ]);
        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_listing_slug_uniqueness_is_scoped_to_the_owning_lapak(): void
    {
        $first = Lapak::factory()->create();
        $second = Lapak::factory()->create();

        LapakListing::factory()->create(['lapak_id' => $first->id, 'slug' => 'beras']);
        LapakListing::factory()->create(['lapak_id' => $second->id, 'slug' => 'beras']);

        $this->assertSame(2, LapakListing::query()->where('slug', 'beras')->count());
    }

    public function test_draft_rejected_and_suspended_content_is_not_public_eligible(): void
    {
        $lapak = Lapak::factory()->published()->create();
        $draft = LapakListing::factory()->create(['lapak_id' => $lapak->id]);
        $rejected = LapakListing::factory()->create([
            'lapak_id' => $lapak->id,
            'moderation_status' => LapakModerationStatus::Rejected,
            'moderation_note' => 'Tidak lengkap',
        ]);
        $suspended = LapakListing::factory()->create([
            'lapak_id' => $lapak->id,
            'moderation_status' => LapakModerationStatus::Suspended,
            'moderation_note' => 'Ditangguhkan',
        ]);

        $this->assertFalse($draft->isPublicEligible());
        $this->assertFalse($rejected->isPublicEligible());
        $this->assertFalse($suspended->isPublicEligible());
        $this->assertFalse(LapakListing::query()->publicEligible()->whereKey($draft->id)->exists());
    }

    public function test_published_available_listing_on_published_lapak_is_public_eligible(): void
    {
        $listing = LapakListing::factory()->published()->create([
            'lapak_id' => Lapak::factory()->published(),
        ]);

        $this->assertTrue($listing->isPublicEligible());
        $this->assertTrue(LapakListing::query()->publicEligible()->whereKey($listing->id)->exists());
        $this->assertNull(LapakViews::ineligibleReason($listing));
    }

    public function test_availability_is_distinct_from_moderation(): void
    {
        $listing = LapakListing::factory()->published()->unavailable()->create([
            'lapak_id' => Lapak::factory()->published(),
        ]);

        $this->assertSame(LapakModerationStatus::Published, $listing->moderation_status);
        $this->assertSame(LapakListingAvailability::Unavailable, $listing->availability);
        $this->assertFalse($listing->isPublicEligible());
        $this->assertStringContainsString('tidak tersedia', (string) LapakViews::ineligibleReason($listing));
    }

    public function test_published_listing_is_not_public_eligible_when_lapak_is_not_published(): void
    {
        $listing = LapakListing::factory()->published()->create([
            'lapak_id' => Lapak::factory()->create(),
        ]);

        $this->assertFalse($listing->isPublicEligible());
        $this->assertStringContainsString('Lapak', (string) LapakViews::ineligibleReason($listing));
    }

    public function test_price_modes_do_not_require_commerce_abstractions(): void
    {
        $fixed = LapakListing::factory()->make([
            'price_mode' => LapakPriceMode::Fixed,
            'price_amount' => 15000,
            'price_unit' => 'kg',
        ]);
        $hidden = LapakListing::factory()->make([
            'price_mode' => LapakPriceMode::Hidden,
            'price_amount' => null,
        ]);
        $negotiable = LapakListing::factory()->negotiable()->make();

        $this->assertSame('Rp 15.000 / kg', LapakViews::priceLabel($fixed));
        $this->assertSame('Harga tidak ditampilkan', LapakViews::priceLabel($hidden));
        $this->assertSame('Nego / hubungi penjual', LapakViews::priceLabel($negotiable));
        $this->assertTrue(LapakPriceMode::Fixed->requiresAmount());
        $this->assertFalse(LapakPriceMode::Negotiable->requiresAmount());
    }
}
