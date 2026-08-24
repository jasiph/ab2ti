<?php

namespace App\Support\Editorial;

use App\Models\BlockTemplate;

final class TemplateBlockRegistry
{
    /**
     * Platform-owned snippet identities. Includes Tiptap's default wrapper
     * label and the seeded BlockTemplate names. New first-class blocks must
     * be added here explicitly — never resolved from user-supplied class names.
     *
     * @var list<string>
     */
    private const PLATFORM_TYPES = [
        'Block Content',
        'Highlight Card (Android Style)',
        'Beautiful Image with Caption',
        'Two Columns Feature',
        'highlight-card',
        'image-caption',
        'two-columns',
    ];

    /**
     * @var list<string>|null
     */
    private ?array $activeTemplateNames = null;

    /**
     * Optional $context (e.g. editorial.cms) is reserved so a later phase can
     * restrict types per domain without a second registry.
     */
    public function allows(string $type, ?string $context = null): bool
    {
        unset($context);

        $normalized = $this->normalizeType($type);

        if ($normalized === null) {
            return false;
        }

        if (in_array($normalized, self::PLATFORM_TYPES, true)) {
            return true;
        }

        return in_array($normalized, $this->activeTemplateNames(), true);
    }

    public function normalizeType(string $type): ?string
    {
        $type = trim($type);

        if ($type === '' || strlen($type) > 80) {
            return null;
        }

        if (str_contains($type, '\\') || str_contains($type, '/') || str_contains($type, '::') || str_contains($type, '.')) {
            return null;
        }

        if (! preg_match('/^[\p{L}\p{N} ()_-]+$/u', $type)) {
            return null;
        }

        return $type;
    }

    /**
     * @return list<string>
     */
    public function platformTypes(): array
    {
        return self::PLATFORM_TYPES;
    }

    /**
     * @return list<string>
     */
    private function activeTemplateNames(): array
    {
        return $this->activeTemplateNames ??= BlockTemplate::query()
            ->where('is_active', true)
            ->pluck('name')
            ->all();
    }
}
