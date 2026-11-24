<?php

namespace App\Support\Cooperative\Import;

final class MoneyParser
{
    /**
     * Parse a whole-rupiah value to integer IDR. Does not round.
     *
     * Safe formats: 1.000.000, Rp 1.000.000, 1.000.000,00, 1,000,000,
     * 1000000, 0, "-", blank. Negative openings and mixed/ambiguous
     * decimal styles are rejected.
     *
     * @return array{amount: ?int, parseable: bool, blank: bool, raw: ?string}
     */
    public function parse(mixed $value): array
    {
        $raw = $this->stringify($value);

        if ($raw === null || $raw === '' || $raw === '-') {
            return $this->blank($raw);
        }

        $normalized = str_replace(["\u{00A0}", ' '], '', $raw);
        $normalized = preg_replace('/^rp/i', '', $normalized) ?? $normalized;

        if ($normalized === '' || $normalized === '-') {
            return $this->blank($raw);
        }

        if (str_starts_with($normalized, '-')) {
            return $this->invalid($raw);
        }

        if (preg_match('/^\d+$/', $normalized) === 1) {
            return $this->amount((int) $normalized, $raw, $normalized === '0');
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $normalized) === 1) {
            return $this->amount((int) str_replace('.', '', $normalized), $raw, false);
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+,(00)$/', $normalized) === 1) {
            $digits = str_replace('.', '', substr($normalized, 0, -3));

            return $this->amount((int) $digits, $raw, false);
        }

        if (preg_match('/^\d+,(00)$/', $normalized) === 1) {
            return $this->amount((int) substr($normalized, 0, -3), $raw, substr($normalized, 0, -3) === '0');
        }

        if (preg_match('/^\d{1,3}(,\d{3})+$/', $normalized) === 1) {
            return $this->amount((int) str_replace(',', '', $normalized), $raw, false);
        }

        return $this->invalid($raw);
    }

    /**
     * @return array{amount: int, parseable: bool, blank: bool, raw: ?string}
     */
    private function blank(?string $raw): array
    {
        return [
            'amount' => 0,
            'parseable' => true,
            'blank' => true,
            'raw' => $raw,
        ];
    }

    /**
     * @return array{amount: null, parseable: bool, blank: bool, raw: ?string}
     */
    private function invalid(string $raw): array
    {
        return [
            'amount' => null,
            'parseable' => false,
            'blank' => false,
            'raw' => $raw,
        ];
    }

    /**
     * @return array{amount: int, parseable: bool, blank: bool, raw: ?string}
     */
    private function amount(int $amount, string $raw, bool $blank): array
    {
        return [
            'amount' => $amount,
            'parseable' => true,
            'blank' => $blank,
            'raw' => $raw,
        ];
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (floor($value) !== $value) {
                return (string) $value;
            }

            return (string) (int) $value;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
