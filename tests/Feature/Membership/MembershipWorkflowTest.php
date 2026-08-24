<?php

namespace Tests\Feature\Membership;

use App\Actions\Membership\ApproveMembershipApplication;
use App\Enums\MembershipApplicationStatus;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class MembershipWorkflowTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_submitted_application_can_start_review(): void
    {
        $staff = $this->reviewer();
        $application = MembershipApplication::factory()->create();

        $this->actingAs($staff)
            ->post(route('admin.membership.applications.review', $application), [
                'staff_notes' => 'Memeriksa kelengkapan.',
            ])
            ->assertRedirect(route('admin.membership.applications.show', $application));

        $application->refresh();
        $this->assertSame(MembershipApplicationStatus::UnderReview, $application->status);
        $this->assertNotNull($application->review_started_at);
        $this->assertSame($staff->id, $application->reviewed_by);
        $this->assertSame('Memeriksa kelengkapan.', $application->staff_notes);
        $this->assertDatabaseHas('membership_application_transitions', [
            'membership_application_id' => $application->id,
            'to_status' => 'under_review',
        ]);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $staff = $this->approver();
        $application = MembershipApplication::factory()->create();

        $this->from(route('admin.membership.applications.show', $application))
            ->actingAs($staff)
            ->post(route('admin.membership.applications.approve', $application))
            ->assertRedirect(route('admin.membership.applications.show', $application))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('members', 0);
        $this->assertSame('submitted', $application->fresh()->status->value);
    }

    public function test_approved_application_creates_exactly_one_member(): void
    {
        $staff = $this->approver();
        $application = MembershipApplication::factory()->underReview()->create([
            'legal_name' => 'Budi Tani',
            'email' => 'budi@example.com',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.membership.applications.approve', $application))
            ->assertRedirect();

        $this->assertDatabaseCount('members', 1);
        $member = Member::query()->first();
        $this->assertSame($application->id, $member?->source_application_id);
        $this->assertSame('Budi Tani', $member?->legal_name);
        $this->assertSame('active', $member?->status->value);
        $this->assertSame('approved', $application->fresh()->status->value);
        $this->assertFalse(User::query()->where('email', 'budi@example.com')->exists());
        $this->assertFalse(Role::query()->where('name', 'member')->exists());
        $this->assertFalse($staff->fresh()->hasRole('member'));
    }

    public function test_approval_is_idempotent_and_cannot_create_duplicate_members(): void
    {
        $staff = $this->approver();
        $application = MembershipApplication::factory()->underReview()->create();

        $this->actingAs($staff)
            ->post(route('admin.membership.applications.approve', $application))
            ->assertRedirect();

        $memberId = Member::query()->value('id');

        $this->actingAs($staff)
            ->post(route('admin.membership.applications.approve', $application))
            ->assertRedirect(route('admin.membership.members.show', $memberId));

        $this->assertDatabaseCount('members', 1);
        $this->assertSame(1, MembershipApplication::query()->where('status', 'approved')->count());

        $second = app(ApproveMembershipApplication::class)->handle($application->fresh(), $staff);
        $this->assertSame($memberId, $second->id);
        $this->assertDatabaseCount('members', 1);
    }

    public function test_rejected_application_cannot_be_approved(): void
    {
        $staff = $this->userWithPermissions([
            'membership-applications.view',
            'membership-applications.approve',
            'membership-applications.reject',
        ]);
        $application = MembershipApplication::factory()->underReview()->create();

        $this->actingAs($staff)
            ->post(route('admin.membership.applications.reject', $application), [
                'rejection_reason' => 'Data tidak lengkap.',
            ])
            ->assertRedirect();

        $this->from(route('admin.membership.applications.show', $application))
            ->actingAs($staff)
            ->post(route('admin.membership.applications.approve', $application))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('rejected', $application->fresh()->status->value);
        $this->assertDatabaseCount('members', 0);
    }

    public function test_reject_does_not_create_a_member(): void
    {
        $staff = $this->userWithPermissions([
            'membership-applications.view',
            'membership-applications.reject',
        ]);
        $application = MembershipApplication::factory()->underReview()->create();

        $this->actingAs($staff)
            ->post(route('admin.membership.applications.reject', $application))
            ->assertRedirect();

        $this->assertDatabaseCount('members', 0);
        $this->assertSame('rejected', $application->fresh()->status->value);
    }

    private function reviewer(): User
    {
        return $this->userWithPermissions([
            'membership-applications.view',
            'membership-applications.review',
        ]);
    }

    private function approver(): User
    {
        return $this->userWithPermissions([
            'membership-applications.view',
            'membership-applications.approve',
        ]);
    }
}
