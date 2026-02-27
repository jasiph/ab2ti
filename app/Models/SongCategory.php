<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SongCategory extends Model
{
    use HasFactory;

    protected $table = 'song_categories';

    protected $fillable = [
        'name',
        'slug',
        'type',
    ];

    public function songs()
    {
        return $this->belongsToMany(Song::class , 'song_category_pivot', 'category_id', 'song_id');
    }
}
