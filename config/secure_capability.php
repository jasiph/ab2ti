<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Purpose registry
    |--------------------------------------------------------------------------
    |
    | Named intents, not Spatie permissions. Unknown purposes are denied.
    | Membership owns membership-application-revision; the platform only
    | stores and validates generic grant mechanics.
    |
    */

    'purposes' => [
        'membership-application-revision' => [
            'target_type' => 'membership_application',
            'ttl_hours' => 168,
        ],
        'member-account-link' => [
            'target_type' => 'member',
            'ttl_hours' => 168,
        ],
    ],

    'target_types' => [
        'membership_application',
        'member',
    ],

    'session_key' => 'capability_grant_id',

];
