<?php

namespace App\Models;

use App\Enums\MembershipApplicationStatus;
use App\Enums\MembershipDocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MembershipApplication extends Model
{
    /** @use HasFactory<\Database\Factories\MembershipApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'legal_name',
        'email',
        'phone',
        'address',
        'applicant_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => MembershipApplicationStatus::class,
            'submitted_at' => 'datetime',
            'review_started_at' => 'datetime',
            'revision_requested_at' => 'datetime',
            'resubmitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(MembershipApplicationTransition::class)->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MembershipDocument::class);
    }

    public function currentDocuments(): HasMany
    {
        return $this->documents()->whereNull('replaced_at');
    }

    public function member(): HasOne
    {
        return $this->hasOne(Member::class, 'source_application_id');
    }

    public function currentDocument(MembershipDocumentType $type): ?MembershipDocument
    {
        return $this->currentDocuments->first(
            fn (MembershipDocument $document): bool => $document->document_type === $type,
        );
    }

    public function recordTransition(
        ?MembershipApplicationStatus $from,
        MembershipApplicationStatus $to,
        ?User $actor,
        string $actorContext,
        ?string $reason = null,
    ): MembershipApplicationTransition {
        return $this->transitions()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor?->id,
            'actor_context' => $actorContext,
            'reason' => $reason,
        ]);
    }
}
