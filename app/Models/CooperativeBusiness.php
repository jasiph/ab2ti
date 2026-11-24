<?php

namespace App\Models;

use App\Enums\CooperativeBusinessStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CooperativeBusiness extends Model
{
    /** @use HasFactory<\Database\Factories\CooperativeBusinessFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'status',
        'participation_unit_name',
        'current_unit_value',
    ];

    protected function casts(): array
    {
        return [
            'status' => CooperativeBusinessStatus::class,
            'current_unit_value' => 'integer',
        ];
    }

    public function participations(): HasMany
    {
        return $this->hasMany(BusinessParticipation::class);
    }

    public function isPsn(): bool
    {
        return $this->code === (string) config('cooperative.psn_code', 'PSN');
    }

    public function publicLabel(): string
    {
        return $this->code.' — '.$this->name;
    }

    public function scopePsn(Builder $query): Builder
    {
        return $query->where('code', (string) config('cooperative.psn_code', 'PSN'));
    }

    public static function psn(): self
    {
        return static::query()->psn()->firstOrFail();
    }
}
