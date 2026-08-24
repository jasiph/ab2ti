<?php

namespace App\Models;

use App\Enums\MembershipApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipApplicationTransition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'membership_application_id',
        'from_status',
        'to_status',
        'actor_user_id',
        'actor_context',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => MembershipApplicationStatus::class,
            'to_status' => MembershipApplicationStatus::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $transition): void {
            $transition->created_at ??= now();
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(MembershipApplication::class, 'membership_application_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
