<?php

namespace App\Support\Lapak;

use App\Models\LapakListing;
use Illuminate\Support\Str;

final class LapakListingSlug
{
    public static function fromTitle(string $title, int $lapakId, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'produk';
        }

        return self::unique($base, $lapakId, $ignoreId);
    }

    public static function unique(string $slug, int $lapakId, ?int $ignoreId = null): string
    {
        $normalized = Str::slug($slug);

        if ($normalized === '') {
            $normalized = 'produk';
        }

        $candidate = $normalized;
        $suffix = 2;

        while (self::exists($candidate, $lapakId, $ignoreId)) {
            $candidate = $normalized.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private static function exists(string $slug, int $lapakId, ?int $ignoreId): bool
    {
        return LapakListing::query()
            ->where('lapak_id', $lapakId)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}
