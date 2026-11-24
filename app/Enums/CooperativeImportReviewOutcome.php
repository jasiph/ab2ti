<?php

namespace App\Enums;

enum CooperativeImportReviewOutcome: string
{
    case Corrected = 'corrected';
    case AcceptedAsIs = 'accepted_as_is';
    case ConfirmedDistinct = 'confirmed_distinct';
    case ConfirmedSamePerson = 'confirmed_same_person';
    case AcceptedSharedContact = 'accepted_shared_contact';
    case SourceVerified = 'source_verified';
    case UnresolvedRequiresFollowup = 'unresolved_requires_followup';
    case ConfirmedNoPsnParticipation = 'confirmed_no_psn_participation';
    case ManualReconciliation = 'manual_reconciliation';
}
