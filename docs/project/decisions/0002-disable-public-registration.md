# 0002 — Disable public self-registration

Date: 2026-08-19
Status: Accepted

## Context

The imported kernel enabled Fortify public registration. AB2TI 2.0 has no public-user account lifecycle yet. Combined with previously UI-only admin authorization, open sign-up would allow anyone to create an authenticated session and (before Phase 0C-A) reach admin URLs.

## Decision

Public self-registration is **disabled** via Fortify (`Features::registration()` removed from `config/fortify.php`).

User provisioning / invitation onboarding is a future AB2TI domain decision. This change does not introduce a replacement workflow.

Email verification (`MustVerifyEmail`) remains **deferred**. Fortify may still expose verification routes; they are not required for admin access.

## Consequences

- `GET /register` and `POST /register` are not registered.
- Public UI must not call `route('register')` unless `canRegister` / `Route::has('register')` is true.
- Login, password reset, profile, 2FA, and existing accounts remain unchanged.
- Re-enabling registration requires an explicit product/security decision and a superseding ADR.
