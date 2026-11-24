<?php

namespace App\Support\Cooperative\Import;

use App\Enums\CooperativeIdentityClass;

final class IdentityClassifier
{
    /**
     * Classify a legacy identity column. Never writes onto Member.nik.
     *
     * @return array{raw: ?string, class: CooperativeIdentityClass, value: ?string}
     */
    public function classify(mixed $value): array
    {
        $raw = is_string($value) || is_numeric($value) ? trim((string) $value) : null;

        if ($raw === null || $raw === '' || $raw === '-') {
            return [
                'raw' => $raw,
                'class' => CooperativeIdentityClass::Other,
                'value' => null,
            ];
        }

        $compact = preg_replace('/\s+/', '', $raw) ?? $raw;

        if (preg_match('/^\d{16}$/', $compact) === 1) {
            return [
                'raw' => $raw,
                'class' => CooperativeIdentityClass::ValidNik,
                'value' => $compact,
            ];
        }

        if ($this->looksLikeAb2tiIdentifier($compact) || $this->looksLikeMembershipNumber($compact)) {
            return [
                'raw' => $raw,
                'class' => CooperativeIdentityClass::Ab2tiIdentifier,
                'value' => $compact,
            ];
        }

        return [
            'raw' => $raw,
            'class' => CooperativeIdentityClass::Other,
            'value' => $raw,
        ];
    }

    private function looksLikeAb2tiIdentifier(string $value): bool
    {
        return preg_match('#^AB2TI/\d+/[IVXLCDM]+/\d+$#i', $value) === 1;
    }

    private function looksLikeMembershipNumber(string $value): bool
    {
        return preg_match('/^MBR-[A-Z0-9-]+$/i', $value) === 1;
    }
}
