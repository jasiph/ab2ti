# AB2TI 2.0 — Pre-Phase 2A Public Delivery, SEO & Social Sharing

**Status:** Platform contract + minimal kernel implementation. Not a business-domain slice.  
**Date:** 2026-08-20  
**ADR:** `docs/project/decisions/0004-public-delivery-seo-social-sharing.md`

This document is the Pre-Phase 2A record. It does not reopen Phase 1A–1G.

---

## 1. Existing functionality discovered

| Area | Finding |
|---|---|
| Blade root | `resources/views/app.blade.php` — charset, viewport, `<title inertia>`, fonts, Vite, `@inertiaHead`. **No** canonical, description, Open Graph, or Twitter tags. |
| Inertia Vue `<Head>` | Used for document titles in layouts and auth pages. CMS `Page/Show.vue` previously emitted `meta name="description"` **client-side only**. |
| CMS fields | `pages.meta_title`, `pages.meta_description` (migration + admin form + `Page` fillable). |
| Inertia SSR | `resources/js/ssr.js` exists; `vite.config.js` lists it. **`npm run build` is client-only.** SSR is not running. `@inertiaHead` therefore does not make Vue `<Head>` crawler-visible. |
| Canonical URLs | No helper. Ziggy `location` uses `$request->url()` (incoming host). `config/app.php` `url` / `APP_URL` is the only origin config. No `URL::forceRootUrl`. |
| Open Graph / Twitter | **None** in application PHP/Vue (Phase 1A already noted absence). |
| Media | Spatie Media Library v11. `Page` collection `page-cover` + `thumb` conversion. `Hero` `hero-backgrounds` + `large`/`thumb`. `File` `default` + `thumb`. Conversions **non-queued**. No 1200×630 social conversion. |
| Image processing | Spatie + Intervention (transitive). Vue `browser-image-compression` on upload. No queue-driven OG renderer. No headless Chromium. |
| Default share image | No `public/` raster brand asset existed. Frontend uses SVG Vue logos. |
| Public routes | `/` welcome, CMS aliases, catch-all `pages.show`. Inactive pages 404. Admin/auth behind reserved first segments (ADR 0003). |
| Tests | CMS routing, Hero media, ACL. No SEO/OG tests. |

---

## 2. Reusable legacy functionality

| Item | Classification | Action |
|---|---|---|
| `pages.meta_title` / `meta_description` | **REUSE** | Editorial SEO overrides. |
| `page-cover` media collection | **REUSE** | Featured-image candidate in the OG hierarchy. |
| Spatie conversions, non-queued | **REUSE** | Add `social-og` 1200×630 JPEG crop at upload/publish time. |
| `config/app.php` `url` (`APP_URL`) | **REUSE** | Default canonical origin. |
| `config/cms.php` reserved first segments | **REUSE** | Private/non-indexable surfaces (admin, login, user, …). |
| Inertia root Blade + shared props | **REUSE** | Emit crawler tags from a shared/overridden `seo` prop. |
| CMS `is_active` + 404 | **REUSE** | Draft/private pages are not shareable. |
| Inertia Vue `<Head>` titles | **REUSE** | SPA tab titles after client navigation. Not the crawler source of truth. |
| `resources/js/ssr.js` | **REMOVE LATER / unused** | Do not turn on SSR for this capability. |

---

## 3. Presbi-specific behavior rejected

- No church, sermon, or congregation metadata types.
- `/sermons` and `/events` remain CMS aliases (existing kernel). They use the **same** platform resolver if those CMS pages are active. They are not a separate SEO product.
- No Presbi default OG artwork. The fallback JPEG is a functional AB2TI placeholder (`AB2TI` on green), not a Presbi brand asset.
- No per-domain “article vs sermon” SEO engines.

---

## 4. Canonical SEO metadata contract

One platform document (`PublicMetadata`) for every public Inertia response:

| Field | Source |
|---|---|
| `title` / `og:title` / `twitter:title` | explicit SEO title → resource title → site default |
| `description` / `og:description` / `twitter:description` | explicit SEO description → excerpt → sanitized content summary → site default |
| `canonical_url` / `og:url` | `CanonicalUrl::forPath()` |
| `og:type` | resource-supplied `website` or `article`; unknown → `website` |
| `og:image` (+ width/height/alt when known) | image hierarchy below |
| `twitter:card` | `summary_large_image` |
| `robots` | `index, follow` if indexable; else `noindex, nofollow` |

Site defaults live in `config/public_delivery.php`.

Descriptions are plain text (tags stripped, entities decoded, whitespace collapsed) and bounded (`description_max_length` 160).

Future publications/knowledge/awards **must** feed `PublicMetadataInput`. Do not add `ArticleOgService` / `AwardOgService`.

---

## 5. Canonical URL strategy

- Origin: `config('public_delivery.canonical_origin')`, defaulting to `APP_URL`.
- Optional env: `PUBLIC_DELIVERY_CANONICAL_ORIGIN`.
- Path: canonical route path only. Query strings stripped. No signed/temporary URLs.
- Home: `{origin}/`. Other resources: `{origin}/{path}` without a trailing slash.
- **Never** built from the incoming `Host` header.
- Ziggy `location` still reflects the request URL (pre-existing). Social/canonical tags do **not** use it.
- Multi-domain: when a second public origin is real, set `PUBLIC_DELIVERY_CANONICAL_ORIGIN` (or extend config with a later ADR). Do not auto-trust `Host`.

---

## 6. Server-side rendering strategy

Social crawlers must not depend on Vue.

**Chosen approach:** render tags in Blade from already-resolved PHP `seo` props.

```
GET public URL
  → Laravel controller / shared resolver
  → Inertia page + seo array
  → app.blade.php includes partials/public-delivery-meta.blade.php
  → crawler sees <link rel="canonical">, description, og:*, twitter:*
```

