<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Song extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'alternative_title',
        'key',
        'tempo',
        'time_signature',
        'lyrics',
        'content',
        'lyric_author',
        'music_author',
        'copyright',
        'youtube_link',
    ];

    protected $casts = [
        'content' => 'array',
        'tempo' => 'integer',
    ];

    public function categories()
    {
        return $this->belongsToMany(SongCategory::class , 'song_category_pivot', 'song_id', 'category_id');
    }

    public function relatedSongs()
    {
        return $this->belongsToMany(Song::class , 'song_relations', 'parent_song_id', 'child_song_id')
            ->withPivot('type', 'order')
            ->withTimestamps();
    }

    // Inverse relationship for related songs (where this song is the child)
    public function parentSongs()
    {
        return $this->belongsToMany(Song::class , 'song_relations', 'child_song_id', 'parent_song_id')
            ->withPivot('type', 'order')
            ->withTimestamps();
    }
}
