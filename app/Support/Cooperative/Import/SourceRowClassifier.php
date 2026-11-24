<?php

namespace App\Support\Cooperative\Import;

final class SourceRowClassifier
{
    /**
     * @var list<string>
     */
    private const FOOTER_LABELS = [
        'laki-laki',
        'laki laki',
        'perempuan',
        'total',
        'jumlah',
        'grand total',
        'jumlah total',
    ];

    /**
     * @param  array<string, mixed>  $raw
     */
    public function isMemberRow(array $raw): bool
    {
        $name = $this->normalizedName($raw['name'] ?? null);

        if ($this->isFooterLabel($name)) {
            return false;
        }

        $number = $this->numericNo($raw['row_no'] ?? null);

        if ($number !== null) {
            return $name !== null || $this->hasIdentityHint($raw);
        }

        return $name !== null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function hasIdentityHint(array $raw): bool
    {
        foreach (['email', 'phone', 'identity', 'share'] as $key) {
            $value = trim((string) ($raw[$key] ?? ''));
            if ($value !== '' && $value !== '-') {
                return true;
            }
        }

        return false;
    }

    private function numericNo(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_float($value)) {
            if (floor($value) !== $value || $value <= 0) {
                return null;
            }

            return (int) $value;
        }

        $text = trim((string) $value);

        if (preg_match('/^\d+$/', $text) !== 1) {
            return null;
        }

        $number = (int) $text;

        return $number > 0 ? $number : null;
    }

    private function normalizedName(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '' || $text === '-') {
            return null;
        }

        $collapsed = preg_replace('/\s+/', ' ', $text) ?? $text;

        return mb_strtolower($collapsed);
    }

    private function isFooterLabel(?string $name): bool
    {
        return $name !== null && in_array($name, self::FOOTER_LABELS, true);
    }
}