Inertia SSR is **not** enabled. It is unnecessary for this problem and is not in the production build.

Vue `<Head>` remains for in-app title updates after client-side visits. Crawlers always issue a full GET.

---

## 7. OG image resolution hierarchy

For indexable public pages:

1. Explicit dedicated social/OG media (`Page` collection `social-og`, or a future resource equivalent)
2. Resource featured / hero / cover (`page-cover`, welcome hero `large`, welcome `image_url`)
3. Generated branded title-card from typography — **deferred** (no Chromium; no per-request renderer)
4. Site-wide default `public/images/og/ab2ti-og-default-v1.jpg` (1200×630 JPEG)

When (1) or (2) is a Spatie original, conversion `social-og` (crop 1200×630 JPEG, non-queued) is preferred if generated.

Signed/temporary URLs (`signature`, `expires`, `token`) are rejected; the next candidate or the site default is used.

Indexable pages always emit a non-empty `og:image` (absolute URL).

---

## 8. Media integration strategy

```
source media (page-cover / future featured)
    → Spatie conversion social-og (1200×630 JPEG)
explicit social-og collection
    → same conversion
site default file
    → public static path (not File Manager, not membership disks)
```

Editors should not have to upload 1200×630 by hand once a cover exists. Dedicated OG override is available as a collection **without** a new admin screen in this phase.

Tiptap/CMS HTML is **content only**. It is not an OG renderer. Optional HTML → description uses sanitization, not raw markup.

File Manager (`media.*`) stays editorial. Membership/private documents must never be OG sources (no such public helper was added).

---

## 9. Asset generation lifecycle

```
editor upload / resource save
    → Spatie stores original
    → non-queued social-og conversion
    → crawler GETs the static file (storage/public or default public/images/og)
```

No queue was introduced. Existing conversions were already `nonQueued()`.

No per-crawler image render. No headless browser.

While a conversion is missing, the resolver may use the original public URL (still absolutized) or the site default.

---

## 10. Caching / versioning strategy

| Asset | URL | Cache intent |
|---|---|---|
| Site default | `/images/og/ab2ti-og-default-v1.jpg` | Fingerprinted by **filename** (`v1`). Replace by adding `v2` and changing config — do not mutate `v1` in place. Web server/CDN: `public, max-age=31536000, immutable`. |
| Spatie `social-og` | Media library path (hash/filename under `/storage/…`) | Public disk. Prefer long cache; a new file/version appears when media is replaced. |
| Laravel OG image routes | **None** | Do not boot the app to serve OG JPEGs. |

No vendor CDN coupling. Absolute URLs use the canonical origin so a CDN in front of `APP_URL` still matches `og:image`.

---

## 11. Editorial override rules

Editors **may** set:

- SEO title (`meta_title`)
- SEO description (`meta_description`)
- Dedicated OG media (collection `social-og`, UI deferred)

Platform **always** derives:

- canonical URL / `og:url`
- image width/height when known
- fallback title, description, image
- `og:type` mapping
- robots / indexability

Empty editorial fields are valid. Defaults are mandatory.

---

## 12. Privacy / indexability rules

| Surface | Indexable? | Preview |
|---|---|---|
| `/` and active CMS pages | yes | Resource metadata + image hierarchy |
| Inactive CMS (`is_active=false`) | no | HTTP 404 |
| First path segment in `config/cms.php` reserved list (`admin`, `login`, `user`, …) | no | `noindex, nofollow`; site title + default OG; **no** CMS/admin copy as the social title |
| Secure Capability / membership PII | n/a | No OG helper for those resources |

Do not use expiring signed media URLs as `og:image`.

---

## 13. Tests added or required

Added:

- `tests/Unit/PublicDelivery/PublicMetadataResolverTest.php`
- `tests/Feature/PublicDelivery/PublicMetadataHttpTest.php`

Covered: title/description hierarchy and HTML sanitization; explicit vs featured vs default image; signed URL rejection; query stripping; Host-header independence; welcome + CMS HTML tags; inactive 404; admin `noindex`; default JPEG 1200×630.

Later slices (publications, knowledge, awards) must add a resolver-input mapping test when those models exist — not now.

---

## 14. Implementation performed

- `config/public_delivery.php`
- `app/Support/PublicDelivery/*` (`PublicMetadata`, `PublicMetadataInput`, `CanonicalUrl`, `PublicMetadataResolver`)
- Shared Inertia `seo` + Blade partial
- Welcome + `PageController` resource mapping
- `Page` collections/conversions: `social-og`
- Placeholder default JPEG
- `.env.example` origin override
- Tests, ADR 0004, this document, Architecture Baseline note

Not changed: ACL, auth, membership/publishing/knowledge/awards domains, queues, SSR.

---

## 15. Items deliberately deferred

- Brand-approved 1200×630 artwork (replace `ab2ti-og-default-v1.jpg`)
- Branded per-resource title-card generation (hierarchy step 3)
- CMS admin UI to pick `social-og` media
- JSON-LD, sitemap.xml, robots.txt policies beyond meta robots
- Inertia SSR
- Changing Ziggy `location` off the request host
- `article` OG type until Publication/Knowledge exist
- Queueing media conversions
- CDN vendor config
- Editorial fields on future business tables (those slices add columns later)

---

## 16. Readiness assessment for Phase 2A

**Ready to start Phase 2A business implementation** on the authorization/schema contracts from Phase 1, using this public-delivery capability for any new public URL.

Phase 2A should **not** invent a second SEO stack. When the first public Publication/Knowledge/Award URL ships, map it through `PublicMetadataInput`.

Placeholder OG artwork is acceptable until brand supplies a replacement; it is not a Phase 2A architecture blocker.
