<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'content_template',
        'data_schema',
    ];

    protected $casts = [
        'data_schema' => 'array',
    ];

    public function letters()
    {
        return $this->hasMany(Letter::class);
    }
}
