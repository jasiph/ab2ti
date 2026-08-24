<?php

namespace Tests\Feature\SecureCapability;

use App\Models\MembershipApplication;
use App\Models\SecureCapabilityGrant;
use App\Support\SecureCapability\SecureCapabilityAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class SecureCapabilityGrantTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_issue_stores_digest_only_and_revokes_siblings(): void
    {
        $staff = $this->userWithPermissions(['membership-applications.request-revision']);
        $application = MembershipApplication::factory()->underReview()->create();
        $access = app(SecureCapabilityAccess::class);

        $first = $access->issue(
            'membership-application-revision',
            'membership_application',
            $application->id,
            $staff,
        );
        $second = $access->issue(
            'membership-application-revision',
            'membership_application',
            $application->id,
            $staff,
        );

        $this->assertDatabaseCount('secure_capability_grants', 2);
        $this->assertNotNull($first->grant->fresh()->revoked_at);
        $this->assertNull($second->grant->fresh()->revoked_at);
        $this->assertDatabaseMissing('secure_capability_grants', [
            'secret_digest' => $first->plainSecret,
        ]);
        $this->assertNull($access->findLiveBySecret($first->plainSecret));
        $this->assertNotNull($access->findLiveBySecret($second->plainSecret));
        $this->assertFalse(
            $access->assertUsable(
                $second->grant->fresh(),
                'membership-application-revision',
                'membership_application',
                $application->id + 1,
            ),
        );
    }

    public function test_unknown_purpose_is_rejected(): void
    {
        $staff = $this->userWithPermissions(['membership-applications.view']);
        $application = MembershipApplication::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        app(SecureCapabilityAccess::class)->issue(
            'not-a-purpose',
            'membership_application',
            $application->id,
            $staff,
        );
    }

    public function test_consumed_grant_is_not_live(): void
    {
        $staff = $this->userWithPermissions(['membership-applications.request-revision']);
        $application = MembershipApplication::factory()->underReview()->create();
        $access = app(SecureCapabilityAccess::class);
        $issued = $access->issue(
            'membership-application-revision',
            'membership_application',
            $application->id,
            $staff,
        );

        $access->consume($issued->grant);

        $this->assertNotNull($issued->grant->fresh()->consumed_at);
        $this->assertNull($access->findLiveBySecret($issued->plainSecret));
        $this->assertSame(0, SecureCapabilityGrant::query()->whereNull('consumed_at')->count());
    }

    public function test_wrong_purpose_or_target_is_denied(): void
    {
        $staff = $this->userWithPermissions(['membership-applications.request-revision']);
        $application = MembershipApplication::factory()->underReview()->create();
        $access = app(SecureCapabilityAccess::class);
        $issued = $access->issue(
            'membership-application-revision',
            'membership_application',
            $application->id,
            $staff,
        );

        $this->assertFalse(
            $access->assertUsable(
                $issued->grant,
                'award-entry-revision',
                'membership_application',
                $application->id,
            ),
        );
        $this->assertFalse(
            $access->assertUsable(
                $issued->grant,
                'membership-application-revision',
                'award_entry',
                $application->id,
            ),
        );
    }
}
