<?php

namespace App\Models;

use App\Enums\MemberStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Member extends Model
{
    /** @use HasFactory<\Database\Factories\MemberFactory> */
    use HasFactory;

    /**
     * Contact snapshot fields only. Identity keys (membership_number,
     * source_application_id, status, recognized_at, created_by) and the
     * deferred card_issued flag are written by named Actions, not HTTP
     * mass assignment.
     */
    protected $fillable = [
        'legal_name',
        'email',
        'phone',
        'address',
    ];

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'recognized_at' => 'datetime',
            'linked_at' => 'datetime',
            'card_issued' => 'boolean',
            'card_issued_at' => 'datetime',
        ];
    }

    public function sourceApplication(): BelongsTo
    {
        return $this->belongsTo(MembershipApplication::class, 'source_application_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lapak(): HasOne
    {
        return $this->hasOne(Lapak::class);
    }

    public function isLinkedToAccount(): bool
    {
        return $this->user_id !== null;
    }
}
