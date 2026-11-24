<?php

namespace App\Support\Cooperative\Import;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class DateRangeParser
{
    /**
     * Parse a legacy date or date range without inventing a single DATE.
     * Indonesian source dates are d/m/Y. ISO Y-m-d is accepted as unambiguous.
     *
     * @return array{
     *     raw: ?string,
     *     parseable: bool,
     *     uncertain: bool,
     *     start: ?string,
     *     end: ?string
     * }
     */
    public function parse(mixed $value): array
    {
        if ($value instanceof DateTimeInterface) {
            return [
                'raw' => $value->format('Y-m-d'),
                'parseable' => true,
                'uncertain' => false,
                'start' => $value->format('Y-m-d'),
                'end' => null,
            ];
        }

        $raw = is_string($value) || is_numeric($value) ? trim((string) $value) : null;

        if ($raw === null || $raw === '' || $raw === '-') {
            return [
                'raw' => $raw,
                'parseable' => true,
                'uncertain' => false,
                'start' => null,
                'end' => null,
            ];
        }

        $compact = preg_replace('/\s+/', '', $raw) ?? $raw;

        if (preg_match('#^(\d{1,2})-(\d{1,2})/(\d{1,2})/(\d{4})$#', $compact, $matches) === 1) {
            $start = $this->calendarDate((int) $matches[1], (int) $matches[3], (int) $matches[4]);
            $end = $this->calendarDate((int) $matches[2], (int) $matches[3], (int) $matches[4]);

            if ($start === null || $end === null) {
                return $this->uncertain($raw);
            }

            return [
                'raw' => $raw,
                'parseable' => true,
                'uncertain' => false,
                'start' => $start,
                'end' => $end,
            ];
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $compact, $matches) === 1) {
            $date = $this->calendarDate((int) $matches[1], (int) $matches[2], (int) $matches[3]);

            if ($date === null) {
                return $this->uncertain($raw);
            }

            return [
                'raw' => $raw,
                'parseable' => true,
                'uncertain' => false,
                'start' => $date,
                'end' => null,
            ];
        }

        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $compact, $matches) === 1) {
            $date = $this->calendarDate((int) $matches[3], (int) $matches[2], (int) $matches[1]);

            if ($date === null) {
                return $this->uncertain($raw);
            }

            return [
                'raw' => $raw,
                'parseable' => true,
                'uncertain' => false,
                'start' => $date,
                'end' => null,
            ];
        }

        return $this->uncertain($raw);
    }

    /**
     * @return array{raw: string, parseable: bool, uncertain: bool, start: null, end: null}
     */
    private function uncertain(string $raw): array
    {
        return [
            'raw' => $raw,
            'parseable' => false,
            'uncertain' => true,
            'start' => null,
            'end' => null,
        ];
    }

    private function calendarDate(int $day, int $month, int $year): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day)->toDateString();
    }
}
