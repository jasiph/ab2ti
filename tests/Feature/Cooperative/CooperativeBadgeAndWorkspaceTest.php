<?php

namespace Tests\Feature\Cooperative;

use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class CooperativeBadgeAndWorkspaceTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_active_cooperative_member_receives_badge_payload(): void
    {
        $staff = $this->userWithPermissions(['membership-members.view']);
        $member = Member::factory()->create();
        CooperativeMembership::factory()->create(['member_id' => $member->id]);

        $this->actingAs($staff)
            ->get(route('admin.membership.members.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('members.data.0.cooperative_badge', true));

        $this->actingAs($staff)
            ->get(route('admin.membership.members.show', $member))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('member.cooperative.state', 'active')
                ->missing('member.cooperative.notes'));
    }

    public function test_inactive_or_non_member_does_not_receive_active_badge(): void
    {
        $staff = $this->userWithPermissions(['membership-members.view']);
        $plain = Member::factory()->create();
        $inactive = Member::factory()->create();
        CooperativeMembership::factory()->inactive()->create(['member_id' => $inactive->id]);

        $this->actingAs($staff)
            ->get(route('admin.membership.members.show', $plain))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('member.cooperative.state', 'none'));

        $this->actingAs($staff)
            ->get(route('admin.membership.members.show', $inactive))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('member.cooperative.state', 'inactive'));
    }

    public function test_workspace_status_is_read_only_for_the_authenticated_member(): void
    {
        $this->syncAclFromConfig();
        $account = User::factory()->create();
        $member = Member::factory()->linked($account)->create();
        CooperativeMembership::factory()->create([
            'member_id' => $member->id,
            'notes' => 'Jangan tampilkan di ruang anggota',
        ]);

        $this->actingAs($account)
            ->get(route('account.lapak.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Account/Lapak/Empty')
                ->where('cooperative.state', 'active')
                ->where('cooperative.psn.has_psn', false)
                ->missing('cooperative.notes'));

        $this->actingAs($account)
            ->patch(route('admin.cooperative.memberships.update', $member->cooperativeMembership), [
                'status' => 'inactive',
            ])
            ->assertForbidden();

        $this->assertTrue($member->fresh()->isActiveCooperativeMember());
    }

    public function test_member_cannot_modify_another_members_implied_cooperative_state_from_workspace(): void
    {
        $this->syncAclFromConfig();
        Role::findOrCreate('editor', 'web');
        $account = User::factory()->create();
        Member::factory()->linked($account)->create();
        $other = CooperativeMembership::factory()->create();

        $this->actingAs($account)
            ->patch(route('admin.cooperative.memberships.update', $other), [
                'status' => 'inactive',
            ])
            ->assertForbidden();
    }
}
