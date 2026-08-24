<?php

namespace App\Support\Membership;

use App\Models\MembershipApplication;
use Illuminate\Support\Str;

final class MembershipReferenceCode
{
    public static function generate(): string
    {
        $prefix = (string) config('membership.reference_code_prefix', 'APP');

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $code = $prefix.'-'.Str::upper(Str::random(10));

            if (! MembershipApplication::query()->where('reference_code', $code)->exists()) {
                return $code;
            }
        }

        return $prefix.'-'.Str::upper(Str::ulid());
    }
}
