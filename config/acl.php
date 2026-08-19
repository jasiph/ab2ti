<?php

return [
    'permissions' => [
        // Group: Users Management
        'users' => [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
        ],

        // Group: Role Management
        'roles' => [
            'roles.manage',
        ],

        // Group: Content Management System
        'cms' => [
            'cms.view',
            'cms.create',
            'cms.update',
            'cms.delete',
        ],

        // Group: System Settings
        'settings' => [
            'access settings',
        ],

        // Group: Culture / Budaya
        'culture' => [
            'culture.view',
            'culture.create',
            'culture.update',
            'culture.delete'
        ],

        // Group: Media Library
        'media' => [
            'media.view',
            'media.create',
            'media.update',
            'media.delete'
        ],

        // Group: Media Library
        'heroes' => [
            'heroes.view',
            'heroes.create',
            'heroes.update',
            'heroes.delete'
        ],

        // Group: Artikel
        'artikel' => [
            'artikel.view',
            'artikel.create',
            'artikel.update',
            'artikel.delete'
        ],
    ],
];
