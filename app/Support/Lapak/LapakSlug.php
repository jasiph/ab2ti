<?php

namespace App\Support\Lapak;

use App\Models\Lapak;
use Illuminate\Support\Str;

final class LapakSlug
{
    public static function fromName(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'lapak';
        }

        return self::unique($base, $ignoreId);
    }

    public static function unique(string $slug, ?int $ignoreId = null): string
    {
        $normalized = Str::slug($slug);

        if ($normalized === '') {
            $normalized = 'lapak';
        }

        $candidate = $normalized;
        $suffix = 2;

        while (self::exists($candidate, $ignoreId)) {
            $candidate = $normalized.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private static function exists(string $slug, ?int $ignoreId): bool
    {
        return Lapak::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}
