# AB2TI 2.0 — Phase 2D Public Experience & CMS Convergence

**Status:** Implemented  
**Date:** 2026-08-21  
**Does not start:** Koperasi, Community, Member self-service, finance, search, recommendations, or a visual redesign.  
**Does not reopen:** Phase 2A / 2B / 2C / 2C.1 except where this phase found a public-experience gap.

This is not a new business-domain phase. It makes the already-implemented Membership, Publishing, Knowledge, and CMS surfaces form one coherent public website.

---

## 1. Phase result

Public IA now has explicit owners:

- Homepage composes Hero/Welcome (platform CMS tables), latest Publications, latest Knowledge Articles, and a Membership CTA to `/keanggotaan`.
- One named-route navigation contract: Beranda, Tentang AB2TI, Publikasi, Teknologi Tani, Keanggotaan, Kontak.
- CMS Page public delivery uses the same editorial safety pipeline as Publishing/Knowledge, with shaped public props (`CmsPageViews`).
- Public metadata remains the centralized `PublicMetadataInput` → `PublicMetadataResolver` contract.
- Presbi Sermons/Events stay retired (ADR 0005). No fake Sermons domain and no compatibility redirects.

---

## 2. Files created/modified

**Created**

- `app/Support/Cms/CmsPageViews.php`
- `resources/js/Pages/Frontend/Landing/Components/MembershipInvite.vue`
- `tests/Unit/Cms/CmsPageViewsTest.php`
- `tests/Feature/PublicExperience/PublicExperienceTest.php`
- `docs/implementation/phase-2d-public-experience-cms-convergence.md`

**Modified**

- `app/Http/Controllers/PageController.php`
- `routes/web.php`
- `config/cms.php`
- `resources/js/Composables/useNavigation.js`
- `resources/js/Layouts/FrontendLayout.vue`
- `resources/js/Layouts/Partials/FrontendNavbar.vue`
- `resources/js/Layouts/Partials/FrontendSidebar.vue`
- `resources/js/Components/ResponsiveNavLink.vue` (`tone="public"`; admin Jetstream tone unchanged)
- `resources/js/Pages/Frontend/Landing/Welcome.vue`
- `resources/js/Pages/Frontend/Landing/Components/HeroSection.vue`
- `resources/js/Pages/Frontend/Landing/Components/WelcomeSection.vue`
- `resources/js/Pages/Frontend/Landing/Components/FooterSection.vue`
- `resources/js/Pages/Frontend/Page/Show.vue`
- `resources/js/Pages/Frontend/Knowledge/Index.vue`
- `resources/js/Pages/Frontend/Knowledge/Show.vue`
- `resources/js/Components/Publishing/PublicationCard.vue`
- `resources/js/Components/Knowledge/KnowledgeArticleCard.vue`
- `lang/id/sources/navigation.json`, `lang/en/sources/navigation.json` (+ compiled `lang/{id,en}.json` after `lang:merge`)
- `tests/Unit/CmsPathTest.php`
- `tests/Feature/Cms/CmsRoutingTest.php`
- `docs/project/ARCHITECTURE_BASELINE.md`

---

## 3. Public route ownership

| Route | Owner | Notes |
|---|---|---|
| `/` `welcome` | PLATFORM (homepage composition) | Queries owned domains with `limit(3)` + `published()` |
| `/about` `about` | PLATFORM/CMS | Alias to slug `about` |
| `/contact` `contact` | PLATFORM/CMS | Alias to slug `contact` |
| `GET /{slug}` `pages.show` | PLATFORM/CMS | Catch-all last; reserved first segments excluded |
| `/publikasi`, `/{type}`, `/{type}/{slug}` | PUBLISHING | Type prefixes `berita`, `kegiatan`, `siaran-pers`, `liputan-media` |
| `/teknologi-tani`, `/teknologi-tani/{slug}` | KNOWLEDGE | Unpublished/invalid slug → 404 |
| `/keanggotaan*` | MEMBERSHIP | Invalid nested path → 404; CMS cannot steal `keanggotaan` |
| `/login`, `/forgot-password`, … | PLATFORM (Fortify) | Auth, not public content |
| `/admin/*`, `/dashboard`, `/user/*` | PLATFORM (admin) | Auth; noindex via private-surface resolver |
| `/sermons`, `/events` | LEGACY removed | Reserved; 404; no redirect (ADR 0005) |
| `/kontak` | REDIRECT/COMPATIBILITY **not implemented** | Reserved only so CMS cannot occupy the Indonesian contact family |

