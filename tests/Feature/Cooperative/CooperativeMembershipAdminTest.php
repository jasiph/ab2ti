<?php

namespace Tests\Feature\Cooperative;

use App\Enums\CooperativeMembershipStatus;
use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class CooperativeMembershipAdminTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_authorized_staff_can_list_cooperative_members(): void
    {
        $staff = $this->userWithPermissions(['cooperative.view']);
        $membership = CooperativeMembership::factory()->create();

        $this->actingAs($staff)
            ->get(route('admin.cooperative.memberships.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Cooperative/Memberships/Index')
                ->has('memberships.data', 1)
                ->where('memberships.data.0.member.legal_name', $membership->member->legal_name)
                ->missing('memberships.data.0.notes'));
    }

    public function test_authorized_staff_can_establish_membership_for_an_existing_member(): void
    {
        $staff = $this->userWithPermissions(['cooperative.view', 'cooperative.manage']);
        $member = Member::factory()->create();

        $this->actingAs($staff)
            ->post(route('admin.cooperative.memberships.store'), [
                'member_id' => $member->id,
                'status' => 'active',
                'notes' => 'Catatan internal pengurus',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cooperative_memberships', [
            'member_id' => $member->id,
            'status' => CooperativeMembershipStatus::Active->value,
            'notes' => 'Catatan internal pengurus',
        ]);
        $this->assertTrue($member->fresh()->isActiveCooperativeMember());
    }

    public function test_duplicate_membership_is_rejected(): void
    {
        $staff = $this->userWithPermissions(['cooperative.view', 'cooperative.manage']);
        $member = Member::factory()->create();
        CooperativeMembership::factory()->create(['member_id' => $member->id]);

        $this->actingAs($staff)
            ->from(route('admin.cooperative.memberships.create'))
            ->post(route('admin.cooperative.memberships.store'), [
                'member_id' => $member->id,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.cooperative.memberships.create'))
            ->assertSessionHasErrors('member_id');

        $this->assertDatabaseCount('cooperative_memberships', 1);
    }

    public function test_non_staff_cannot_access_admin_cooperative_routes(): void
    {
        $this->syncAclFromConfig();
        $user = User::factory()->create();
        $membership = CooperativeMembership::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.cooperative.memberships.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.cooperative.memberships.store'), [
                'member_id' => Member::factory()->create()->id,
                'status' => 'active',
            ])
            ->assertForbidden();
        $this->actingAs($user)
            ->patch(route('admin.cooperative.memberships.update', $membership), [
                'status' => 'inactive',
            ])
            ->assertForbidden();
    }

    public function test_membership_can_be_deactivated_and_reactivated(): void
    {
        $staff = $this->userWithPermissions(['cooperative.view', 'cooperative.manage']);
        $membership = CooperativeMembership::factory()->create();

        $this->actingAs($staff)
            ->patch(route('admin.cooperative.memberships.update', $membership), [
                'status' => 'inactive',
            ])
            ->assertRedirect();

        $membership->refresh();
        $this->assertSame(CooperativeMembershipStatus::Inactive, $membership->status);
        $this->assertNotNull($membership->ended_at);
        $this->assertFalse($membership->member->fresh()->isActiveCooperativeMember());

        $this->actingAs($staff)
            ->patch(route('admin.cooperative.memberships.update', $membership), [
                'status' => 'active',
            ])
            ->assertRedirect();

        $membership->refresh();
        $this->assertSame(CooperativeMembershipStatus::Active, $membership->status);
        $this->assertNull($membership->ended_at);
        $this->assertTrue($membership->member->fresh()->isActiveCooperativeMember());
    }

    public function test_editor_role_does_not_receive_cooperative_administration(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $this->actingAs($editor)
            ->get(route('admin.cooperative.memberships.index'))
            ->assertForbidden();
    }

    public function test_create_search_omits_members_who_already_have_membership(): void
    {
        $staff = $this->userWithPermissions(['cooperative.view', 'cooperative.manage']);
        $taken = Member::factory()->create(['legal_name' => 'Sudah Koperasi']);
        $open = Member::factory()->create(['legal_name' => 'Belum Koperasi']);
        CooperativeMembership::factory()->create(['member_id' => $taken->id]);

        $this->actingAs($staff)
            ->get(route('admin.cooperative.memberships.create', ['search' => 'Koperasi']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Cooperative/Memberships/Create')
                ->has('candidates', 1)
                ->where('candidates.0.id', $open->id));
    }
}
