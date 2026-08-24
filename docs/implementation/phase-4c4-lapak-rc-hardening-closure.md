# AB2TI 2.0 — Phase 4C.4 Lapak RC hardening & closure

**Status:** Implemented — RC READY  
**Date:** 2026-08-23  
**Does not start:** Koperasi, Community, or Lapak commerce.  
**Does not introduce:** cart, checkout, payment, orders, shipping, wallet, escrow, invoice, reviews, ratings, chat, wishlists, commission, subscription, schema.org, search engine, second media system, or a new ACL architecture.

---

## 1. Precondition

The Pre-4C.4 admin-shell repair remains in place and was re-verified before this work:

```text
EnsurePlatformStaff
alias: platform-staff
```

Applied to `GET /dashboard` and the entire `/admin/*` group. `User::isPlatformStaff()` (`super-admin`, `membership-admin`, `editor`) is the staff boundary. Spatie permissions without a staff role cannot enter the shell. `/akun/lapak` remains auth-only (linked Member workspace).

| Actor | `/dashboard` | `/admin/dashboard` | `/akun/lapak` |
|---|---|---|---|
| Guest | login redirect | login redirect | login redirect |
| Ordinary User | branded 403 | branded 403 | Unlinked workspace (auth) |
| Linked Member | branded 403 | branded 403 | allowed |
| Editor / Membership Admin / Super Admin | allowed | allowed | staff 403 (owner workspace is not staff) |

Granular ACL/Policies remain authoritative *inside* the admin shell.

---

## 2. Post-login destinations

`config/fortify.php` `home` is still `/dashboard` (staff-only alias). That path is **not** reopened to members.

Actual login / 2FA / already-authenticated guest-route redirects use:

- `App\Support\Auth\PostAuthenticationHome`
- `App\Http\Responses\LoginResponse`
- `App\Http\Responses\TwoFactorLoginResponse`
- `bootstrap/app.php` `$middleware->redirectUsersTo(...)`

| Actor | Destination |
|---|---|
| Platform staff | `admin.dashboard` (`/admin/dashboard`); administrative `url.intended` honored |
| Linked Member | `account.lapak.index` (`/akun/lapak`); administrative intended URLs ignored |
| Ordinary User | `welcome` (`/`); administrative intended URLs ignored |

Password reset still returns to the login screen (Fortify default). It does not log the user into `/dashboard`.

A successful login of a legitimate non-staff account does **not** land on branded admin 403 as the normal flow.

---

## 3. Authorization matrix (public / owner / admin)

```text
PUBLIC   guest-safe read-only /lapak
OWNER    authenticated User → linked Member → own Lapak → own Listings
ADMIN    platform-staff boundary + granular ACL / Policies
```

Authentication grants neither Member ownership nor admin access.

Owner identity is always derived from the authenticated User’s linked Member. Submitted `member_id` / `lapak_id` values are ignored on owner create. Foreign edit/submit/availability/category administration is 403. Staff cannot use `/akun/lapak`.

---

## 4. Privacy

Public props (`/lapak`, storefront, listing, homepage preview) expose only:

- public Lapak name, description, location
- seller-entered public contact channels (generated hrefs, not raw Member/User fields)
- public media URLs
- published listing title/summary/description/price label/category
- public slugs/URLs

Confirmed absent: User ID/email, Member `user_id` / private email / phone / address, `source_application_id`, `created_by`, `linked_at`, account-link status, capability data, moderation notes/status, membership application data.

Public seller identity remains the **Lapak**, not the Member legal name.

---

## 5. Public eligibility

Single source:

```text
LapakListing::publicEligible()
  listing published
  AND listing available
  AND Lapak::publicEligible() (published)
  AND category active
```

`PublicLapakSearch` and public controllers compose those scopes. Directory, storefront, detail, and homepage `latest(3)` all use them.

Immediate public withdrawal (branded 404 / empty directory/homepage) when listing is suspended, unavailable, pending after a published edit, parent Lapak is unpublished/suspended, or category is inactive.

---

## 6. Moderation & concurrency

Owner contract unchanged:

| Status | Owner |
|---|---|
| draft | edit + submit |
| pending_review | content locked (availability still allowed unless suspended) |
| published | availability may change; substantive edit returns to `pending_review` |
| rejected | edit + resubmit |
| suspended | cannot self-publish or edit |

Hardening:

- `UpdateLapak` / `UpdateLapakListing` / `UpdateLapakListingAvailability` `lockForUpdate()` and throw `InvalidLapakTransition` when the owner’s current row no longer allows the write
- double submit is rejected once status is `pending_review`
- owner update of published content re-submits based on the **post-update** status
- owner updates that omit `slug` **keep the existing slug** so public permalinks stay stable

No revision/versioning framework.

---

## 7. Contact safety & abuse

`LapakPublicContact` builds links from structured fields only. Seller-entered href strings are never trusted.

- WhatsApp: Indonesian `0…` / `8…` / `+62…` → `https://wa.me/62…`; rejects `javascript:`, `data:`, `http(s):`, punctuation-only, too-short/long
- Telephone: `tel:+…` from digits only
- Email: `mailto:` only after `FILTER_VALIDATE_EMAIL` and character checks

Contact CTAs are **direct external links**. They do not hit an AB2TI server endpoint, so contact-action rate limiting is not materially applicable. Existing auth/membership/capability throttles are unchanged.

Public copy: “Hubungi Penjual” / Contact the seller, plus “Transaksi dilakukan langsung dengan penjual.”

---

## 8. Media hardening

Public: zero / one / many images; missing conversion or deleted media → `safeMediaUrl()` null (placeholder, not 500). SEO image uses listing image → Lapak cover → platform default, with `urlFromMedia()` try/catch.

