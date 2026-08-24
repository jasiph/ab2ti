<?php

namespace App\Enums;

enum MembershipDocumentType: string
{
    case ApplicantPhoto = 'applicant_photo';
    case IdentityDocument = 'identity_document';
    case PaymentProof = 'payment_proof';
}
