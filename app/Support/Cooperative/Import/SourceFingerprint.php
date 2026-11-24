<?php

namespace App\Support\Cooperative\Import;

final class SourceFingerprint
{
    /**
     * Stable identity of a legacy source row, independent of filename.
     *
     * @param  array<string, mixed>  $normalized
     */
    public function fromNormalized(array $normalized): string
    {
        $payload = [
            'identity_class' => $normalized['identity_class'] ?? null,
            'identity_value' => $normalized['identity_value'] ?? null,
            'email' => $normalized['email'] ?? null,
            'phone' => $normalized['phone_normalized'] ?? null,
            'name' => mb_strtolower(trim((string) ($normalized['name'] ?? ''))),
            'principal' => $normalized['principal_amount'] ?? null,
            'voluntary' => $normalized['voluntary_amount'] ?? null,
            'total' => $normalized['source_total'] ?? null,
            'share' => $normalized['share_reference'] ?? null,
            'explicit_units' => $normalized['explicit_units'] ?? null,
        ];

        return hash('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
