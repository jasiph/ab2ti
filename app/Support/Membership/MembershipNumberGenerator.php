<?php

namespace App\Support\Membership;

use App\Models\Member;
use Illuminate\Support\Str;

final class MembershipNumberGenerator
{
    /**
     * Produce a unique opaque membership number.
     *
     * This is a technical uniqueness key, not a product-approved card format.
     */
    public static function generate(): string
    {
        $prefix = (string) config('membership.membership_number_prefix', 'MBR');

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $number = $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));

            if (! Member::query()->where('membership_number', $number)->exists()) {
                return $number;
            }
        }

        return $prefix.'-'.Str::upper(Str::ulid());
    }
}
