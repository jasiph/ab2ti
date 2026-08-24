<?php

namespace App\Enums;

enum LapakPriceMode: string
{
    case Fixed = 'fixed';
    case From = 'from';
    case Negotiable = 'negotiable';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Harga tetap',
            self::From => 'Mulai dari',
            self::Negotiable => 'Nego / hubungi penjual',
            self::Hidden => 'Harga tidak ditampilkan',
        };
    }

    public function requiresAmount(): bool
    {
        return $this === self::Fixed || $this === self::From;
    }
}
