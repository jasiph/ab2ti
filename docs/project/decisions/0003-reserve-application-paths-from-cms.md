# 0003 — Reserve application paths from CMS

Date: 2026-08-19
Status: Accepted

## Context

Public CMS pages are resolved by a catch-all `GET /{slug}` registered last in `routes/web.php`. Nested slugs are supported (`about/team`). The catch-all previously used `where('slug', '.*')`, so first segments such as `login`, `admin`, or `register` could be claimed as CMS content when no more-specific HTTP method/route matched.

Application and framework routes (Fortify, Jetstream, Sanctum, health, locale switcher, `/admin`) must keep winning.

## Decision

Application/framework first-path segments are **reserved**. They cannot be CMS slugs, including nested slugs whose first segment is reserved (`admin/help`, `register/now`).

The canonical list lives in `config/cms.php` and is consumed by:

- the catch-all route constraint (`App\Support\CmsPath`)
- Page create/update validation

`register` remains reserved even though public self-registration is disabled (ADR 0002).

CMS aliases `/about`, `/sermons`, `/events`, and `/contact` are **not** reserved; they are CMS pages with explicit routes.

## Consequences

- CMS remains flexible, including nested pages, but cannot claim system namespaces.
- Explicit application routes continue to be registered before the catch-all.
- GET `/register` is not a CMS page. POST `/register` still does not create users.
- `/sermons` and `/events` are unchanged pending a Phase 1 information-architecture decision.
