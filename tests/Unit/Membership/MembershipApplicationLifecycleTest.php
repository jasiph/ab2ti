<?php

namespace Tests\Unit\Membership;

use App\Enums\MembershipApplicationStatus;
use App\Exceptions\InvalidMembershipApplicationTransition;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Support\Membership\MembershipApplicationLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipApplicationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_initialize_persists_submitted_and_records_a_transition(): void
    {
        $application = new MembershipApplication;
        $application->fill([
            'legal_name' => 'Sari Petani',
            'email' => 'sari@example.com',
            'phone' => '081234567890',
            'address' => 'Desa Contoh',
        ]);
        $application->reference_code = 'APP-TESTCODE1';

        app(MembershipApplicationLifecycle::class)->initialize(
            $application,
            MembershipApplicationStatus::Submitted,
            'applicant',
            apply: function (MembershipApplication $application): void {
                $application->submitted_at = now();
            },
        );

        $application->refresh();
        $this->assertSame(MembershipApplicationStatus::Submitted, $application->status);
        $this->assertNotNull($application->submitted_at);
        $this->assertDatabaseHas('membership_application_transitions', [
            'membership_application_id' => $application->id,
            'from_status' => null,
            'to_status' => 'submitted',
            'actor_context' => 'applicant',
        ]);
    }

    public function test_initialize_rejects_non_submitted_first_status(): void
    {
        $application = new MembershipApplication;
        $application->fill([
            'legal_name' => 'Sari Petani',
            'email' => 'sari@example.com',
            'phone' => '081234567890',
            'address' => 'Desa Contoh',
        ]);
        $application->reference_code = 'APP-TESTCODE2';

        $this->expectException(InvalidMembershipApplicationTransition::class);

        app(MembershipApplicationLifecycle::class)->initialize(
            $application,
            MembershipApplicationStatus::Approved,
            'applicant',
        );
    }

    public function test_initialize_rejects_an_already_persisted_application(): void
    {
        $application = MembershipApplication::factory()->create();

        $this->expectException(InvalidMembershipApplicationTransition::class);

        app(MembershipApplicationLifecycle::class)->initialize(
            $application,
            MembershipApplicationStatus::Submitted,
            'applicant',
        );
    }

    public function test_valid_staff_transition_is_recorded(): void
    {
        $staff = User::factory()->create();
        $application = MembershipApplication::factory()->create();

        app(MembershipApplicationLifecycle::class)->transition(
            $application,
            MembershipApplicationStatus::UnderReview,
            $staff,
            'staff',
            'Mulai tinjau',
            function (MembershipApplication $application) use ($staff): void {
                $application->review_started_at = now();
                $application->reviewed_by = $staff->id;
            },
        );

        $this->assertSame(MembershipApplicationStatus::UnderReview, $application->fresh()->status);
        $this->assertDatabaseHas('membership_application_transitions', [
            'membership_application_id' => $application->id,
            'from_status' => 'submitted',
            'to_status' => 'under_review',
            'actor_user_id' => $staff->id,
        ]);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $staff = User::factory()->create();
        $lifecycle = app(MembershipApplicationLifecycle::class);

        $submitted = MembershipApplication::factory()->create();
        $this->assertTransitionRejected(
            $lifecycle,
            $submitted,
            MembershipApplicationStatus::Approved,
            $staff,
        );
        $this->assertSame('submitted', $submitted->fresh()->status->value);

        $rejected = MembershipApplication::factory()->rejected()->create();
        $this->assertTransitionRejected(
            $lifecycle,
            $rejected,
            MembershipApplicationStatus::Approved,
            $staff,
        );

        $revision = MembershipApplication::factory()->revisionRequested()->create();
        $this->assertTransitionRejected(
            $lifecycle,
            $revision,
            MembershipApplicationStatus::Approved,
            $staff,
        );
        $this->assertTransitionRejected(
            $lifecycle,
            $revision,
            MembershipApplicationStatus::UnderReview,
            $staff,
        );

        $approved = MembershipApplication::factory()->approved()->create();
        $this->assertTransitionRejected(
            $lifecycle,
            $approved,
            MembershipApplicationStatus::Rejected,
            $staff,
        );
    }

    public function test_applicant_resubmit_moves_revision_requested_to_submitted(): void
    {
        $application = MembershipApplication::factory()->revisionRequested()->create();

        app(MembershipApplicationLifecycle::class)->transition(
            $application,
            MembershipApplicationStatus::Submitted,
            null,
            'applicant',
            null,
            function (MembershipApplication $application): void {
                $application->resubmitted_at = now();
            },
        );

        $this->assertSame(MembershipApplicationStatus::Submitted, $application->fresh()->status);
        $this->assertDatabaseHas('membership_application_transitions', [
            'membership_application_id' => $application->id,
            'from_status' => 'revision_requested',
            'to_status' => 'submitted',
            'actor_context' => 'applicant',
        ]);
    }

    private function assertTransitionRejected(
        MembershipApplicationLifecycle $lifecycle,
        MembershipApplication $application,
        MembershipApplicationStatus $to,
        User $actor,
    ): void {
        try {
            $lifecycle->transition(
                $application,
                $to,
                $actor,
                'staff',
                null,
                function (MembershipApplication $application): void {
                    $application->staff_notes = 'should not persist';
                },
            );
            $this->fail('Expected InvalidMembershipApplicationTransition');
        } catch (InvalidMembershipApplicationTransition) {
            $this->assertNull($application->fresh()->staff_notes);
        }
    }
}
