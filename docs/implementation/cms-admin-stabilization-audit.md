# CMS admin stabilization audit

Date: 2026-08-22  
Scope: restore the retained CMS/admin content-management surface. Not Phase 4C.

## Reported failures

Admin → Block Templates, Hero Management, and File Management failed when opening create (and equivalent GET `/admin/{resource}/create` URLs). CMS Pages create failed the same way. Application log:

- `Call to undefined method FileManagerController::show()`
- `Call to undefined method PageController::show()`
- `Call to undefined method BlockTemplateController::show()`
- Hero `show()` existed but returned an empty non-Inertia response

## Root cause

Permission-split `Route::resource(..., only: ['index', 'show'])` was registered **before** `only: ['create', 'store']`. Laravel therefore matched `GET /admin/files/create` (and pages/block-templates/heroes) as `show` with `{id} = "create"`.

The same unused `show`/`create`/`edit` verbs were registered for Users and Roles even though those screens use modals.

## Fixes

- Register only implemented resource verbs for files, pages, block-templates, heroes, users, and roles.
- Hero status uses `PATCH admin.heroes.toggle` so the index projection cannot wipe copy fields via `update`.
- File restore JSON includes a presented `file` for the undo toast; destroy uses `File` binding; preview modal uses named routes.
- Welcome Section added to CMS navigation (`cms.view`).
- Block template picker uses canonical `BaseModal`; Tiptap `setContent` uses the v3 options object.
- `BaseList` / Block Template index paginate through imported `BasePagination`.

## Regression tests

- `tests/Feature/Admin/CmsAdminSurfaceTest.php`
- `tests/Feature/Admin/BlockTemplateAdminTest.php`
- `tests/Feature/Admin/HeroAdminTest.php`
- extensions in `tests/Feature/Admin/FileManagerTest.php`

## Deferred

- Page builder; comments/likes/bookmarks; newsletter; Lapak; Membership changes; dedicated File edit form (never implemented).
