<?php

namespace App\Enums;

enum LapakListingAvailability: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Unavailable => 'Tidak tersedia',
        };
    }
}
