<?php

namespace App\Support\SecureCapability;

use App\Models\SecureCapabilityGrant;
use App\Models\User;
use DateTimeInterface;
use InvalidArgumentException;

final class IssuedCapability
{
    public function __construct(
        public SecureCapabilityGrant $grant,
        public string $plainSecret,
    ) {}
}

final class SecureCapabilityAccess
{
    /**
     * @param  array<string, mixed>|null  $payload  Optional invitation context (not extra authority).
     */
    public function issue(
        string $purpose,
        string $targetType,
        int $targetId,
        User $issuer,
        ?DateTimeInterface $expiresAt = null,
        ?array $payload = null,
    ): IssuedCapability {
        $definition = $this->purposeDefinition($purpose);

        if (($definition['target_type'] ?? null) !== $targetType) {
            throw new InvalidArgumentException('Secure capability target type is not allowed for this purpose.');
        }

        if (! in_array($targetType, config('secure_capability.target_types', []), true)) {
            throw new InvalidArgumentException('Secure capability target type is not allow-listed.');
        }

        $this->revokeLiveSiblings($purpose, $targetType, $targetId);

        $secret = CapabilitySecret::generate();
        $hours = (int) ($definition['ttl_hours'] ?? 168);

        $grant = SecureCapabilityGrant::query()->create([
            'purpose' => $purpose,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'secret_digest' => CapabilitySecret::digest($secret),
            'payload' => $payload,
            'issued_by' => $issuer->id,
            'issued_at' => now(),
            'expires_at' => $expiresAt ?? now()->addHours($hours),
        ]);

        return new IssuedCapability($grant, $secret);
    }

    public function findLiveBySecret(string $secret): ?SecureCapabilityGrant
    {
        if ($secret === '') {
            return null;
        }

        $grant = SecureCapabilityGrant::query()
            ->where('secret_digest', CapabilitySecret::digest($secret))
            ->first();

        if ($grant === null || ! $grant->isLive()) {
            return null;
        }

        return $grant;
    }

    public function findLiveById(int $id): ?SecureCapabilityGrant
    {
        $grant = SecureCapabilityGrant::query()->find($id);

        if ($grant === null || ! $grant->isLive()) {
            return null;
        }

        return $grant;
    }

    public function assertUsable(
        SecureCapabilityGrant $grant,
        string $purpose,
        string $targetType,
        int $targetId,
    ): bool {
        if (! $grant->isLive()) {
            return false;
        }

        if ($grant->purpose !== $purpose) {
            return false;
        }

        if ($grant->target_type !== $targetType || (int) $grant->target_id !== $targetId) {
            return false;
        }

        $definition = config('secure_capability.purposes.'.$purpose);

        return is_array($definition) && ($definition['target_type'] ?? null) === $targetType;
    }

    public function touchLastUsed(SecureCapabilityGrant $grant): void
    {
        $grant->last_used_at = now();
        $grant->save();
    }

    public function consume(SecureCapabilityGrant $grant): void
    {
        if ($grant->consumed_at === null) {
            $grant->consumed_at = now();
            $grant->save();
        }
    }

    public function revoke(SecureCapabilityGrant $grant): void
    {
        if ($grant->revoked_at === null) {
            $grant->revoked_at = now();
            $grant->save();
        }
    }

    public function revokeLiveSiblings(string $purpose, string $targetType, int $targetId): void
    {
        SecureCapabilityGrant::query()
            ->where('purpose', $purpose)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->whereNull('revoked_at')
            ->whereNull('consumed_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function purposeDefinition(string $purpose): array
    {
        $definition = config('secure_capability.purposes.'.$purpose);

        if (! is_array($definition)) {
            throw new InvalidArgumentException('Unknown secure capability purpose.');
        }

        return $definition;
    }
}
