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

        // Group: Song Catalog
        'songs' => [
            'songs.view',
            'songs.create',
            'songs.update',
            'songs.delete',
            // Categories
            'song_categories.view',
            'song_categories.create',
            'song_categories.update',
            'song_categories.delete',
        ],

        // Group: Artikel
        'artikel' => [
            'artikel.view',
            'artikel.create',
            'artikel.update',
            'artikel.delete'
        ],

        // Group: Congregation Management
        'congregations' => [
            'congregations.view',
            'congregations.create',
            'congregations.update',
            'congregations.delete'
        ],

        // Group: Document Archive
        'documents' => [
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.delete'
        ]
    ],
];
