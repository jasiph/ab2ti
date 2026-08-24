# AB2TI 2.0 — Phase 2E Platform Hardening & Operational Readiness

**Status:** Implemented  
**Date:** 2026-08-21  
**Does not start:** Phase 3, Koperasi, Community, or new business features.

This phase closes known platform debt so Phase 2 can be assessed for formal closure. It is not a feature-growth slice.

---

## 1. Phase result

Branded HTML errors exist for 403, 404, 419, and production 500 with correct HTTP status codes. ACL sync remains additive; `--clean` is explicit, transactional, and idempotent; `--dry-run` reports without writing. Simple Fortify submit buttons now use `BaseButton`. Jetstream security confirmations were kept. `BaseModal` now traps focus, restores it, and honors non-dismissible mode. No Koperasi/Community work.

---

## 2. Files created/modified

**Created**

- `app/Support/Http/ApplicationErrorPage.php`
- `resources/js/Pages/Errors/Show.vue`
- `tests/Unit/Http/ApplicationErrorPageTest.php`
- `tests/Feature/Platform/ApplicationErrorPageTest.php`
- `tests/Feature/Acl/AclSyncCommandTest.php`
- `docs/implementation/phase-2e-platform-hardening-operational-readiness.md`

**Modified**

- `bootstrap/app.php`
- `app/Console/Commands/SyncPermissions.php`
- `routes/web.php` (PHPUnit-only error trigger routes)
- `resources/js/Components/Base/BaseModal.vue`
- `resources/js/Components/ConfirmDialog.vue`
- `resources/js/Components/FileManager/FilePickerModal.vue`
- Auth: `Login`, `ForgotPassword`, `ResetPassword`, `ConfirmPassword`, `VerifyEmail`, `TwoFactorChallenge`, `Register`
- Profile: `UpdatePasswordForm`, `UpdateProfileInformationForm` (Save only)
- `docs/project/ARCHITECTURE_BASELINE.md`

---

## 3. Error handling architecture

Inspected first: `bootstrap/app.php` `withExceptions` was empty. No Blade error views. Inertia SSR is not wired. Laravel/Inertia `exceptions->respond()` is the existing application boundary.

Contract:

1. JSON requests keep the framework response.
2. HTML 403 / 404 / 419 always render Inertia `Errors/Show` via `ApplicationErrorPage`.
3. HTML 500 renders that page only when `APP_DEBUG=false`.
4. `APP_DEBUG=true` 500 keeps Laravel diagnostics.
5. Status codes are not rewritten to 200.
6. User copy is generic Indonesian from the PHP helper; exception class, message, paths, and permission names are not passed as props.
7. `seo` is `PublicMetadataInput` with `indexable: false` (no second SEO framework).

401 (guest → login redirect), 422 (Inertia validation), 429 (throttle), and 503 (maintenance) stay framework-appropriate.

PHPUnit CSRF is skipped by Laravel (`runningUnitTests()`), so 419 is exercised with `abort(419)` on a test-only route, not a forged POST.

---

## 4. 403 / 404 / 419 / 500 result

| Status | HTTP | Page | Recovery |
|---|---|---|---|
| 403 | 403 | Akses tidak diizinkan | Beranda + Dasbor or Masuk |
| 404 | 404 | Halaman tidak ditemukan | Beranda + previous page |
| 419 | 419 | Sesi telah berakhir | Muat ulang, Beranda, Masuk if guest |
| 500 (production) | 500 | Terjadi gangguan | Beranda |
| 500 (debug) | 500 | Laravel diagnostic HTML | n/a |

---

## 5. ACL audit

Source of truth remains `config/acl.php` (`<domain>.<action>`, plus documented exception `access settings`).

| Name / family | Class |
|---|---|
| users.*, roles.manage, cms.*, media.*, heroes.*, membership-*, publishing-publications.*, knowledge-articles.* | CANONICAL ACTIVE |
| `access settings` | PLATFORM ACTIVE (unused; kept) |
| culture.*, artikel.*, congregations.*, documents.*, songs.*, song_categories.* | LEGACY STALE (absent from config, routes, policies, menus, seeders, tests except 2E fixtures) |
| sermons.*, church.* as permission names | not present in config or operator dry-run |

