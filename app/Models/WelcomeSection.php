<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WelcomeSection extends Model
{
    protected $fillable = [
        'title',
        'content',
        'secondary_content',
        'image_url',
        'cta_label',
        'cta_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
