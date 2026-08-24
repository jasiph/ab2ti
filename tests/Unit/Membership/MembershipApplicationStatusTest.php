<?php

namespace Tests\Unit\Membership;

use App\Enums\MembershipApplicationStatus;
use Tests\TestCase;

class MembershipApplicationStatusTest extends TestCase
{
    public function test_phase_2a_allowed_transitions_match_the_lifecycle_contract(): void
    {
        $submitted = MembershipApplicationStatus::Submitted;
        $review = MembershipApplicationStatus::UnderReview;
        $revision = MembershipApplicationStatus::RevisionRequested;
        $approved = MembershipApplicationStatus::Approved;
        $rejected = MembershipApplicationStatus::Rejected;

        $this->assertTrue($submitted->canTransitionTo($review));
        $this->assertFalse($submitted->canTransitionTo($approved));
        $this->assertFalse($submitted->canTransitionTo($rejected));

        $this->assertTrue($review->canTransitionTo($approved));
        $this->assertTrue($review->canTransitionTo($rejected));
        $this->assertTrue($review->canTransitionTo($revision));

        $this->assertTrue($revision->canTransitionTo($submitted));
        $this->assertFalse($revision->canTransitionTo($review));
        $this->assertFalse($revision->canTransitionTo($approved));

        $this->assertFalse($approved->canTransitionTo($rejected));
        $this->assertFalse($rejected->canTransitionTo($approved));
        $this->assertTrue($approved->isTerminal());
        $this->assertTrue($rejected->isTerminal());
    }
}
