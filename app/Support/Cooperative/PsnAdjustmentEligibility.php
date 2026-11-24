<?php

namespace App\Support\Cooperative;

use App\Enums\CooperativeImportReviewCategory;
use App\Enums\CooperativeImportReviewStatus;
use App\Models\CooperativeImportReviewItem;
use App\Models\CooperativeMembership;
use Illuminate\Database\Eloquent\Builder;

final class PsnAdjustmentEligibility
{
    public static function hasUnresolvedPsn(CooperativeMembership $membership): bool
    {
        $memberId = $membership->member_id;
        if ($memberId === null) {
            return false;
        }

        return CooperativeImportReviewItem::query()
            ->where('category', CooperativeImportReviewCategory::PsnUnconfirmed)
            ->where('status', CooperativeImportReviewStatus::Pending)
            ->whereHas('importRow', function (Builder $query) use ($memberId): void {
                $query->where('created_member_id', $memberId)
                    ->orWhere('matched_member_id', $memberId);
            })
            ->exists();
    }

    public static function ordinaryAdjustmentBlock(CooperativeMembership $membership): ?string
    {
        if (! $membership->isActive()) {
            return 'inactive';
        }

        if (self::hasUnresolvedPsn($membership)) {
            return 'unresolved';
        }

        return null;
    }

    public static function allowsOrdinaryAdjustment(CooperativeMembership $membership): bool
    {
        return self::ordinaryAdjustmentBlock($membership) === null;
    }
}
