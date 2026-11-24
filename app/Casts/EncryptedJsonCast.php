<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Encrypts JSON at rest as a JSON wrapper so MySQL json columns stay valid.
 * Legacy plaintext JSON arrays remain readable.
 *
 * @implements CastsAttributes<array<string, mixed>|null, array<string, mixed>|null>
 */
final class EncryptedJsonCast implements CastsAttributes
{
    private const WRAP_KEY = '_encrypted';

    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                return $this->decryptPayload((string) $value);
            }
        }

        if (! is_array($value)) {
            return null;
        }

        if ($this->isWrapped($value)) {
            return $this->decryptPayload($value[self::WRAP_KEY]);
        }

        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode([
            self::WRAP_KEY => Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR)),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function isWrapped(array $value): bool
    {
        return array_keys($value) === [self::WRAP_KEY] && is_string($value[self::WRAP_KEY]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decryptPayload(string $payload): ?array
    {
        try {
            $json = json_decode(Crypt::decryptString($payload), true);

            return is_array($json) ? $json : null;
        } catch (Throwable) {
            $json = json_decode($payload, true);

            return is_array($json) ? $json : null;
        }
    }
}
