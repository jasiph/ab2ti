<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reserved CMS first-path segments
    |--------------------------------------------------------------------------
    |
    | Canonical list of URL first segments that MUST NOT be claimed as CMS
    | page slugs (including nested slugs such as login/help).
    |
    | Routes, Page validation, and tests MUST read this file. Do not copy
    | the segment list elsewhere.
    |
    | Keys are the reserved first segments. Values document why they exist.
    |
    */

    'reserved_first_segments' => [

        'admin' => 'Authenticated admin application namespace (/admin/*).',

        'api' => 'Sanctum API prefix (routes/api.php).',

        'build' => 'Vite production asset directory served from public/build.',

        'dashboard' => 'Authenticated application home (/dashboard).',

        'email' => 'Fortify email-verification URL prefix (kept reserved even if the feature is disabled).',

        'forgot-password' => 'Fortify password-reset request UI.',

        'hot' => 'Vite HMR lockfile/path under public/hot.',

        'keanggotaan' => 'Association membership public family (informational landing + intake).',

        'koperasi' => 'Koperasi Pusat AB2TI public landing (/koperasi).',

        'akses' => 'Secure Capability Access redeem and loginless purpose workspace (/akses/*).',

        'anggota' => 'Reserved so CMS cannot occupy the legacy membership URL family.',

        'publikasi' => 'Publishing hub (/publikasi).',

        'berita' => 'Publishing type family (/berita/{slug}).',

        'kegiatan' => 'Publishing type family (/kegiatan/{slug}).',

        'siaran-pers' => 'Publishing type family (/siaran-pers/{slug}).',

        'liputan-media' => 'Publishing type family (/liputan-media/{slug}).',

        'teknologi-tani' => 'Agricultural Knowledge public family (/teknologi-tani).',

        'lapak' => 'Lapak Anggota public family (/lapak). Admin lives under /admin/lapak; owner workspace under /akun/lapak.',

        'sermons' => 'Retired Presbi alias. Not an AB2TI URL. Must not become a CMS page.',

        'events' => 'Retired Presbi alias. Not an AB2TI URL. Must not become a CMS page.',

        'kontak' => 'Reserved so CMS cannot occupy the Indonesian contact URL family. Public contact remains /contact until an IA change.',

        'language' => 'Locale switcher (/language/{locale}).',

        'login' => 'Fortify login.',

        'logout' => 'Fortify logout.',

        'register' => 'Closed public-registration namespace. Must stay reserved so /register cannot become a CMS page.',

        'reset-password' => 'Fortify password-reset form.',

        'sanctum' => 'Laravel Sanctum CSRF cookie endpoint.',

        'storage' => 'Public filesystem symlink (/storage).',

        'two-factor-challenge' => 'Fortify two-factor challenge.',

        'up' => 'Laravel health endpoint (/up).',

        'user' => 'Jetstream profile, password, 2FA, and API-token routes (/user/*).',

        'akun' => 'Authenticated account workspace (Member account-link + Lapak Saya). Not the public /lapak family.',

    ],

];
