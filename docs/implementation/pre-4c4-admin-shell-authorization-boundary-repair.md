# AB2TI 2.0 — Pre-4C.4 admin shell authorization boundary repair

**Status:** Implemented  
**Date:** 2026-08-23  
**Does not start:** Phase 4C.4. No Lapak hardening, localization, or unrelated domain work.

---

## 1. Original exposure

`/dashboard` and `/admin/dashboard` both rendered `Dashboard.vue` inside `AppLayout` behind **authentication only**. Module `/admin/*` routes already used Spatie `permission:` middleware, so ordinary Users could not operate Membership/Lapak/CMS/Media — but they **could enter the administrative shell** by URL.

`tests/Feature/Admin/AdminAuthorizationTest.php::test_authenticated_user_can_open_dashboard_without_module_permissions` encoded that hole as expected behavior. Public header hiding (`auth.can_access_admin`) was UX only.

---

## 2. `/dashboard` audit and decision

| Question | Finding |
|---|---|
| Why both URLs? | Kernel leftover. `config/fortify.php` `home` is `/dashboard`. Constitution keeps both URLs; do not invent a third. |
| Administrative? | Yes. Same `Admin\DashboardController` → `Dashboard.vue` → `AppLayout`. |
| Jetstream default? | Yes. Named route `dashboard`. |
| Other references | Admin `AppTopbar` logo; unused `WebLayout.vue`; Fortify post-login home; CMS reserved first segment `dashboard`. Public topbar already uses `admin.dashboard`. |

**Decision A — keep as staff-only alias.**

```text
/dashboard
  staff     → administrative Dashboard (same controller as /admin/dashboard)
  non-staff → branded 403
  guest     → login redirect
```

Not option B (redirect to `/admin/dashboard`): Fortify `home` would add a hop, and the Constitution already allows both URLs. `/dashboard` is **not** a member dashboard.

---

## 3. `/admin/dashboard` rule

Same staff boundary as `/dashboard`. Canonical staff entry used by `PublicTopbarUtilities` remains `route('admin.dashboard')`.

---

## 4. Staff mechanism

Reusable middleware `App\Http\Middleware\EnsurePlatformStaff`, alias `platform-staff`.

It calls `User::isPlatformStaff()` only. Role names are not duplicated in routes.

Applied as:

```text
auth:sanctum + jetstream session + platform-staff
  → GET /dashboard
  → entire prefix /admin (admin.*)
```

`/akun/lapak` and `/akun/tautkan/{secret}` stay on **auth only** (not staff).

---

## 5. `isPlatformStaff()`

Unchanged:

```text
super-admin
membership-admin
editor
```

Any future platform staff role must be added there. `Gate::before` still bypasses abilities for `super-admin` after the user has entered the shell.

---

## 6. Relationship to granular ACL

```text
Authentication
    ↓
EnsurePlatformStaff   → may you enter the administrative application?
    ↓
permission: / Policies → which modules/actions?
```

Entering the shell does not grant Membership, Lapak, CMS, Media, or Publishing operations. Direct Spatie permissions without a staff role no longer open `/admin`.

The leftover Spatie `user` role with `media.view` (seeder) no longer admits that account to File Manager.

---

## 7. Member workspace separation

Linked Member:

```text
/akun/lapak     → allowed (FrontendLayout)
/dashboard      → 403
/admin/dashboard → 403
```

Denied admin requests are **not** redirected to `/akun/lapak`. Owner empty/unlinked pages no longer link “back to dashboard”; they return to `welcome`.

---

## 8. Intentionally exempt administrative-looking routes

| Surface | Why exempt |
|---|---|
| Jetstream `/user/profile`, `/user/api-tokens` | Self-service account, not the admin application. Still uses `AppLayout` chrome. |
| `/akun/lapak`, `/akun/tautkan/{secret}` | Member/account identity, not staff. |
| Unused `WebLayout.vue` dashboard links | Layout is not used by current pages. |

Fortify still redirects successful login to `/dashboard`. Ordinary Users therefore land on branded 403 after login. Changing Fortify `home` is an authentication product decision, deferred.

---

## 9. Test matrix

Replaced the insecure dashboard assertion. Coverage:

| Actor | `/dashboard` | `/admin/dashboard` | Notes |
|---|---|---|---|
| Guest | login redirect | login redirect | |
| Ordinary User | 403 branded | 403 branded | |
| Linked Member | 403 | 403 | `/akun/lapak` 200 |
| Permission without staff role | 403 | 403 | even with `users.view` / `media.view` / `lapak.view` |
| Editor | 200 | 200 | CMS ok; Membership/Lapak 403 |
| Membership Admin | 200 | 200 | Membership/Lapak ok; CMS/Media 403 |
| Super Admin | 200 | 200 | `Gate::before` still applies |

Representative module indexes (Membership, Lapak, Publishing, Knowledge, Media, CMS) remain guest-redirect / ordinary-user-403.

---

## 10. Public topbar

Unchanged. Dashboard utility still requires `auth.can_access_admin`, which already equals `isPlatformStaff()`. Theme, language, Lapak Saya, Login, primary nav, and Membership CTA were not modified.

---

## 11. Phase 4C.4

```text
Phase 4C.4 was NOT started.
Ready to retry Phase 4C.4
```