Operator `--dry-run` (no writes): 36 canonical names in config; DB had 42 rows; **18 canonical Phase 2A/2B/2C permissions missing**; **24 Presbi leftover permissions present**. Ordinary sync would add the 18. `--clean` would remove the 24. Neither write was executed against the operator database.

---

## 6. ACL sync / clean operational contract

```
acl:sync                 additive + report stale
acl:sync --dry-run       report only
acl:sync --clean         explicit stale delete
acl:sync --clean --dry-run
```

Cleanup detaches `role_has_permissions` and `model_has_permissions`, then deletes the permission row, inside a transaction. Roles and users are not deleted. Canonical assignments remain. Second `--clean` is a no-op. Ordinary sync never deletes.

---

## 7. Confirmed stale permissions removed

**In application config/code:** none remained (already removed in 0C-B).

**In tests:** `culture.view` / `artikel.*` fixtures are created and removed by `--clean`.

**On the operator database:** leftover rows were **classified and reported**, not deleted. Consented operator follow-up: `acl:sync` then `acl:sync --clean` on a DB they maintain.

---

## 8. Fortify / Jetstream audit

| Surface | Class | Action |
|---|---|---|
| Login, forgot, reset, confirm password, verify email, 2FA challenge, register submit | SAFE TO CONVERGE | `PrimaryButton` → `BaseButton` (`type="submit"`) |
| Profile update password / profile Save | SAFE TO CONVERGE | `BaseButton` |
| Login unused `BaseModal` import | SAFE | removed |
| 2FA enable/disable, recovery codes | REQUIRES BEHAVIOR-SENSITIVE MIGRATION | KEEP Jetstream buttons |
| Delete account, other browser sessions, `ConfirmsPassword`, API tokens | REQUIRES BEHAVIOR-SENSITIVE MIGRATION | KEEP `DialogModal` / `ConfirmationModal` / `DangerButton` |
| Jetstream `Modal` (`<dialog>`) | KEEP FRAMEWORK COMPONENT | password-confirm and profile security |

---

## 9. UI convergence performed

Only the safe submit-button swap above. AuthenticationCard layouts, Fortify endpoints, and Jetstream form contracts were not rewritten.

---

## 10. BaseModal accessibility result

Implemented without a new library.

- Open: store opener, focus `[data-modal-initial-focus]` or first visible control or the panel (`tabindex="-1"`).
- Open: Tab / Shift+Tab cycle inside the teleported panel.
- Escape and backdrop close only if `closeable`.
- Close button hidden when not closeable.
- Close: restore focus to the opener.
- `role="dialog"`, `aria-modal`, `aria-labelledby` / `aria-label`.

`ConfirmDialog` labels the title and prefers initial focus on Cancel. `FilePickerModal` exposes a labelled heading. Nested modals are not used and were not added.

No Vitest/Cypress/Playwright in this repository; focus behavior is **code-inspected**, not browser-automated.

---

## 11. Reusable-component decisions

| Candidate | Decision |
|---|---|
| FormField / StatusBadge / Drawer / FocusManager | DEFERRED — no duplicated generic consumers that justify a new primitive (`BaseStatusBadge` already exists for domain badges) |
| BaseButton | reused on auth submit and error recovery |
| BaseModal | hardened; not replaced |
| Jetstream Modal | kept for security confirmations |

---

## 12. Production-safety audit

CODE-INSPECTED unless noted.

