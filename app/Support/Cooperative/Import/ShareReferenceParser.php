<?php

namespace App\Support\Cooperative\Import;

final class ShareReferenceParser
{
    /**
     * Split a legacy No. Saham value into a reference and optional explicit units.
     *
     * @return array{
     *     raw: ?string,
     *     share_reference: ?string,
     *     explicit_units: ?int,
     *     parseable: bool,
     *     blank: bool
     * }
     */
    public function parse(mixed $value): array
    {
        $raw = is_string($value) || is_numeric($value) ? trim((string) $value) : null;

        if ($raw === null || $raw === '' || $raw === '-') {
            return [
                'raw' => $raw,
                'share_reference' => null,
                'explicit_units' => null,
                'parseable' => true,
                'blank' => true,
            ];
        }

        $compact = preg_replace('/\s+/', ' ', $raw) ?? $raw;

        if (substr_count($compact, '(') > 1 || substr_count($compact, ')') > 1) {
            return $this->invalid($raw);
        }

        if (preg_match('/^(.+?)\s*\((\d+)\)\s*$/', $compact, $matches) === 1) {
            $reference = trim($matches[1]);

            if ($reference === '' || ! $this->looksLikeReference($reference)) {
                return $this->invalid($raw);
            }

            return [
                'raw' => $raw,
                'share_reference' => $reference,
                'explicit_units' => (int) $matches[2],
                'parseable' => true,
                'blank' => false,
            ];
        }

        if (str_contains($compact, '(') || str_contains($compact, ')')) {
            return $this->invalid($raw);
        }

        if (! $this->looksLikeReference($compact)) {
            return $this->invalid($raw);
        }

        return [
            'raw' => $raw,
            'share_reference' => $compact,
            'explicit_units' => null,
            'parseable' => true,
            'blank' => false,
        ];
    }

    /**
     * @return array{raw: string, share_reference: null, explicit_units: null, parseable: bool, blank: bool}
     */
    private function invalid(string $raw): array
    {
        return [
            'raw' => $raw,
            'share_reference' => null,
            'explicit_units' => null,
            'parseable' => false,
            'blank' => false,
        ];
    }

    private function looksLikeReference(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)+$/', $value) === 1;
    }
}
