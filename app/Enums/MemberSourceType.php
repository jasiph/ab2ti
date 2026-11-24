<?php

namespace App\Enums;

enum MemberSourceType: string
{
    case MembershipApplication = 'membership_application';
    case CooperativeLegacyImport = 'cooperative_legacy_import';
}