- `APP_DEBUG` still true in `.env.example` (local). Production 500 branding keys off `config('app.debug')`. **AUTOMATED** for both debug and production 500 paths.
- Error HTML does not receive exception details. **AUTOMATED**.
- Logging remains Laravel `stack`/`single`; no new PII log statements were added.
- `npx vite build` **PASS**.
- CSRF: Laravel skips token matching in PHPUnit; production still uses `ValidateCsrfToken`. 419 branding **AUTOMATED** via `abort(419)`.
- CMS catch-all still last; reserved segments unchanged. **AUTOMATED** (existing 2D tests).
- Public metadata indexability unchanged for content; errors noindex. **AUTOMATED**.
- Admin/private robots unchanged. **AUTOMATED** (existing tests).
- Auth redirects: guests still 302 to login. **AUTOMATED**.
- No CSP/security-header package added (not already present).
- Membership documents / draft Publication / draft Knowledge / inactive CMS boundaries unchanged. **AUTOMATED** (regression suite).
- `php artisan config:cache` then `config:clear` **PASS**.
- Operator `acl:sync --dry-run` **PASS** (no writes).

---

## 13. Security / privacy verification

- Membership private documents remain off generic media (existing tests green).
- Public Membership paths still shaped (existing tests green).
- Admin authorization still `permission:` middleware (existing tests green).
- Draft Publication / Knowledge still 404 (existing tests green).
- Editorial HTML / Template Blocks unchanged (existing tests green).
- Inactive CMS still 404; catch-all still cannot steal reserved families.
- Frontend menus remain UX-only.
- 403 copy does not include permission names (Ziggy may still list admin route names such as `admin.users.create`; that is routing, not ACL).
- Production 500 hides `RuntimeException` and the thrown secret.
- 404 does not include `NotFoundHttpException` or storage paths.

---

## 14. Automated tests

- `ApplicationErrorPageTest` (unit + feature)
- `AclSyncCommandTest`
- Existing auth, profile, 2FA, delete-account, Membership, Publishing, Knowledge, CMS, Public Experience suites

No frontend unit/e2e framework exists; none was added.

---

## 15. Manual / code-inspection verification

| Item | Evidence |
|---|---|
| Desktop/mobile 404 | AUTOMATED (HTTP/Inertia); NOT VERIFIED in a browser (no running server in this session) |
| 403 | AUTOMATED |
| 419 | AUTOMATED (`abort(419)`); real CSRF mismatch CODE-INSPECTED (Laravel production middleware) |
| Production-style 500 | AUTOMATED |
| Keyboard modal | CODE-INSPECTED |
| Auth screens changed | AUTOMATED (login/password/profile tests) |
| Homepage / Publication / Knowledge / CMS / Membership / admin | AUTOMATED regression |

---

## 16. Regression result

`php artisan test`: **220 passed, 5 skipped**. Phase 2A–2D suites green.

---

## 17. Deviations

1. 419 is not triggered by forged POST in PHPUnit (framework skips CSRF in tests). Branded 419 is tested with `abort(419)`.
2. Jetstream security modals were not replaced.
3. Operator stale ACL rows were not deleted; only reported.
4. Operator DB is missing 18 canonical permissions until a consented `acl:sync` (not run here).
5. No browser pass was performed this session.
6. 401/422/429/503 have no dedicated branded pages.

---

## 18. Remaining technical debt

Non-blocking:

- Operator `acl:sync` then `--clean` on databases they maintain
- Jetstream vs Base on remaining security confirmations
- Dotted Inertia `auth.user.*` keys
- Unused `access settings`
- Unused `BaseSearchSelect` / `DashboardStatCard`
- Brand OG artwork
- Laravel skeleton README
- No CI
- No CSP middleware
- `MustVerifyEmail` still deferred
- Historical church **tables** on old DBs (not permissions)

---

## 19. Remaining business questions

Unchanged from 2D and not Phase 2 blockers: `/kontak` vs `/contact`, Knowledge taxonomy, public bylines, when to expose Koperasi/Community.

---

## 20. Definition of Done

See the Phase 2E report checklist. In-scope items are done. Operator ACL write/`--clean` is an ops step, not incomplete product code.

---

## 21. Recommendation on formally closing Phase 2

**CLOSE PHASE 2.**

The Membership, Publishing, Knowledge, and public-experience slices are implemented and regression-green. Remaining items are documented non-blocking debt, operator sync, or future bounded contexts. Do not keep Phase 2 open for `/kontak`, taxonomy, bylines, OG artwork, or Koperasi/Community.