No UNKNOWN public content routes were left in `routes/web.php`.

---

## 4. Public navigation structure

Contract: `useNavigation().frontendMenus`.

1. Beranda → `welcome`
2. Tentang AB2TI → `about`
3. Publikasi → `publications.index`
4. Teknologi Tani → `knowledge.index`
5. Keanggotaan → `membership.index`
6. Kontak → `contact`

Navbar, mobile sidebar, and footer consume the same list. Labels are translation keys; the template calls `$t` once. No Sermons, Events, Farming Tech nav item, dead hashes, or future Koperasi/Community entries.

---

## 5. Homepage data ownership

| Section | Source |
|---|---|
| Hero | `heroes` (active, random one) + `hero-backgrounds` URLs. No Unsplash. Gradient fallback if no image. Default CTA is `/keanggotaan` when CMS hero has no button URL. |
| Welcome/about | `welcome_sections` (active). No Unsplash. Image omitted if none. Default CTA is `knowledge.index` when the CMS row has no URL. Hardcoded Indonesian copy remains only when no CMS row exists. |
| Latest publications | Publishing `published()`, `limit(3)`, `PublicationViews::publicCard` |
| Latest Teknologi Tani | Knowledge `published()`, `limit(3)`, `KnowledgeArticleViews::publicCard` |
| Membership invite | CTA URL only (`membership.index`). No member/application data. |
| Removed | `laravelVersion` / `phpVersion` / page-level `canLogin`; Unsplash fallbacks; hover-only zoom; `/login` as default “Bergabung” destination |

---

## 6. CMS convergence

CMS Page already used `EditorialContent` (Phase 2B.1). This phase did **not** add a CMS-specific sanitizer.

Change: public Inertia payload is `CmsPageViews::publicDetail()` instead of the Eloquent `Page`. Legitimate Tiptap HTML and registered Template Blocks still render. `javascript:` and protocol-relative related links are dropped.

---

## 7. Public metadata convergence

No feature-specific SEO classes were added.

Verified remaining on the existing resolver:

- Homepage, CMS Page, Publication index/detail, Knowledge index/detail: indexable, canonical from `CanonicalUrl`
- Membership informational `/keanggotaan`: indexable (existing)
- Membership apply/submitted and admin: noindex (existing)
- OG order unchanged: dedicated social image → cover/hero → site fallback

---

## 8. UI / design-system reuse

Reused: `FrontendLayout`, `NavLink`, `BaseButton`, `BasePagination`, `PublicationCard`, `KnowledgeArticleCard`.

Did not create `PublicationButton` / `KnowledgeButton` / `MembershipButton`.

`ResponsiveNavLink` gained an optional `tone="public"` so the public sidebar uses brand tokens without copying the component. Admin sidebar keeps the existing Jetstream tone.

---

## 9. HCI / accessibility work

- Current nav state via `NavLink` `aria-current`
- Hamburger `aria-expanded` / `aria-controls`
- Translated open/close/login strings on public chrome
- Visible focus on public nav, footer, CMS related links, hero CTAs
- Meaningful cover `alt`; lazy-load below-fold card images
- Knowledge public title uses “Teknologi Tani” rather than “Farming Tech”
- Empty states on Publication/Knowledge indexes unchanged
- Hover-only image zoom removed from hero/welcome
- Branded 404/403 pages **not** introduced (Laravel default remains; not a broken public flow)

---

## 10. Legacy public cleanup