Owner: direct upload only. File Manager routes remain staff-only. Foreign library IDs cannot be attached. Admin Media unchanged. No second storage system.

---

## 9. Search / filter / pagination

`PublicLapakSearch` + `PublicContentSearch`:

- empty / whitespace → treated as no term
- term capped at 100 characters
- `%` `_` `\` escaped in LIKE
- quotes and UTF-8 are parameterized
- invalid/inactive `kategori` → empty result, not error
- page `1` valid; `0` / `-1` / `abc` → page 1; huge page → empty page, filters preserved
- sort: `published_at DESC`, `id DESC`
- homepage preview limited to 3

Does not search User/Member private fields.

---

## 10. SEO / social

Central `PublicMetadataResolver`. Canonical without query string. Title/description truncated. OG + Twitter/X `summary_large_image`. Long Unicode titles truncate safely. Empty summary falls back to description or platform default. Missing/deleted image → platform default OG.

**Structured data remains deferred** (no generic schema.org pipeline; Lapak is not represented as checkout).

---

## 11. Accessibility (concrete)

- Directory category chips: `aria-current="page"`; search uses `PublicArchiveSearch` label
- Listing gallery: `role="region"`, `tabindex="0"`, left/right keys, previous/next and thumbnail labels, meaningful `alt`
- Contact links: visible labels + `sr-only` display values; `min-h-control` tap targets
- Public nav: skip link, `aria-expanded` menu, focus rings already present
- Owner/admin: existing labels, validation, moderation guidance, confirmations unchanged except as above

No accessibility library added.

---

## 12. Responsive QA

Inspected Tailwind breakpoints and current public/owner classes (no essential content hidden):

| Width | Expected mode |
|---|---|
| 320 / 360 / 390 | mobile menu (`xl:hidden`); utility bar wraps; cards 1-col; contact stacked |
| 768 | `sm`/`md` two-column cards; footer 1→3 col at `md` |
| 1024 | `lg` three-column directory |
| 1280+ | desktop primary nav (`xl:flex`); sidebar closes at `min-width: 1280px` |
| 1366 / 1440 / 1536 / 1920 | same desktop shell; `max-w-7xl` container |

Long titles use `break-words`. Gallery `max-h` + `object-contain`. Owner forms `max-w-2xl` with standard inputs.

Live multi-viewport browser pass was not the primary evidence; layout contracts are the same Phase 4A/4B shell.

---

## 13. Navigation & i18n

Canonical navigation key `Teknologi Tani`:

```text
ID: Teknologi Tani
EN: Agricultural Technology
```

Surfaces using `$t('Teknologi Tani')` / `$t(item.label)` / `$t(item.titleKey)`:

- desktop public navbar (`FrontendNavbar`)
- mobile/sidebar (`FrontendSidebar`)
- footer (`PublicFooter`)
- homepage pathway (`HomepagePathways` `titleKey`)
- Knowledge index/show eyebrow (same key; knowledge sentence copy may still mention “Teknologi Tani articles” in EN as product name)

Vue does not hardcode the English nav label.

Preserved:

- Theme switch and Language switch via `PublicTopbarUtilities` (not duplicated)
- Dashboard remains utility-only for platform staff
- Lapak Saya utility for linked Members
- Apply Membership stays contextual (homepage + `/keanggotaan`), not primary nav

`WebLayout.vue` was unused dead code with stale Dashboard links. It was **deleted**. Canonical public shell is `FrontendLayout.vue`.

---

## 14. Test runner / memory

Previous `composer test` invoked `php artisan test`, which respawned PHPUnit under the inherited **128M** PHP CLI limit. Spatie Media Library GD conversions in media tests exhausted that limit. Direct `php -d memory_limit=512M vendor/bin/phpunit` passed with peak well under 512M (not a runaway leak).

Canonical adjustment (test runner only, not production):

- `phpunit.xml`: `<ini name="memory_limit" value="512M"/>`
- `composer.json` `test` script: `@php artisan config:clear` then `@php -d memory_limit=512M vendor/bin/phpunit`

Evidence this is conversion load, not a leak: full suite peak **120.50 MB** (`composer test`) and **118.50 MB** (`php -d memory_limit=512M vendor/bin/phpunit`). Both: **427 tests, 3894 assertions, 5 skipped**.

---

## 15. Database constraints

Verified, no new migration:

- `members.user_id` nullable **unique** FK
- `lapaks.member_id` **unique** FK

---

## 16. Known V1 limitations (intentional, not blockers)

- no checkout / payment / shipping / orders / chat / ratings / reviews
- no structured-data framework
- account-link invitation delivered out-of-band
- public self-registration may remain closed
- published content edits return to review (no revision snapshots)

---

## 17. Acceptance

Focused tests in `tests/Feature/Lapak/LapakRcAcceptanceTest.php` plus existing Lapak / account-link / admin-authorization / public-navigation suites cover:

account link → linked-Member login without admin 403 → owner create/upload/submit → admin publish → public directory/storefront/listing/contact → reject/resubmit → published edit → availability → suspension → inactive category → foreign IDOR → ordinary User cannot enter admin → private fields absent.

---

## 18. Platform contract (final)

```text
PUBLIC
/lapak
classified discovery only

OWNER
/akun/lapak
authenticated User → linked Member → own Lapak → own Listings

ADMIN
/dashboard + /admin/*
platform-staff boundary → granular ACL / Policies

PUBLICATION
owner creates/submits → staff moderates → eligibility scopes control delivery

TRANSACTION
outside AB2TI platform
```
