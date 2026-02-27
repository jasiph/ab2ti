<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ArchivedDocument extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'type', // incoming_letter, legal, certificate, report, other
        'reference_number',
        'source',
        'title',
        'description',
        'document_date',
        'received_at',
        'file_path',
        'congregation_id',
        'created_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'received_at' => 'date',
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class , 'created_by');
    }

    public function congregation()
    {
        return $this->belongsTo(Congregation::class);
    }

    // Scopes
    public function scopeSearch($query, $search)
    {
        return $query->where('title', 'like', "%{$search}%")
            ->orWhere('reference_number', 'like', "%{$search}%")
            ->orWhere('source', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%");
    }

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }
}
