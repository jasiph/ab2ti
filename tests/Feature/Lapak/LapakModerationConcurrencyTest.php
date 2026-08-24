<?php

namespace Tests\Feature\Lapak;

use App\Actions\Lapak\SubmitLapakForReview;
use App\Actions\Lapak\SubmitLapakListingForReview;
use App\Actions\Lapak\UpdateLapak;
use App\Actions\Lapak\UpdateLapakListing;
use App\Actions\Lapak\UpdateLapakListingAvailability;
use App\Enums\LapakListingAvailability;
use App\Enums\LapakModerationStatus;
use App\Exceptions\InvalidLapakTransition;
use App\Models\Lapak;
use App\Models\LapakListing;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LapakModerationConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_owner_save_is_rejected_after_admin_suspends(): void
    {
        [$owner, $lapak, $listing] = $this->publishedOwnerResources();

        Lapak::query()->whereKey($lapak->id)->update([
            'moderation_status' => LapakModerationStatus::Suspended->value,
        ]);
        LapakListing::query()->whereKey($listing->id)->update([
            'moderation_status' => LapakModerationStatus::Suspended->value,
        ]);

        $this->expectException(InvalidLapakTransition::class);

        app(UpdateLapak::class)->handle($lapak, [
            'name' => 'Disimpan stale',
            'slug' => $lapak->slug,
            'contact_phone' => '081234567890',
        ], $owner);
    }

    public function test_stale_owner_listing_save_is_rejected_after_pending_lock(): void
    {
        [$owner, $lapak, $listing] = $this->publishedOwnerResources();

        LapakListing::query()->whereKey($listing->id)->update([
            'moderation_status' => LapakModerationStatus::PendingReview->value,
        ]);

        try {
            app(UpdateLapakListing::class)->handle($listing, [
                'category_id' => $listing->category_id,
                'title' => 'Disimpan stale',
                'slug' => $listing->slug,
                'price_mode' => $listing->price_mode->value,
                'price_amount' => $listing->price_amount,
                'price_unit' => $listing->price_unit,
                'availability' => LapakListingAvailability::Available->value,
            ], $owner);
            $this->fail('Stale owner listing save should fail after pending_review.');
        } catch (InvalidLapakTransition) {
            $this->assertSame('Produk tayang', $listing->fresh()->title);
            $this->assertSame(LapakModerationStatus::PendingReview, $listing->fresh()->moderation_status);
        }

        $this->assertSame($lapak->name, $lapak->fresh()->name);
    }

    public function test_stale_availability_update_is_rejected_after_suspension(): void
    {
        [$owner, , $listing] = $this->publishedOwnerResources();

        LapakListing::query()->whereKey($listing->id)->update([
            'moderation_status' => LapakModerationStatus::Suspended->value,
        ]);

        $this->expectException(InvalidLapakTransition::class);

        app(UpdateLapakListingAvailability::class)->handle(
            $listing,
            LapakListingAvailability::Unavailable,
            $owner,
        );
    }

    public function test_second_submit_is_rejected_once_pending(): void
    {
        $owner = User::factory()->create();
        $member = Member::factory()->linked($owner)->create();
        $lapak = Lapak::factory()->create([
            'member_id' => $member->id,
            'contact_phone' => '081234567890',
        ]);

        app(SubmitLapakForReview::class)->handle($lapak, $owner);

        $this->expectException(InvalidLapakTransition::class);

        app(SubmitLapakForReview::class)->handle($lapak->fresh(), $owner);
    }

    public function test_second_listing_submit_is_rejected_once_pending(): void
    {
        [$owner, $lapak] = $this->publishedOwnerResources();
        $listing = LapakListing::factory()->create([
            'lapak_id' => $lapak->id,
            'title' => 'Draf produk',
        ]);

        app(SubmitLapakListingForReview::class)->handle($listing, $owner);

        $this->expectException(InvalidLapakTransition::class);

        app(SubmitLapakListingForReview::class)->handle($listing->fresh(), $owner);
    }

    /**
     * @return array{0: User, 1: Lapak, 2: LapakListing}
     */
    private function publishedOwnerResources(): array
    {
        $owner = User::factory()->create();
        $member = Member::factory()->linked($owner)->create();
        $lapak = Lapak::factory()->published()->create([
            'member_id' => $member->id,
            'name' => 'Lapak tayang',
            'contact_phone' => '081234567890',
        ]);
        $listing = LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'title' => 'Produk tayang',
        ]);

        return [$owner, $lapak, $listing];
    }
}
