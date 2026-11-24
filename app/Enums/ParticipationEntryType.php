<?php

namespace App\Enums;

enum ParticipationEntryType: string
{
    case OpeningBalance = 'opening_balance';
    case Adjustment = 'adjustment';
}
