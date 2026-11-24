<?php

namespace App\Enums;

enum CooperativeMatchMethod: string
{
    case NikExact = 'nik_exact';
    case MemberIdentifierExact = 'member_identifier_exact';
    case EmailExact = 'email_exact';
    case PhoneExact = 'phone_exact';
    case NameWithSupport = 'name_with_support';
    case Manual = 'manual';
    case ProposedLegacyCreate = 'proposed_legacy_create';
    case Unmatched = 'unmatched';
    case Ambiguous = 'ambiguous';
}
