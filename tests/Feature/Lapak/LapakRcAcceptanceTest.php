<?php

namespace Tests\Feature\Lapak;

use App\Actions\Membership\IssueMemberAccountLink;
use App\Enums\LapakModerationStatus;
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

class LapakRcAcceptanceTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->syncAclFromConfig();
    }

    public function test_staff_issues_claim_and_user_links_to_member(): void
    {
        $staff = $this->lapakStaff();
        $member = Member::factory()->create(['legal_name' => 'Siti Tani']);
        $account = User::factory()->create();
        $secret = app(IssueMemberAccountLink::class)->handle($member, $staff, $account->email)->plainSecret;

        $this->actingAs($account)
            ->post(route('account.member-link.confirm', $secret))
            ->assertRedirect(route('account.lapak.index'));

        $this->assertSame($account->id, $member->fresh()->user_id);
        $this->actingAs($account)->get(route('account.lapak.index'))->assertOk();
        $this->actingAs($account)->get(route('dashboard'))->assertForbidden();
    }

    public function test_linked_member_login_reaches_owner_workspace_not_admin_403(): void
    {
        $account = User::factory()->create();
        Member::factory()->linked($account)->create();

        $this->post('/login', [
            'email' => $account->email,
            'password' => 'password',
        ])->assertRedirect(route('account.lapak.index', absolute: false));

        $this->actingAs($account)->get(route('account.lapak.index'))->assertOk();
        $this->actingAs($account)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($account)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_ordinary_user_login_is_safe_and_cannot_enter_admin(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('welcome', absolute: false));

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.lapak.storefronts.index'))->assertForbidden();
    }

    public function test_owner_create_submit_admin_publish_then_public_contact(): void
    {
        [$owner] = $this->linkedOwner();
        $staff = $this->lapakStaff();
        $category = LapakCategory::factory()->create(['name' => 'Benih', 'slug' => 'benih']);

        $this->actingAs($owner)
            ->post(route('account.lapak.store'), [
                'name' => 'Lapak Siti',
                'contact_whatsapp' => '081234567890',
                'contact_phone' => '081234567890',
                'contact_email' => 'lapak@example.com',
                'new_cover' => UploadedFile::fake()->image('sampul.jpg', 800, 400),
            ])
            ->assertRedirect(route('account.lapak.index'));

        $lapak = $owner->linkedMember()->fresh()->lapak;
        $this->assertNotNull($lapak);
        $this->assertSame(LapakModerationStatus::Draft, $lapak->moderation_status);

        $owner->unsetRelation('member');

        $this->actingAs($owner)
            ->post(route('account.lapak.listings.store'), [
                'category_id' => $category->id,
                'title' => 'Benih jagung',
                'price_mode' => 'fixed',
                'price_amount' => 25000,
                'price_unit' => 'kg',
                'availability' => 'available',
                'new_image' => UploadedFile::fake()->image('jagung.jpg', 600, 600),
            ])
            ->assertRedirect(route('account.lapak.listings.index'));

        $listing = $lapak->fresh()->listings()->first();
        $this->assertNotNull($listing);
        $this->assertSame(LapakModerationStatus::Draft, $listing->moderation_status);

        $this->actingAs($owner)
            ->post(route('account.lapak.submit', $lapak))
            ->assertRedirect();
        $this->actingAs($owner)
            ->post(route('account.lapak.listings.submit', $listing))
            ->assertRedirect();
        $this->actingAs($owner)
            ->post(route('admin.lapak.storefronts.moderate', $lapak), ['status' => 'published'])
            ->assertForbidden();

        $this->get('/lapak')->assertOk()->assertInertia(fn ($page) => $page->where('listings.data', []));

        $this->actingAs($staff)
            ->post(route('admin.lapak.storefronts.moderate', $lapak), ['status' => 'published'])
            ->assertRedirect();
        $this->actingAs($staff)
            ->post(route('admin.lapak.listings.moderate', $listing), ['status' => 'published'])
            ->assertRedirect();

        $this->assertSame(LapakModerationStatus::Published, $lapak->fresh()->moderation_status);
        $this->assertSame(LapakModerationStatus::Published, $listing->fresh()->moderation_status);

        $this->get('/lapak')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('listings.data', 1)
                ->where('listings.data.0.title', 'Benih jagung')
                ->missing('listings.data.0.user_id')
                ->missing('listings.data.0.member_name'));

        $this->get('/lapak/'.$lapak->slug.'/produk/'.$listing->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('listing.title', 'Benih jagung')
                ->where('listing.price_label', 'Rp 25.000 / kg')
                ->where('listing.category_name', 'Benih')
                ->where('listing.lapak.name', 'Lapak Siti')
                ->where('listing.lapak.contact.0.href', fn ($href) => is_string($href)
                    && str_starts_with($href, 'https://wa.me/6281234567890'))
                ->missing('listing.created_by')
                ->missing('listing.moderation_note')
                ->missing('listing.lapak.member'));

        $this->get('/lapak/'.$lapak->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('lapak.name', 'Lapak Siti')
                ->has('listings.data', 1));
    }

    public function test_rejected_listing_shows_reason_to_owner_never_public_then_resubmit_publish(): void
    {
        [$owner, $member] = $this->linkedOwner();
        $lapak = Lapak::factory()->published()->create([
            'member_id' => $member->id,
            'name' => 'Lapak Siti',
            'contact_whatsapp' => '081234567890',
        ]);
        $listing = LapakListing::factory()->create([
            'lapak_id' => $lapak->id,
            'title' => 'Benih jagung',
            'slug' => 'benih-ditolak',
            'moderation_status' => LapakModerationStatus::PendingReview,
        ]);
        $staff = $this->lapakStaff();

        $this->actingAs($staff)
            ->post(route('admin.lapak.listings.moderate', $listing), [
                'status' => 'rejected',
                'note' => 'Lengkapi deskripsi produk.',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->get(route('account.lapak.listings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('listings.0.moderation_note', 'Lengkapi deskripsi produk.')
                ->where('listings.0.can_edit', true));

        $this->get('/lapak/'.$lapak->slug.'/produk/'.$listing->slug)
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page
                ->component('Errors/Show')
                ->where('status', 404));

        $public = $this->get('/lapak/'.$lapak->slug.'/produk/'.$listing->slug)->assertNotFound()->getContent();
        $this->assertStringNotContainsString('Lengkapi deskripsi produk.', $public);

        $this->actingAs($owner)
            ->put(route('account.lapak.listings.update', $listing), [
                'category_id' => $listing->category_id,
                'title' => $listing->title,
                'description' => 'Deskripsi dilengkapi.',
                'price_mode' => 'negotiable',
                'availability' => 'available',
            ])
            ->assertRedirect();
        $this->actingAs($owner)
            ->post(route('account.lapak.listings.submit', $listing))
            ->assertRedirect();
        $this->actingAs($staff)
            ->post(route('admin.lapak.listings.moderate', $listing), ['status' => 'published'])
            ->assertRedirect();

        $this->get('/lapak/'.$lapak->slug.'/produk/'.$listing->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('listing.title', $listing->title)
                ->missing('listing.moderation_note'));
    }

    public function test_published_edit_returns_to_review_and_public_is_removed_until_republish(): void
    {
        [$owner, $lapak, $listing] = $this->publishedPair();
        $staff = $this->lapakStaff();

        $this->actingAs($owner)
            ->put(route('account.lapak.listings.update', $listing), [
                'category_id' => $listing->category_id,
                'title' => 'Judul diubah',
                'price_mode' => 'negotiable',
                'availability' => 'available',
            ])
            ->assertRedirect();

        $this->assertSame(LapakModerationStatus::PendingReview, $listing->fresh()->moderation_status);
        $this->get('/lapak/'.$lapak->slug.'/produk/'.$listing->slug)->assertNotFound();
        $this->get('/lapak')->assertOk()->assertInertia(fn ($page) => $page->where('listings.data', []));

        $this->actingAs($staff)
            ->post(route('admin.lapak.listings.moderate', $listing), ['status' => 'published'])
            ->assertRedirect();

        $this->get('/lapak/'.$lapak->slug.'/produk/'.$listing->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('listing.title', 'Judul diubah'));
    }

    public function test_availability_suspension_and_inactive_category_remove_public_delivery(): void
    {
        [$owner, $lapak, $listing] = $this->publishedPair();
        $staff = $this->lapakStaff();
        $path = '/lapak/'.$lapak->slug.'/produk/'.$listing->slug;

        $this->get($path)->assertOk();

        $this->actingAs($owner)
            ->post(route('account.lapak.listings.availability', $listing), [
                'availability' => 'unavailable',
            ])
            ->assertRedirect();
        $this->get($path)->assertNotFound();
        $this->get('/lapak')->assertOk()->assertInertia(fn ($page) => $page->where('listings.data', []));

        $this->actingAs($owner)
            ->post(route('account.lapak.listings.availability', $listing), [
                'availability' => 'available',
            ])
            ->assertRedirect();
        $this->get($path)->assertOk();

        $this->actingAs($staff)
            ->post(route('admin.lapak.listings.moderate', $listing), [
                'status' => 'suspended',
                'note' => 'Melanggar ketentuan.',
            ])
            ->assertRedirect();
        $this->get($path)->assertNotFound();
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->where('latestLapakListings', []));
        $this->actingAs($owner)
            ->post(route('account.lapak.listings.submit', $listing))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('admin.lapak.listings.moderate', $listing), ['status' => 'published']);
        $this->assertSame(LapakModerationStatus::Published, $listing->fresh()->moderation_status);

        $this->actingAs($staff)
            ->patch(route('admin.lapak.categories.toggle', $listing->category_id))
            ->assertRedirect();
        $this->get($path)->assertNotFound();
        $this->get('/lapak')->assertOk()->assertInertia(fn ($page) => $page->where('listings.data', []));
        $this->actingAs($owner)
            ->patch(route('admin.lapak.categories.toggle', $listing->category_id))
            ->assertForbidden();
    }

    public function test_foreign_member_and_ordinary_user_cannot_mutate_or_enter_admin(): void
    {
        [, $lapak, $listing] = $this->publishedPair();
        [$other] = $this->linkedOwner();
        $ordinary = User::factory()->create();

        $this->actingAs($other)
            ->put(route('account.lapak.update', $lapak), ['name' => 'Direbut'])
            ->assertForbidden();
        $this->actingAs($other)
            ->put(route('account.lapak.listings.update', $listing), [
                'category_id' => $listing->category_id,
                'title' => 'Direbut',
                'price_mode' => 'negotiable',
                'availability' => 'available',
            ])
            ->assertForbidden();
        $this->actingAs($ordinary)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($ordinary)->get(route('account.lapak.index'))->assertOk();
    }

    /**
     * @return array{0: User, 1: Member}
     */
    private function linkedOwner(): array
    {
        $account = User::factory()->create();
        $member = Member::factory()->linked($account)->create();

        return [$account, $member];
    }

    private function lapakStaff(): User
    {
        return $this->userWithPermissions(['lapak.view', 'lapak.manage', 'lapak.moderate']);
    }

    /**
     * @return array{0: User, 1: Lapak, 2: LapakListing}
     */
    private function publishedPair(): array
    {
        [$owner, $member] = $this->linkedOwner();
        $lapak = Lapak::factory()->published()->create([
            'member_id' => $member->id,
            'name' => 'Lapak Siti',
            'contact_whatsapp' => '081234567890',
        ]);
        $listing = LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'title' => 'Benih jagung',
            'slug' => 'benih-jagung',
        ]);

        return [$owner, $lapak, $listing];
    }
}
