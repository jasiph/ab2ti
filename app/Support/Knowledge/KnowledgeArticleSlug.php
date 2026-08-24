<?php

namespace App\Support\Knowledge;

use App\Models\KnowledgeArticle;
use Illuminate\Support\Str;

final class KnowledgeArticleSlug
{
    public static function fromTitle(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'teknologi-tani';
        }

        return self::unique($base, $ignoreId);
    }

    public static function unique(string $slug, ?int $ignoreId = null): string
    {
        $normalized = Str::slug($slug);

        if ($normalized === '') {
            $normalized = 'teknologi-tani';
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
        return KnowledgeArticle::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}
