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

        // Group: Media Library
        'media' => [
            'media.view',
            'media.create',
            'media.update',
            'media.delete',
        ],

        // Group: Media Library
        'heroes' => [
            'heroes.view',
            'heroes.create',
            'heroes.update',
            'heroes.delete',
        ],
    ],
];
