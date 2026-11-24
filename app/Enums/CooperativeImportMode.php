<?php

namespace App\Enums;

enum CooperativeImportMode: string
{
    case Append = 'append';
    case ReplaceLegacy = 'replace_legacy';
}
