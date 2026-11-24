<?php

namespace App\Enums;

enum CooperativeIdentityClass: string
{
    case ValidNik = 'valid_nik';
    case Ab2tiIdentifier = 'ab2ti_identifier';
    case Other = 'other';
}
