<?php

namespace App\Actions\Membership;

use App\Enums\MembershipApplicationStatus;
use App\Exceptions\InvalidMembershipApplicationTransition;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Support\Membership\MembershipApplicationLifecycle;
use App\Support\SecureCapability\IssuedCapability;
use App\Support\SecureCapability\SecureCapabilityAccess;
use Illuminate\Support\Facades\DB;

class RequestMembershipApplicationRevision
{
    public function __construct(
        private MembershipApplicationLifecycle $lifecycle,
        private SecureCapabilityAccess $capabilities,
    ) {}

    public function handle(
        MembershipApplication $application,
        User $actor,
        string $instruction,
    ): IssuedCapability {
        return DB::transaction(function () use ($application, $actor, $instruction) {
            /** @var MembershipApplication $application */
            $application = MembershipApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            $purpose = (string) config('membership.revision_capability_purpose', 'membership-application-revision');

            if ($application->status === MembershipApplicationStatus::RevisionRequested) {
                $application->revision_reason = $instruction;
                $application->revision_requested_at = $application->revision_requested_at ?? now();
                $application->save();

                return $this->capabilities->issue(
                    $purpose,
                    'membership_application',
                    $application->id,
                    $actor,
                );
            }

            if ($application->status !== MembershipApplicationStatus::UnderReview) {
                throw new InvalidMembershipApplicationTransition(
                    'Permohonan hanya dapat diminta revisi saat sedang ditinjau.',
                );
            }

            $this->lifecycle->transition(
                $application,
                MembershipApplicationStatus::RevisionRequested,
                $actor,
                'staff',
                $instruction,
                function (MembershipApplication $application) use ($instruction): void {
                    $application->revision_reason = $instruction;
                    $application->revision_requested_at = now();
                },
            );

            return $this->capabilities->issue(
                $purpose,
                'membership_application',
                $application->id,
                $actor,
            );
        });
    }
}
