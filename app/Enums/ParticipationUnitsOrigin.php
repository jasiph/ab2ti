<?php

namespace App\Enums;

enum ParticipationUnitsOrigin: string
{
    case Explicit = 'explicit';
    case Inferred = 'inferred';
    case ManualReconciliation = 'manual_reconciliation';
    case None = 'none';
}
