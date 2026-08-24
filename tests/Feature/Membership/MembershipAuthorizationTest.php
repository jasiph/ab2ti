<?php

namespace Tests\Feature\Membership;

use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class MembershipAuthorizationTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_guest_cannot_access_membership_admin(): void
    {
        $application = MembershipApplication::factory()->create();

        $this->get(route('admin.membership.applications.index'))
            ->assertRedirect(route('login'));
        $this->get(route('admin.membership.applications.show', $application))
            ->assertRedirect(route('login'));
        $this->get(route('admin.membership.members.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_permission_cannot_access_membership_admin(): void
    {
        $this->syncAclFromConfig();
        $user = User::factory()->create();
        $application = MembershipApplication::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.membership.applications.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->get(route('admin.membership.applications.show', $application))
            ->assertForbidden();
        $this->actingAs($user)
            ->get(route('admin.membership.members.index'))
            ->assertForbidden();
    }

    public function test_view_permission_allows_list_but_not_approve(): void
    {
        $user = $this->userWithPermissions(['membership-applications.view']);
        $application = MembershipApplication::factory()->underReview()->create();

        $this->actingAs($user)
            ->get(route('admin.membership.applications.index'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('admin.membership.applications.approve', $application))
            ->assertForbidden();

        $this->assertDatabaseCount('members', 0);
        $this->assertSame('under_review', $application->fresh()->status->value);
    }

    public function test_review_permission_does_not_imply_approve(): void
    {
        $user = $this->userWithPermissions([
            'membership-applications.view',
            'membership-applications.review',
        ]);
        $application = MembershipApplication::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.membership.applications.review', $application))
            ->assertRedirect(route('admin.membership.applications.show', $application));

        $this->assertSame('under_review', $application->fresh()->status->value);

        $this->actingAs($user)
            ->post(route('admin.membership.applications.approve', $application))
            ->assertForbidden();

        $this->assertDatabaseCount('members', 0);
    }

    public function test_approve_and_reject_are_independently_enforceable(): void
    {
        $approver = $this->userWithPermissions([
            'membership-applications.view',
            'membership-applications.approve',
        ]);
        $rejector = $this->userWithPermissions([
            'membership-applications.view',
            'membership-applications.reject',
        ]);
        $toApprove = MembershipApplication::factory()->underReview()->create();
        $toReject = MembershipApplication::factory()->underReview()->create();

        $this->actingAs($approver)
            ->post(route('admin.membership.applications.reject', $toApprove))
            ->assertForbidden();

        $this->actingAs($rejector)
            ->post(route('admin.membership.applications.approve', $toReject))
            ->assertForbidden();

        $this->actingAs($approver)
            ->post(route('admin.membership.applications.approve', $toApprove))
            ->assertRedirect();

        $this->actingAs($rejector)
            ->post(route('admin.membership.applications.reject', $toReject))
            ->assertRedirect();

        $this->assertSame('approved', $toApprove->fresh()->status->value);
        $this->assertSame('rejected', $toReject->fresh()->status->value);
        $this->assertDatabaseCount('members', 1);
    }

    public function test_editor_role_does_not_receive_membership_administration(): void
    {
        $this->syncAclFromConfig();

        $editorRole = Role::findOrCreate('editor', 'web');
        $editorRole->syncPermissions([
            'cms.view',
            'cms.create',
            'cms.update',
            'heroes.view',
            'media.view',
            'media.create',
        ]);

        $editor = User::factory()->create();
        $editor->assignRole('editor');
        $application = MembershipApplication::factory()->underReview()->create();

        $this->actingAs($editor)
            ->get(route('admin.membership.applications.index'))
            ->assertForbidden();
        $this->actingAs($editor)
            ->post(route('admin.membership.applications.approve', $application))
            ->assertForbidden();
        $this->actingAs($editor)
            ->get(route('admin.membership.members.index'))
            ->assertForbidden();
    }

    public function test_member_view_permission_is_required_for_member_workspace(): void
    {
        $user = $this->userWithPermissions(['membership-applications.view']);
        $member = Member::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.membership.members.show', $member))
            ->assertForbidden();

        $viewer = $this->userWithPermissions(['membership-members.view']);

        $this->actingAs($viewer)
            ->get(route('admin.membership.members.show', $member))
            ->assertOk();
    }
}
