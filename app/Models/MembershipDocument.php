<?php

namespace App\Models;

use App\Enums\MembershipDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipDocument extends Model
{
    protected $fillable = [
        'membership_application_id',
        'document_type',
        'disk',
        'object_key',
        'original_filename',
        'mime_type',
        'size_bytes',
        'checksum',
        'uploaded_at',
        'uploaded_by_context',
        'uploaded_by_user_id',
        'replaced_at',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => MembershipDocumentType::class,
            'uploaded_at' => 'datetime',
            'replaced_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(MembershipApplication::class, 'membership_application_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function isCurrent(): bool
    {
        return $this->replaced_at === null;
    }
}
