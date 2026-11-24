<?php

namespace App\Support\Membership;

use App\Enums\MembershipApplicationStatus;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipApplicationTransition;
use App\Models\MembershipDocument;
use App\Models\User;
use App\Support\Cooperative\CooperativeViews;

final class MembershipViews
{
    /**
     * @return array<string, mixed>
     */
    public static function applicationListItem(MembershipApplication $application): array
    {
        return [
            'id' => $application->id,
            'reference_code' => $application->reference_code,
            'legal_name' => $application->legal_name,
            'email' => $application->email,
            'status' => $application->status->value,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function applicationDetail(MembershipApplication $application, User $actor): array
    {
        $application->loadMissing([
            'currentDocuments',
            'transitions.actor',
            'reviewer',
            'approver',
            'rejector',
            'member',
        ]);

        return [
            'id' => $application->id,
            'reference_code' => $application->reference_code,
            'legal_name' => $application->legal_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'address' => $application->address,
            'applicant_notes' => $application->applicant_notes,
            'staff_notes' => $application->staff_notes,
            'status' => $application->status->value,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'review_started_at' => $application->review_started_at?->toIso8601String(),
            'reviewed_by_name' => $application->reviewer?->name,
            'approved_at' => $application->approved_at?->toIso8601String(),
            'approved_by_name' => $application->approver?->name,
            'rejected_at' => $application->rejected_at?->toIso8601String(),
            'rejected_by_name' => $application->rejector?->name,
            'rejection_reason' => $application->rejection_reason,
            'revision_reason' => $application->revision_reason,
            'revision_requested_at' => $application->revision_requested_at?->toIso8601String(),
            'resubmitted_at' => $application->resubmitted_at?->toIso8601String(),
            'member_id' => $application->member?->id,
            'documents' => $application->currentDocuments
                ->map(fn (MembershipDocument $document) => self::documentMeta($document))
                ->values()
                ->all(),
            'history' => $application->transitions
                ->map(fn (MembershipApplicationTransition $transition) => self::transitionItem($transition))
                ->values()
                ->all(),
            'available_actions' => self::availableActions($application, $actor),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function documentMeta(MembershipDocument $document): array
    {
        return [
            'id' => $document->id,
            'document_type' => $document->document_type->value,
            'original_filename' => $document->original_filename,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'uploaded_at' => $document->uploaded_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function transitionItem(MembershipApplicationTransition $transition): array
    {
        return [
            'id' => $transition->id,
            'from_status' => $transition->from_status?->value,
            'to_status' => $transition->to_status->value,
            'actor_name' => $transition->actor?->name,
            'actor_context' => $transition->actor_context,
            'reason' => $transition->reason,
            'created_at' => $transition->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function memberListItem(Member $member): array
    {
        return [
            'id' => $member->id,
            'membership_number' => $member->membership_number,
            'legal_name' => $member->legal_name,
            'email' => $member->email,
            'status' => $member->status->value,
            'recognized_at' => $member->recognized_at?->toIso8601String(),
            'cooperative_badge' => $member->isActiveCooperativeMember(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function memberSummary(Member $member): array
    {
        $member->loadMissing(['sourceApplication', 'creator', 'cooperativeMembership']);

        return [
            'id' => $member->id,
            'membership_number' => $member->membership_number,
            'status' => $member->status->value,
            'legal_name' => $member->legal_name,
            'email' => $member->email,
            'phone' => $member->phone,
            'address' => $member->address,
            'recognized_at' => $member->recognized_at?->toIso8601String(),
            'created_by_name' => $member->creator?->name,
            'account_link' => AccountLinkStatus::for($member),
            'cooperative' => CooperativeViews::badgeForMember($member),
            'source_application' => $member->sourceApplication === null ? null : [
                'id' => $member->sourceApplication->id,
                'reference_code' => $member->sourceApplication->reference_code,
                'status' => $member->sourceApplication->status->value,
            ],
        ];
    }

    /**
     * UI convenience only. Server Actions remain the security boundary.
     *
     * @return list<string>
     */
    public static function availableActions(MembershipApplication $application, User $actor): array
    {
        $actions = [];

        if ($application->status === MembershipApplicationStatus::Submitted && $actor->can('review', $application)) {
            $actions[] = 'review';
        }

        if ($application->status === MembershipApplicationStatus::UnderReview) {
            if ($actor->can('approve', $application)) {
                $actions[] = 'approve';
            }
            if ($actor->can('reject', $application)) {
                $actions[] = 'reject';
            }
            if ($actor->can('requestRevision', $application)) {
                $actions[] = 'request_revision';
            }
        }

        if (
            $application->status === MembershipApplicationStatus::RevisionRequested
            && $actor->can('requestRevision', $application)
        ) {
            $actions[] = 'reissue_revision';
        }

        return $actions;
    }
}
