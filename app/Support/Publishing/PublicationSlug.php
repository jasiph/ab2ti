<?php

namespace App\Support\Publishing;

use App\Models\Publication;
use Illuminate\Support\Str;

final class PublicationSlug
{
    public static function fromTitle(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'publikasi';
        }

        return self::unique($base, $ignoreId);
    }

    public static function unique(string $slug, ?int $ignoreId = null): string
    {
        $normalized = Str::slug($slug);

        if ($normalized === '') {
            $normalized = 'publikasi';
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
        return Publication::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}
