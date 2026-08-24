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

        // Group: Membership applications (staff queue)
        'membership-applications' => [
            'membership-applications.view',
            'membership-applications.review',
            'membership-applications.approve',
            'membership-applications.reject',
            'membership-applications.request-revision',
        ],

        // Group: AB2TI members (domain identity, not a Spatie role)
        'membership-members' => [
            'membership-members.view',
            'membership-members.update',
        ],

        // Group: Private membership documents (not media.*)
        'membership-documents' => [
            'membership-documents.view',
            'membership-documents.manage',
        ],

        // Group: Publishing
        'publishing-publications' => [
            'publishing-publications.view',
            'publishing-publications.create',
            'publishing-publications.update',
            'publishing-publications.publish',
            'publishing-publications.delete',
        ],

        // Group: Agricultural Knowledge
        'knowledge-articles' => [
            'knowledge-articles.view',
            'knowledge-articles.create',
            'knowledge-articles.update',
            'knowledge-articles.publish',
            'knowledge-articles.delete',
        ],

        // Group: Lapak Anggota (classified marketplace; not e-commerce)
        'lapak' => [
            'lapak.view',
            'lapak.manage',
            'lapak.moderate',
        ],
    ],
];
