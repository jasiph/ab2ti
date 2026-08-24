# 0004 — Public delivery SEO and social sharing

Date: 2026-08-20
Status: Accepted

## Context

AB2TI 2.0 will publish many public resources (CMS pages now; publications, knowledge articles, Award editions, and similar later). Social crawlers and search engines must see title, description, canonical URL, Open Graph, and Twitter/X tags in the **initial HTTP HTML**, without Vue hydration.

The kernel had CMS `meta_title` / `meta_description` and Inertia Vue `<Head>`, but no canonical URL, Open Graph, Twitter cards, or crawler-visible metadata contract. Enabling Inertia SSR solely for head tags would add Node infrastructure the project does not run (`resources/js/ssr.js` exists; `npm run build` is client-only).

## Decision

SEO / Open Graph is a **platform public-delivery capability**, not a per-domain feature.

- Canonical identifiers and URLs are produced from `config('public_delivery.canonical_origin')` (defaults to `APP_URL`), never from the incoming `Host` header.
- One resolver (`App\Support\PublicDelivery\PublicMetadataResolver`) maps a small input DTO to metadata. Future resources supply input; they do not get `ArticleOgService`-style classes.
- Crawler-visible tags are rendered in Blade (`resources/views/partials/public-delivery-meta.blade.php`) from the Inertia `seo` prop. Inertia SSR is **not** required for this.
- OG images are 1200×630 JPEG by default. Source media may produce a Spatie `social-og` conversion at publish/upload time (non-queued). Crawlers fetch a static asset. Site-wide fallback: `public/images/og/ab2ti-og-default-v1.jpg`.
- Reserved first-path segments (`config/cms.php`) are never indexable public previews.
- Inactive CMS pages remain 404.

## Consequences

- Controllers/routes for public resources must pass `seo` (or accept the shared default).
- Brand-approved replacement of the placeholder default OG image is an operations/design task, not a new architecture.
- Per-resource branded title-card generation (beyond cropping source media) is deferred.
- Dedicated CMS editorial UI for the `social-og` collection is deferred; the collection is registered so later UI can attach it.
