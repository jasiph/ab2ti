<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class IncomingLetter extends Model implements HasMedia
{
    use HasFactory, HasUuids, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'sender',
        'reference_number',
        'received_at',
        'subject',
        'file_path',
        'disposition',
        'created_by',
    ];

    protected $casts = [
        'received_at' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class , 'created_by');
    }
}