| Finding | Class | Action |
|---|---|---|
| `/sermons`, `/events` | REMOVE NOW (already 2C.1) | Left retired |
| Unsplash homepage fallbacks | REMOVE NOW | Removed |
| Laravel version homepage props | REMOVE NOW | Removed |
| Hero CTA default `/login` | REMOVE NOW | Membership |
| Double-translated nav + English sidebar chrome | REMOVE NOW | Keys + `$t` |
| `Farming Tech` public labels | REMOVE NOW | `Teknologi Tani` |
| `/about`, `/contact` | KEEP GENERIC | AB2TI CMS aliases |
| Publishing `kegiatan` | KEEP GENERIC | Not church Events |
| BlockTemplateSeeder Unsplash sample | ADMIN/LEGACY OUTSIDE 2D | Left (admin snippet, not homepage) |
| Historical church tables on old DBs | ADMIN/LEGACY OUTSIDE 2D | No DROP |
| `culture.*` / `artikel.*` stale ACL rows | ADMIN/LEGACY OUTSIDE 2D | Operator `acl:sync --clean` |

---

## 11. Security / privacy verification

- Draft Publication / Knowledge still excluded from homepage and public show (`published()`).
- Inactive CMS pages 404.
- Public CMS props omit media internals, `settings`, `is_active`.
- Related links cannot carry `javascript:` or `//host` URLs.
- Editorial HTML still sanitized; Template Blocks remain registry-bound.
- Membership documents remain on the private disk; homepage CTA has no PII.
- Admin authorization unchanged (server `permission:` middleware).

---

## 12. Tests

- `tests/Feature/PublicExperience/PublicExperienceTest.php` — named destinations, sermons/kontak 404, homepage published-only + no demo props, shaped CMS props, template block public render
- `tests/Unit/Cms/CmsPageViewsTest.php` — related-link allowlist
- `CmsPathTest` / `CmsRoutingTest` — `kontak` reserved
- Existing Phase 2A / 2B / 2C / metadata / editorial tests remain the regression net

---

## 13. Regression result

`php artisan test`: **210 passed, 5 skipped** (email verification and registration features disabled). Phase 2A Membership, Phase 2B Publishing, Phase 2C Knowledge, CMS editorial/routing, and public metadata suites all green.

`vendor/bin/pint --dirty`: PASS  
`npx vite build`: PASS  
`php artisan lang:merge id` / `en`: PASS  
`php artisan route:list`: PASS (public families remain owned; CMS catch-all last)

---

## 14. Deviations

1. `/kontak` is reserved but **not** implemented as a URL or redirect. Public contact remains `/contact` (English path, existing CMS alias).
2. Welcome-section default CTA remains `knowledge.index` when no CMS CTA is stored (always a live public family). `/about` is used only when an operator sets it, or via the dedicated nav item.
3. Homepage Membership CTA is always shown (URL only). It does not query Membership records.
4. Hero/Welcome still have hardcoded Indonesian copy when no CMS rows exist. Removing Unsplash is in scope; rewriting all default copy is not a redesign mandate.
5. Branded error pages were not built.

---

## 15. Remaining business questions

- Should public contact move to `/kontak` (and/or about to `/tentang`) while keeping `/contact` as a redirect?
- Should empty Hero/Welcome CMS rows suppress hardcoded marketing copy entirely?
- Public byline / Knowledge taxonomy (still open from 2C).
- When to expose Koperasi / Community in the public nav (not before those contexts exist).

---

## 16. Remaining technical questions

- Branded 404/403/419/500 pages (baseline debt item 21).
- Operator `acl:sync --clean` for stale `culture.*` / `artikel.*` rows.
- Site-wide OG fallback is still the functional placeholder JPEG.
- Jetstream vs Base component families on Fortify/profile.

---

## 17. Definition of Done

See the Phase 2D report checklist. All in-scope items are implemented; branded error pages and Indonesian contact URL remain documented deferrals, not incomplete 2D work.

---

## 18. Recommendation for next phase

Do **not** start the next phase from this document.

Product sequence after public IA is coherent: the next **business** context is Koperasi Pusat AB2TI **or** Community, only when explicitly commissioned. Alternatively, a small platform hardening slice (branded error pages, OG artwork, `/kontak` IA) if product wants polish before another domain.

Do not implement Koperasi membership, Community, or member self-service as a silent follow-on.
