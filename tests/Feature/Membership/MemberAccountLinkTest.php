<?php

namespace Tests\Feature\Membership;

use App\Actions\Membership\IssueMemberAccountLink;
use App\Models\Member;
use App\Models\SecureCapabilityGrant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class MemberAccountLinkTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_staff_can_issue_an_invitation_and_secret_is_not_persisted(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create();

        $response = $this->actingAs($staff)
            ->post(route('admin.membership.members.account-link', $member), [
                'intended_email' => $member->email,
            ]);

        $response->assertRedirect(route('admin.membership.members.show', $member));
        $response->assertSessionHas('member_account_link_url');

        $url = (string) $response->getSession()->get('member_account_link_url');
        $secret = basename(parse_url($url, PHP_URL_PATH) ?: '');

        $this->assertNotSame('', $secret);
        $this->assertStringContainsString('/akun/tautkan/', $url);
        $this->assertDatabaseMissing('secure_capability_grants', [
            'secret_digest' => $secret,
        ]);
        $this->assertDatabaseHas('secure_capability_grants', [
            'purpose' => 'member-account-link',
            'target_type' => 'member',
            'target_id' => $member->id,
        ]);
        $grant = SecureCapabilityGrant::query()->where('target_id', $member->id)->first();
        $this->assertSame(strtolower($member->email), $grant?->payload['intended_email'] ?? null);
        $this->assertFalse((bool) ($grant?->payload['existing_account'] ?? true));
        $this->assertArrayNotHasKey('intended_user_id', $grant?->payload ?? []);
        $this->assertNull($member->fresh()->user_id);
    }

    public function test_valid_claim_links_authenticated_user_after_explicit_confirm(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create(['legal_name' => 'Siti Tani']);
        $account = User::factory()->create();
        $secret = $this->issueSecret($staff, $member, $account->email);

        $this->actingAs($account)
            ->get(route('account.member-link.show', $secret))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Account/MemberLink/Confirm')
                ->where('member.legal_name', 'Siti Tani')
                ->where('staff_blocked', false)
                ->where('already_linked_elsewhere', false));

        $this->assertNull($member->fresh()->user_id);

        $this->actingAs($account)
            ->post(route('account.member-link.confirm', $secret))
            ->assertRedirect(route('account.lapak.index'));

        $member->refresh();
        $this->assertSame($account->id, $member->user_id);
        $this->assertNotNull($member->linked_at);
        $this->assertTrue($account->fresh()->linkedMember()?->is($member));
        $this->assertNotNull(SecureCapabilityGrant::query()->where('target_id', $member->id)->first()?->consumed_at);
        $this->assertFalse($account->fresh()->can('lapak.manage'));
        $this->assertFalse($account->fresh()->can('lapak.moderate'));
        $this->assertFalse(Role::query()->where('name', 'member')->exists());
    }

    public function test_guest_sees_invitation_and_matching_email_does_not_link(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create(['email' => 'sama@example.com', 'phone' => '081234567890']);
        $account = User::factory()->create([
            'email' => 'sama@example.com',
        ]);
        $secret = $this->issueSecret($staff, $member, $account->email);

        $this->get(route('account.member-link.show', $secret))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Account/MemberLink/Activate')
                ->where('mode', 'login')
                ->where('existing_account', true));

        $this->actingAs($account)
            ->get(route('account.lapak.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Account/Lapak/Unlinked')
                ->where('auth.linked_to_member', false));

        $this->assertNull($member->fresh()->user_id);
        $this->assertNull($account->fresh()->linkedMember());
    }

    public function test_invalid_expired_revoked_and_consumed_tokens_are_rejected(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create();
        $account = User::factory()->create();

        $this->actingAs($account)
            ->get(route('account.member-link.show', str_repeat('a', 32)))
            ->assertNotFound();

        $expired = $this->issueSecret($staff, $member, $account->email);
        SecureCapabilityGrant::query()->latest('id')->first()?->forceFill([
            'expires_at' => now()->subHour(),
        ])->save();

        $this->actingAs($account)
            ->get(route('account.member-link.show', $expired))
            ->assertNotFound();
        $this->actingAs($account)
            ->post(route('account.member-link.confirm', $expired))
            ->assertNotFound();

        $revoked = $this->issueSecret($staff, $member, $account->email);
        $this->actingAs($staff)
            ->delete(route('admin.membership.members.account-link.revoke', $member))
            ->assertRedirect();

        $this->actingAs($account)
            ->post(route('account.member-link.confirm', $revoked))
            ->assertNotFound();

        $live = $this->issueSecret($staff, $member, $account->email);
        $this->actingAs($account)
            ->post(route('account.member-link.confirm', $live))
            ->assertRedirect(route('account.lapak.index'));

        $this->actingAs($account)
            ->post(route('account.member-link.confirm', $live))
            ->assertNotFound();
    }

    public function test_token_cannot_link_a_different_member_and_replay_fails(): void
    {
        $staff = $this->linkStaff();
        $target = Member::factory()->create();
        $other = Member::factory()->create();
        $account = User::factory()->create();
        $secret = $this->issueSecret($staff, $target, $account->email);

        $this->actingAs($account)
            ->post(route('account.member-link.confirm', $secret))
            ->assertRedirect(route('account.lapak.index'));

        $this->assertSame($account->id, $target->fresh()->user_id);
        $this->assertNull($other->fresh()->user_id);

        $this->actingAs($account)
            ->post(route('account.member-link.confirm', $secret))
            ->assertNotFound();
    }

    public function test_already_linked_member_and_user_cannot_be_claimed_again(): void
    {
        $staff = $this->linkStaff();
        $account = User::factory()->create();
        $linked = Member::factory()->linked($account)->create();
        $secondMember = Member::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($staff)
            ->post(route('admin.membership.members.account-link', $linked), [
                'intended_email' => $linked->email,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $secret = $this->issueSecret($staff, $secondMember, $secondUser->email);

        $this->actingAs($account)
            ->post(route('account.member-link.confirm', $secret))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($secondMember->fresh()->user_id);
        $this->assertSame($account->id, $linked->fresh()->user_id);

        $this->actingAs($secondUser)
            ->post(route('account.member-link.confirm', $secret))
            ->assertRedirect(route('account.lapak.index'));

        $this->assertSame($secondUser->id, $secondMember->fresh()->user_id);
    }

    public function test_staff_role_cannot_claim_a_member_to_the_staff_account(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $staff = User::factory()->create();
        $staff->assignRole('membership-admin');
        $member = Member::factory()->create();
        $secret = $this->issueSecret($staff, $member);

        $this->actingAs($staff)
            ->get(route('account.member-link.show', $secret))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('staff_blocked', true));

        $this->actingAs($staff)
            ->post(route('account.member-link.confirm', $secret))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($member->fresh()->user_id);
        $this->assertNull($staff->fresh()->linkedMember());
    }

    public function test_revision_akses_route_rejects_account_link_secret(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create();
        $secret = $this->issueSecret($staff, $member);

        $this->get('/akses/'.$secret)->assertNotFound();
    }

    public function test_member_ids_cannot_be_enumerated_into_ownership(): void
    {
        $account = User::factory()->create();
        $member = Member::factory()->create();

        $this->actingAs($account)
            ->get(route('account.lapak.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Account/Lapak/Unlinked'));

        $this->actingAs($account)
            ->post(route('account.lapak.store'), [
                'member_id' => $member->id,
                'name' => 'Lapak curian',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('lapaks', 0);
        $this->assertNull($member->fresh()->user_id);
        $this->assertFalse(Schema::hasColumn('users', 'member_id'));
    }

    public function test_user_id_is_not_mass_assignable_and_unique(): void
    {
        $account = User::factory()->create();
        $member = Member::factory()->create();

        $member->fill(['user_id' => $account->id, 'legal_name' => 'Tetap'])->save();
        $this->assertNull($member->fresh()->user_id);

        Member::factory()->linked($account)->create();

        $this->expectException(QueryException::class);
        Member::factory()->linked($account)->create();
    }

    public function test_view_permission_cannot_issue_account_link(): void
    {
        $viewer = $this->userWithPermissions(['membership-members.view']);
        $member = Member::factory()->create();

        $this->actingAs($viewer)
            ->post(route('admin.membership.members.account-link', $member))
            ->assertForbidden();
    }

    private function linkStaff(): User
    {
        return $this->userWithPermissions([
            'membership-members.view',
            'membership-members.update',
        ]);
    }

    private function issueSecret(User $staff, Member $member, ?string $email = null): string
    {
        return app(IssueMemberAccountLink::class)->handle($member, $staff, $email ?? $member->email)->plainSecret;
    }
}
