<?php

namespace App\Support\Membership;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MembershipIntakeNonce
{
    public const SESSION_KEY = 'membership_intake_token';

    public function ensure(Request $request): string
    {
        $existing = $request->session()->get(self::SESSION_KEY);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $token = (string) Str::uuid();
        $request->session()->put(self::SESSION_KEY, $token);

        return $token;
    }

    public function assertMatches(Request $request, string $token): void
    {
        $sessionToken = $request->session()->get(self::SESSION_KEY);

        if (! is_string($sessionToken) || $sessionToken === '' || ! hash_equals($sessionToken, $token)) {
            throw ValidationException::withMessages([
                'intake_token' => 'Formulir ini sudah tidak berlaku. Muat ulang halaman, lalu kirim lagi.',
            ]);
        }
    }

    public function acquireLock(string $token): bool
    {
        return Cache::add($this->lockKey($token), '1', now()->addMinutes(5));
    }

    public function releaseLock(string $token): void
    {
        Cache::forget($this->lockKey($token));
    }

    public function consume(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    private function lockKey(string $token): string
    {
        return 'membership-intake-nonce:'.$token;
    }
}
