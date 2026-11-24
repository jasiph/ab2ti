<?php

namespace App\Enums;

enum CooperativeMemberResolution: string
{
    case Existing = 'existing';
    case ProposedLegacyCreate = 'proposed_legacy_create';
    case Manual = 'manual';
    case Ambiguous = 'ambiguous';
    case Invalid = 'invalid';
}
