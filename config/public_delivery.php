<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Canonical public origin
    |--------------------------------------------------------------------------
    |
    | Authoritative origin for canonical URLs, og:url, and og:image.
    | Do not derive this from the incoming Host header.
    |
    | Empty PUBLIC_DELIVERY_CANONICAL_ORIGIN falls back to APP_URL.
    |
    */

    'canonical_origin' => rtrim((string) env('PUBLIC_DELIVERY_CANONICAL_ORIGIN', env('APP_URL', 'http://localhost')), '/'),

    /*
    |--------------------------------------------------------------------------
    | Site defaults
    |--------------------------------------------------------------------------
    */

    'site_name' => env('APP_NAME', 'AB2TI 2.0'),

    'default_title' => env('APP_NAME', 'AB2TI 2.0'),

    'default_description' => 'Asosiasi Bank Benih dan Teknologi Tani Indonesia (AB2TI).',

    'default_og_image_path' => '/images/og/ab2ti-og-default-v1.jpg',

    'default_og_image_alt' => 'AB2TI',

    'og_image_width' => 1200,

    'og_image_height' => 630,

    'twitter_card' => 'summary_large_image',

    'title_max_length' => 120,

    'description_max_length' => 160,

    /*
    |--------------------------------------------------------------------------
    | Indexability
    |--------------------------------------------------------------------------
    |
    | Public indexable pages use index,follow. Surfaces whose first path
    | segment is reserved in config/cms.php (admin, login, user, …) are
    | never indexable and must not expose resource-specific social previews.
    |
    */

    'indexable_robots' => 'index, follow',

    'private_robots' => 'noindex, nofollow',

];
