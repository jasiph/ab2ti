<?php

namespace App\Support\Cooperative;

final class IdrMoney
{
    public static function format(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
