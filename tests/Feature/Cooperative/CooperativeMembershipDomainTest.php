<?php

namespace Tests\Feature\Cooperative;

use App\Enums\CooperativeMembershipStatus;
use App\Models\CooperativeMembership;
use App\Models\Member;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CooperativeMembershipDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_membership_belongs_to_a_member(): void
    {
        $member = Member::factory()->create();
        $membership = CooperativeMembership::factory()->create([
            'member_id' => $member->id,
        ]);

        $this->assertTrue($membership->member->is($member));
        $this->assertTrue($member->fresh()->isActiveCooperativeMember());
    }

    public function test_one_member_cannot_have_two_cooperative_membership_rows(): void
    {
        $member = Member::factory()->create();
        CooperativeMembership::factory()->create(['member_id' => $member->id]);

        $this->expectException(QueryException::class);

        CooperativeMembership::factory()->create(['member_id' => $member->id]);
    }

    public function test_an_ab2ti_member_without_a_row_is_not_a_cooperative_member(): void
    {
        $member = Member::factory()->create();

        $this->assertFalse($member->isActiveCooperativeMember());
        $this->assertNull($member->cooperativeMembership);
    }

    public function test_inactive_membership_is_not_treated_as_active(): void
    {
        $member = Member::factory()->create();
        CooperativeMembership::factory()->inactive()->create(['member_id' => $member->id]);

        $this->assertFalse($member->fresh()->isActiveCooperativeMember());
        $this->assertSame(CooperativeMembershipStatus::Inactive, $member->cooperativeMembership->status);
    }
}
