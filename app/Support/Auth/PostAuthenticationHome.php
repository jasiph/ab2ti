<?php

namespace App\Support\Auth;

use App\Models\User;

final class PostAuthenticationHome
{
    public static function url(?User $user): string
    {
        if ($user?->isPlatformStaff()) {
            return route('admin.dashboard');
        }

        if ($user?->linkedMember() !== null) {
            return route('account.lapak.index');
        }

        return route('welcome');
    }

    public static function isAdministrativePath(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;
        $path = '/'.ltrim((string) $path, '/');

        return $path === '/dashboard' || str_starts_with($path, '/admin');
    }
}
