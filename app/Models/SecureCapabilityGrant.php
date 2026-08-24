<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecureCapabilityGrant extends Model
{
    protected $fillable = [
        'purpose',
        'target_type',
        'target_id',
        'secret_digest',
        'payload',
        'issued_by',
        'issued_at',
        'expires_at',
        'revoked_at',
        'consumed_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'payload' => 'array',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isExpired(?\DateTimeInterface $at = null): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte($at ?? now());
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isLive(?\DateTimeInterface $at = null): bool
    {
        return ! $this->isExpired($at) && ! $this->isRevoked() && ! $this->isConsumed();
    }
}
