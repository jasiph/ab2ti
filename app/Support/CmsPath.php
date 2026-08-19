<?php

namespace App\Support;

class CmsPath
{
    /**
     * @return array<string, string>
     */
    public static function reservedFirstSegments(): array
    {
        /** @var array<string, string> $segments */
        $segments = config('cms.reserved_first_segments', []);

        return $segments;
    }

    /**
     * @return list<string>
     */
    public static function reservedSegmentNames(): array
    {
        return array_keys(self::reservedFirstSegments());
    }

    public static function normalize(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = trim($slug, '/');
        $slug = preg_replace('#/+#', '/', $slug) ?? $slug;

        return $slug;
    }

    public static function firstSegment(string $slug): string
    {
        $normalized = self::normalize($slug);
        $parts = explode('/', $normalized);

        return $parts[0] ?? '';
    }

    public static function isReserved(string $slug): bool
    {
        $first = self::firstSegment($slug);

        return $first !== '' && in_array($first, self::reservedSegmentNames(), true);
    }

    public static function hasValidShape(string $slug): bool
    {
        $normalized = self::normalize($slug);

        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*(?:\/[a-z0-9]+(?:[-_][a-z0-9]+)*)*$/', $normalized);
    }

    public static function catchAllConstraint(): string
    {
        $quoted = array_map(
            static fn (string $segment): string => preg_quote($segment, '/'),
            self::reservedSegmentNames()
        );

        $alternation = implode('|', $quoted);

        return '(?!(?:'.$alternation.')(?:/|$)).+';
    }
}
