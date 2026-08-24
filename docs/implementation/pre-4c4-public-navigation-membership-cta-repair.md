# AB2TI 2.0 — Pre-4C.4 public navigation & membership CTA repair

**Status:** Implemented  
**Date:** 2026-08-23  
**Does not start:** Phase 4C.4.

---

## 1. Original collision

After Lapak Anggota joined `frontendMenus`, the sticky public navbar packed seven site destinations, a large **Apply for membership** button, **Dashboard**, and (for linked members) **Lapak Saya** into one row. At common desktop widths (especially 1280–1440 with longer EN labels) those clusters competed for width. Font shrinking would not have fixed the IA.

---

## 2. Primary navigation vs utility

**Primary navbar** (`FrontendNavbar` / mobile list / footer) is site navigation only, still from `useNavigation().frontendMenus`:

Home → About AB2TI → Publications → Teknologi Tani → Lapak Anggota → Membership → Contact

Removed from that row:

- Apply for membership
- Dashboard
- Account/admin shortcuts

---

## 3. Existing utility component reused

Theme and language already lived in the public top bar (`ThemeToggle`, `LanguageSwitcher`). They were extracted into `Layouts/Partials/PublicTopbarUtilities.vue` and reused by:

- `FrontendLayout` top bar (`variant="bar"`)
- mobile `FrontendSidebar` utility group (`variant="menu"`)

No second theme switch, no second language switch, no top-bar redesign.

---

## 4. Dashboard visibility

Shared Inertia prop `auth.can_access_admin` is `User::isPlatformStaff()` (`super-admin`, `membership-admin`, `editor`).

| Viewer | Utility shown | Destination |
|---|---|---|
| Guest | Log in | `/login` |
| Platform staff | Dashboard | `admin.dashboard` (`/admin/dashboard`) |
| Linked member (not staff) | Lapak Saya | `/akun/lapak` |
| Ordinary authenticated non-staff | none of the above | — |

Frontend visibility is UX only. `/dashboard` and `/admin/dashboard` remain auth-gated as before; ordinary accounts can still open them if they know the URL. No new roles or permissions.

---

## 5. Membership CTA relocation

Global header and footer no longer carry a permanent Apply button.

- **Membership page** (`/keanggotaan`): information and process first; existing `applyUrl` → `membership.apply` with **Ajukan Keanggotaan** / **Apply for membership**.
- **Homepage**: pathway still goes to membership information. `MembershipInvite` now has primary **Pelajari Keanggotaan** (`membership.index`) and secondary **Ajukan Keanggotaan** (`membership.apply`). Hero remains CMS-driven.

---

## 6. Responsive breakpoint

Desktop inline nav used `lg` (1024px), which is too late for seven nowrap labels. It now uses **`xl` (1280px)**. The mobile `BaseModal` closes at `min-width: 1280px`. Below 1280, the hamburger owns the same `frontendMenus` list. Overlap is prevented by switching mode, not by shrinking type.

---

## 7. Localization

Canonical keys reused. ID **Apply for membership** is now `Ajukan Keanggotaan`. New key **Learn about membership** → `Pelajari Keanggotaan` / `Learn about membership`. Dashboard remains `Dasbor` / `Dashboard`. Lapak Anggota remains `Lapak Anggota` / `Member marketplace`. Theme and language control labels moved onto `$t(...)`.

---

## 8. Tests

`tests/Feature/PublicExperience/PublicNavigationRepairTest.php` plus updates to public-experience and membership intake tests.
