<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Letter extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'letter_template_id',
        'letter_number',
        'content_data',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'security_hash',
    ];

    protected $casts = [
        'content_data' => 'array',
        'approved_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(LetterTemplate::class , 'letter_template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class , 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class , 'approved_by');
    }

    public function signers()
    {
        return $this->hasMany(LetterSigner::class);
    }

    public function getRenderedContentAttribute()
    {
        if (!$this->template)
            return '';

        $content = $this->template->content_template;
        $data = $this->content_data;

        foreach ($data as $key => $value) {
            $content = str_replace("{{ $key }}", $value, $content);
        }

        $content = str_replace("{{ letter_number }}", $this->letter_number ?? '[DRAFT]', $content);

        return $content;
    }
}
