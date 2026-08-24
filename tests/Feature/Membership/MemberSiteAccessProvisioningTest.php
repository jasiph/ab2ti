<?php

namespace Tests\Feature\Membership;

use App\Actions\Membership\IssueMemberAccountLink;
use App\Enums\LapakModerationStatus;
use App\Models\Lapak;
use App\Models\Member;
use App\Models\SecureCapabilityGrant;
use App\Models\User;
use App\Support\SecureCapability\SecureCapabilityAccess;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class MemberSiteAccessProvisioningTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_membership_admin_sees_site_access_panel_and_can_issue_invitation(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create(['email' => 'siti@example.com']);

        $this->actingAs($staff)
            ->get(route('admin.membership.members.show', $member))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Membership/Members/Show')
                ->where('member.account_link.status', 'unlinked')
                ->where('member.account_link.label', 'Belum aktif')
                ->where('canIssueAccountLink', true));

        $this->actingAs($staff)
            ->post(route('admin.membership.members.account-link', $member), [
                'intended_email' => 'siti@example.com',
            ])
            ->assertRedirect(route('admin.membership.members.show', $member))
            ->assertSessionHas('member_account_link_url');

        $this->actingAs($staff)
            ->get(route('admin.membership.members.show', $member))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('member.account_link.status', 'invited')
                ->where('member.account_link.intended_email', 'siti@example.com')
                ->where('member.account_link.existing_account', false));
    }

    public function test_linked_member_shows_active_site_access_and_cannot_receive_another_invitation(): void
    {
        $staff = $this->linkStaff();
        $account = User::factory()->create();
        $member = Member::factory()->linked($account)->create();

        $this->actingAs($staff)
            ->get(route('admin.membership.members.show', $member))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('member.account_link.status', 'linked')
                ->where('member.account_link.label', 'Aktif')
                ->where('member.account_link.linked_user_email', $account->email));

        $this->actingAs($staff)
            ->post(route('admin.membership.members.account-link', $member), [
                'intended_email' => $account->email,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_unauthorized_actors_cannot_provision_site_access(): void
    {
        $member = Member::factory()->create();
        $ordinary = User::factory()->create();
        $viewer = $this->userWithPermissions(['membership-members.view']);

        $this->actingAs($ordinary)
            ->post(route('admin.membership.members.account-link', $member), [
                'intended_email' => $member->email,
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('admin.membership.members.account-link', $member), [
                'intended_email' => $member->email,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('secure_capability_grants', 0);
    }

    public function test_reissue_revokes_previous_invitation_and_revoke_blocks_use(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create();
        $first = $this->issueSecret($staff, $member);

        $issued = $this->actingAs($staff)
            ->post(route('admin.membership.members.account-link', $member), [
                'intended_email' => $member->email,
            ]);
        $issued->assertRedirect();
        $liveUrl = (string) $issued->getSession()->get('member_account_link_url');
        $liveSecret = basename(parse_url($liveUrl, PHP_URL_PATH) ?: '');

        $this->get(route('account.member-link.show', $first))->assertNotFound();
        $this->assertNotNull(SecureCapabilityGrant::query()->oldest('id')->first()?->revoked_at);
        $this->assertNull(SecureCapabilityGrant::query()->latest('id')->first()?->revoked_at);
        $this->assertNotSame('', $liveSecret);

        $this->actingAs($staff)
            ->delete(route('admin.membership.members.account-link.revoke', $member))
            ->assertRedirect();

        $this->get(route('account.member-link.show', $liveSecret))->assertNotFound();
    }

    public function test_existing_user_logs_in_then_confirms_without_email_auto_link(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create(['email' => 'anggota@example.com', 'legal_name' => 'Siti Tani']);
        $account = User::factory()->create(['email' => 'akun@example.com']);
        $secret = $this->issueSecret($staff, $member, 'akun@example.com');

        $this->get(route('account.member-link.show', $secret))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Account/MemberLink/Activate')
                ->where('mode', 'login')
                ->where('member.legal_name', 'Siti Tani')
                ->missing('member.email')
                ->missing('member.phone'));

        $this->assertNull($member->fresh()->user_id);

        $this->post('/login', [
            'email' => $account->email,
            'password' => 'password',
        ])->assertRedirect(route('account.member-link.show', $secret, absolute: false));

        $this->assertAuthenticatedAs($account);
        $this->assertFalse($account->fresh()->isPlatformStaff());

        $this->from(route('account.member-link.show', $secret))
            ->post(route('account.member-link.confirm', $secret))
            ->assertRedirect(route('account.lapak.index'));

        $this->assertSame($account->id, $member->fresh()->user_id);
        $grant = SecureCapabilityGrant::query()->where('target_id', $member->id)->latest('id')->first();
        $this->assertNotNull($grant?->consumed_at);
        $this->assertTrue(($grant?->payload['existing_account'] ?? false) === true);
        $this->assertSame($account->id, $grant?->payload['intended_user_id'] ?? null);
    }

    public function test_unrelated_user_cannot_consume_an_existing_account_invitation(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create();
        $intended = User::factory()->create(['email' => 'user-a@example.com']);
        $stranger = User::factory()->create(['email' => 'user-b@example.com']);
        $secret = $this->issueSecret($staff, $member, $intended->email);

        $this->actingAs($stranger)
            ->post(route('account.member-link.confirm', $secret))
            ->assertNotFound();

        $this->assertNull($member->fresh()->user_id);
        $this->assertNull($stranger->fresh()->linkedMember());
        $this->assertNull(SecureCapabilityGrant::query()->where('target_id', $member->id)->latest('id')->first()?->consumed_at);

        $this->actingAs($intended)
            ->post(route('account.member-link.confirm', $secret))
            ->assertRedirect(route('account.lapak.index'));

        $this->assertSame($intended->id, $member->fresh()->user_id);
        $this->assertNotNull($member->fresh()->linked_at);
        $this->assertNotNull(SecureCapabilityGrant::query()->where('target_id', $member->id)->latest('id')->first()?->consumed_at);
    }

    public function test_invitation_stays_bound_to_user_id_after_email_change_and_reuse(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create();
        $intended = User::factory()->create(['email' => 'lama@example.com']);
        $secret = $this->issueSecret($staff, $member, 'lama@example.com');

        $intended->forceFill(['email' => 'baru@example.com'])->save();
        $reuse = User::factory()->create(['email' => 'lama@example.com']);

        $this->actingAs($reuse)
            ->post(route('account.member-link.confirm', $secret))
            ->assertNotFound();

        $this->assertNull($member->fresh()->user_id);
        $this->assertNull($reuse->fresh()->linkedMember());

        $this->actingAs($intended)
            ->post(route('account.member-link.confirm', $secret))
            ->assertRedirect(route('account.lapak.index'));

        $this->assertSame($intended->id, $member->fresh()->user_id);
        $this->assertSame('baru@example.com', $intended->fresh()->email);
        $this->actingAs($intended)
            ->post(route('account.member-link.confirm', $secret))
            ->assertNotFound();
    }

    public function test_legacy_existing_account_invitation_without_user_id_fails_closed(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create();
        $account = User::factory()->create(['email' => 'warisan@example.com']);
        $secret = $this->issueSecret($staff, $member, $account->email);

        $grant = SecureCapabilityGrant::query()->where('target_id', $member->id)->latest('id')->first();
        $payload = $grant?->payload ?? [];
        unset($payload['intended_user_id']);
        $grant?->forceFill(['payload' => $payload])->save();

        $this->actingAs($account)
            ->post(route('account.member-link.confirm', $secret))
            ->assertNotFound();

        $this->assertNull($member->fresh()->user_id);
        $this->assertNull($grant?->fresh()->consumed_at);
    }

    public function test_new_user_invitation_cannot_be_confirmed_by_an_existing_user(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create();
        $stranger = User::factory()->create();
        $secret = $this->issueSecret($staff, $member, 'baru.aktivasi@example.com');

        $this->actingAs($stranger)
            ->post(route('account.member-link.confirm', $secret))
            ->assertNotFound();

        $this->assertNull($member->fresh()->user_id);
        $this->assertNull(SecureCapabilityGrant::query()->where('target_id', $member->id)->latest('id')->first()?->consumed_at);

        $this->post('/logout');
        $this->post(route('account.member-link.activate', $secret), [
            'name' => 'Baru Aktivasi',
            'email' => 'baru.aktivasi@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('account.lapak.index'));

        $this->assertNotNull($member->fresh()->user_id);
        $this->assertNotSame($stranger->id, $member->fresh()->user_id);
    }

    public function test_existing_account_invitation_cannot_be_used_to_activate_a_new_user(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create();
        $intended = User::factory()->create(['email' => 'sudah@example.com']);
        $secret = $this->issueSecret($staff, $member, $intended->email);

        $this->post(route('account.member-link.activate', $secret), [
            'name' => 'Penyusup',
            'email' => 'sudah@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertNull($member->fresh()->user_id);
        $this->assertSame(1, User::query()->where('email', 'sudah@example.com')->count());
    }

    public function test_new_user_activation_creates_account_links_and_authenticates(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create([
            'legal_name' => 'Siti Tani',
            'email' => 'kontak-anggota@example.com',
        ]);
        $secret = $this->issueSecret($staff, $member, 'siti.akun@example.com');

        $this->get(route('account.member-link.show', $secret))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Account/MemberLink/Activate')
                ->where('mode', 'activate')
                ->where('intended_email', 'siti.akun@example.com')
                ->where('member.legal_name', 'Siti Tani')
                ->missing('member.email'));

        $this->post(route('account.member-link.activate', $secret), [
            'name' => 'Siti Akun',
            'email' => 'siti.akun@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('account.lapak.index'));

        $this->assertAuthenticated();
        $user = User::query()->where('email', 'siti.akun@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame($user->id, $member->fresh()->user_id);
        $this->assertNotNull($member->fresh()->linked_at);
        $this->assertNotSame('kontak-anggota@example.com', $user->email);
        $this->assertNotNull(SecureCapabilityGrant::query()->where('target_id', $member->id)->first()?->consumed_at);

        $this->get(route('account.lapak.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Account/Lapak/Empty'));

        $this->assertDatabaseCount('lapaks', 0);
    }

    public function test_activation_of_member_with_existing_lapak_does_not_create_a_second(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create(['email' => 'pemilik@example.com']);
        $lapak = Lapak::factory()->create([
            'member_id' => $member->id,
            'name' => 'Lapak Siti',
            'moderation_status' => LapakModerationStatus::Draft,
        ]);
        $secret = $this->issueSecret($staff, $member, 'pemilik.akun@example.com');

        $this->post(route('account.member-link.activate', $secret), [
            'name' => 'Pemilik',
            'email' => 'pemilik.akun@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('account.lapak.index'));

        $this->assertDatabaseCount('lapaks', 1);
        $this->get(route('account.lapak.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Account/Lapak/Overview')
                ->where('lapak.id', $lapak->id)
                ->where('lapak.name', 'Lapak Siti'));
    }

    public function test_activation_failures_do_not_create_orphan_users(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create();
        $secret = $this->issueSecret($staff, $member, 'baru@example.com');

        $this->post(route('account.member-link.activate', str_repeat('x', 32)), [
            'name' => 'Baru',
            'email' => 'baru@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'baru@example.com']);

        $expired = $this->issueSecret($staff, $member, 'baru@example.com');
        SecureCapabilityGrant::query()->latest('id')->first()?->forceFill([
            'expires_at' => now()->subHour(),
        ])->save();
        $this->post(route('account.member-link.activate', $expired), [
            'name' => 'Baru',
            'email' => 'baru@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'baru@example.com']);

        $revoked = $this->issueSecret($staff, $member, 'baru@example.com');
        $this->actingAs($staff)
            ->delete(route('admin.membership.members.account-link.revoke', $member));
        $this->post('/logout');
        $this->assertGuest();
        $this->post(route('account.member-link.activate', $revoked), [
            'name' => 'Baru',
            'email' => 'baru@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'baru@example.com']);

        $live = $this->issueSecret($staff, $member, 'baru@example.com');
        $this->post(route('account.member-link.activate', $live), [
            'name' => 'Baru',
            'email' => 'baru@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'baru@example.com']);
        $this->assertNull($member->fresh()->user_id);
    }

    public function test_activation_rejects_duplicate_email_wrong_email_replay_and_already_linked_member(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create();
        User::factory()->create(['email' => 'sudah-ada@example.com']);
        $secret = $this->issueSecret($staff, $member, 'baru@example.com');

        $this->post(route('account.member-link.activate', $secret), [
            'name' => 'Baru',
            'email' => 'sudah-ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertNull($member->fresh()->user_id);

        $this->post(route('account.member-link.activate', $secret), [
            'name' => 'Baru',
            'email' => 'lain@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => 'lain@example.com']);
        $this->assertNull($member->fresh()->user_id);

        $this->post(route('account.member-link.activate', $secret), [
            'name' => 'Baru',
            'email' => 'baru@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('account.lapak.index'));

        $this->post(route('account.member-link.activate', $secret), [
            'name' => 'Lagi',
            'email' => 'baru@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->post('/logout');
        $this->assertGuest();

        $this->post(route('account.member-link.activate', $secret), [
            'name' => 'Lagi',
            'email' => 'baru@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertSame(1, User::query()->where('email', 'baru@example.com')->count());
    }

    public function test_member_linked_before_submit_does_not_create_a_user(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create();
        $secret = $this->issueSecret($staff, $member, 'baru@example.com');
        $other = User::factory()->create();
        $member->forceFill([
            'user_id' => $other->id,
            'linked_at' => now(),
        ])->save();

        $this->post(route('account.member-link.activate', $secret), [
            'name' => 'Baru',
            'email' => 'baru@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'baru@example.com']);
        $this->assertSame($other->id, $member->fresh()->user_id);
    }

    public function test_wrong_purpose_and_staff_email_and_staff_claim_are_rejected(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('membership-admin');
        $member = Member::factory()->create();

        $this->actingAs($staff)
            ->post(route('admin.membership.members.account-link', $member), [
                'intended_email' => $staff->email,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $issued = app(SecureCapabilityAccess::class)->issue(
            'membership-application-revision',
            'membership_application',
            1,
            $staff,
        );

        $this->post('/logout');
        $this->assertGuest();

        $this->get(route('account.member-link.show', $issued->plainSecret))->assertNotFound();
        $this->post(route('account.member-link.activate', $issued->plainSecret), [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $secret = $this->issueSecret($staff, $member, 'anggota@example.com');
        $this->actingAs($staff)
            ->get(route('account.member-link.show', $secret))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Account/MemberLink/Confirm')
                ->where('staff_blocked', true));
        $this->actingAs($staff)
            ->post(route('account.member-link.confirm', $secret))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertNull($member->fresh()->user_id);
    }

    public function test_provisioned_member_logs_in_normally_and_cannot_enter_admin(): void
    {
        $staff = $this->linkStaff();
        $member = Member::factory()->create();
        $secret = $this->issueSecret($staff, $member, 'login.ulang@example.com');

        $this->post(route('account.member-link.activate', $secret), [
            'name' => 'Login Ulang',
            'email' => 'login.ulang@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('account.lapak.index'));

        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', [
            'email' => 'login.ulang@example.com',
            'password' => 'password',
        ])->assertRedirect(route('account.lapak.index', absolute: false));

        $user = User::query()->where('email', 'login.ulang@example.com')->first();
        $this->actingAs($user)->get(route('account.lapak.index'))->assertOk();
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_public_registration_remains_closed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'visitor@example.com']);
    }

    public function test_cannot_invite_a_user_already_linked_to_another_member(): void
    {
        $staff = $this->linkStaff();
        $taken = User::factory()->create(['email' => 'sudah-terhubung@example.com']);
        Member::factory()->linked($taken)->create();
        $member = Member::factory()->create();

        $this->actingAs($staff)
            ->post(route('admin.membership.members.account-link', $member), [
                'intended_email' => 'sudah-terhubung@example.com',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('secure_capability_grants', [
            'purpose' => 'member-account-link',
            'target_id' => $member->id,
        ]);
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
        return app(IssueMemberAccountLink::class)
            ->handle($member, $staff, $email ?? $member->email)
            ->plainSecret;
    }
}
