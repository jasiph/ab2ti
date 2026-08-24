<?php

namespace App\Support\SecureCapability;

final class CapabilitySecret
{
    public static function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function digest(string $secret): string
    {
        return hash_hmac('sha256', $secret, (string) config('app.key'));
    }

    public static function matches(string $secret, string $digest): bool
    {
        return hash_equals($digest, self::digest($secret));
    }
}
