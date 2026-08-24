<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Technical identifiers
    |--------------------------------------------------------------------------
    |
    | These prefixes produce unique opaque strings. They are not a product-
    | approved membership-card number format (Phase 1F: format is feature
    | policy / still open).
    |
    */

    'reference_code_prefix' => 'APP',

    'membership_number_prefix' => 'MBR',

    /*
    |--------------------------------------------------------------------------
    | Future Secure Capability Grant purpose
    |--------------------------------------------------------------------------
    |
    | Named intent for a later platform grant (Phase 1E-A). This is not a
    | Spatie permission, not a User, and not a token stored on the
    | application row. Public revision is not implemented in Phase 3A.
    |
    */

    'revision_capability_purpose' => 'membership-application-revision',

    'account_link_capability_purpose' => 'member-account-link',

    /*
    |--------------------------------------------------------------------------
    | Private documents
    |--------------------------------------------------------------------------
    |
    | Stored on the dedicated private disk. Types are the Phase 1F set only.
    | Whether any document is mandatory on submit remains an open product
    | question; Phase 2A accepts applications without files.
    |
    */

    'documents_disk' => 'membership_documents',

    'max_document_kilobytes' => 5120,

    'document_mimes' => [
        'applicant_photo' => ['jpg', 'jpeg', 'png', 'webp'],
        'identity_document' => ['jpg', 'jpeg', 'png', 'pdf'],
        'payment_proof' => ['jpg', 'jpeg', 'png', 'pdf'],
    ],

];
