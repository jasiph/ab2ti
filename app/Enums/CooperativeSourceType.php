<?php

namespace App\Enums;

enum CooperativeSourceType: string
{
    case LegacyImport = 'legacy_import';
    case Staff = 'staff';
    case ImportReversal = 'import_reversal';
}
