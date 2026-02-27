<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Congregation extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'unit_number',
        'family_card_number',
        'nik',
        'name',
        'gender',
        'place_of_birth',
        'date_of_birth',
        'blood_type',
        'address',
        'phone',
        'email',
        'marital_status',
        'baptism_date',
        'sidi_date',
        'join_date',
        'status',
        'father_id',
        'mother_id',
        'spouse_id',
        'head_of_family_id',
        'photo_path',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'baptism_date' => 'date',
        'sidi_date' => 'date',
        'join_date' => 'date',
    ];

    // Relationships

    public function father()
    {
        return $this->belongsTo(Congregation::class , 'father_id');
    }

    public function mother()
    {
        return $this->belongsTo(Congregation::class , 'mother_id');
    }

    public function spouse()
    {
        return $this->belongsTo(Congregation::class , 'spouse_id');
    }

    public function headOfFamily()
    {
        return $this->belongsTo(Congregation::class , 'head_of_family_id');
    }

    public function familyMembers()
    {
        return $this->hasMany(Congregation::class , 'head_of_family_id');
    }

    public function getChildrenAttribute()
    {
        return Congregation::where('father_id', $this->id)
            ->orWhere('mother_id', $this->id)
            ->get();
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch($query, $term)
    {
        $term = "%{$term}%";
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', $term)
                ->orWhere('unit_number', 'like', $term)
                ->orWhere('nik', 'like', $term);
        });
    }
}
