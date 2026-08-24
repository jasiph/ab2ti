# AB2TI 2.0 — Phase 4C.3 Public Lapak experience

**Status:** Implemented  
**Date:** 2026-08-22  
**Does not start:** Phase 4C.4 (RC hardening/closure).  
**Does not introduce:** cart, checkout, orders, payment, escrow, invoice, wallet, shipping, buyer accounts, reviews, ratings, wishlists, chat, commission, seller subscription, or transaction history.

---

## 1. Public routes

Named family, registered in `routes/web.php` after Knowledge and before the CMS catch-all:

| Method | URL | Name | Controller |
|---|---|---|---|
| GET | `/lapak` | `lapak.index` | `PublicLapakController@index` |
| GET | `/lapak/{lapak}` | `lapak.show` | `PublicLapakController@show` |
| GET | `/lapak/{lapak}/produk/{listing}` | `lapak.listings.show` | `PublicLapakController@listing` |

Slug constraint: `[a-z0-9]+(?:-[a-z0-9]+)*`.

Query-driven discovery (no category route tree):

```text
/lapak?q=beras
/lapak?kategori=benih
/lapak?q=pupuk&kategori=sarana-produksi
```

Surface separation:

```text
/lapak/...        public classified directory
/akun/lapak/...   authenticated owner workspace (Phase 4C.2)
/admin/lapak/...  staff moderation (Phase 4C.1)
```

CMS first segment `lapak` remains reserved so Pages cannot occupy the prefix. Real public routes now own it.

---

## 2. Delivery architecture

Public controllers resolve by slug **and** `publicEligible()`. Implicit Eloquent binding is not used.

Presentation arrays come from `LapakViews` (`publicListingCard`, `publicStorefront`, `publicListingDetail`, `publicPriceLabel`). Raw Eloquent models are not passed to Vue.

Pages reuse the Phase 4A/4B shell: `FrontendLayout`, `PublicContainer`, `PublicSection`, `SectionHeading`, `PublicArchiveSearch`, `BasePagination`.

Lapak-specific public components (only where reused):

- `PublicLapakListingCard`
- `LapakContactActions`
- `LapakImageGallery`

---

## 3. Public eligibility reuse

Phase 4C.1 scopes are the only eligibility predicates:

- `Lapak::publicEligible()` — moderation `published`
- `LapakListing::publicEligible()` — listing `published` + `available` + parent Lapak published + category `is_active`

Controllers and `PublicLapakSearch` compose those scopes. Moderation predicates are not duplicated.

**Unavailable listings are not publicly discoverable.** Their public detail URL returns the branded 404. There is no second “visible but unavailable” public behavior.

---

## 4. Search and filter

`App\Support\Lapak\PublicLapakSearch`:

- Bound search term from `PublicContentSearch::fromRequest()` (length-limited). LIKE wildcards in the term are escaped. No raw SQL concatenation of user input besides the escaped LIKE.
- Searches public fields only: listing title/summary/description, Lapak name/location_text, category name.
- Does **not** search Member/User email, phone, or legal name.
- Active-category filter via `kategori={slug}`. Invalid or inactive slugs yield an empty result (`whereRaw('1 = 0')`), not an error.
- Pagination 12, `withQueryString()`.
- **Sort:** `published_at` descending, then `id` descending. No relevance ranking in V1.
- Index/card queries select card columns only (no listing `description`, no moderation notes, no Member).

---

## 5. Storefront

`/lapak/{slug}` is the public seller identity. Exposed fields: Lapak name, cover, summary, description, location_text, and explicit Lapak contact channels.

**Member personal name is not shown.** Public seller identity is the Lapak name. Phase 4C.1/4C.2 did not establish `members.legal_name` as a public storefront field.

Listings on the storefront are `publicEligible()` only, paginated. Empty state does not mention draft/pending counts.

---

## 6. Listing detail

`/lapak/{lapak}/produk/{listing}` is scoped to the named Lapak. A public listing under Lapak B is 404 at Lapak A’s URL.

Exposed: title, category name, images, summary, description (plain text, escaped in Vue), public price label, Lapak identity/location, contact CTA, link back to storefront.

Not exposed: moderation notes/status, owner account, Member records, internal IDs.

---

## 7. Price presentation

Public labels (`LapakViews::publicPriceLabel`) — not raw enums, not a commerce/pricing engine:

| Mode | Public copy |
|---|---|
| `fixed` | `Rp 25.000` or `Rp 25.000 / kg` |
| `from` | `Mulai Rp 25.000` |
| `negotiable` | `Harga dapat ditanyakan` |
| `hidden` | `Hubungi penjual untuk harga` |

Admin/owner screens keep the existing `priceLabel()` (including “Nego (…)”) and are unchanged.

---

## 8. Contact-link normalization

`LapakPublicContact` builds channels from Lapak `contact_phone`, `contact_whatsapp`, and `contact_email` only. No fallback to `members.phone`, `members.email`, `users.email`, or application fields.

| Channel | Rule |
|---|---|
| WhatsApp | Digits only; `08…` / `8…` → `62…`; `https://wa.me/{digits}` plus optional `?text=` prefill. Rejects `javascript:`, `data:`, `vbscript:`, and `http(s):` input. |
| Phone | `tel:+{digits}` after the same digit normalization. |
| Email | `mailto:` only if `filter_var` email validation passes and the value has no spaces/`<>"'`. |

Sellers cannot supply arbitrary hrefs. Prefill examples:

- Storefront: `Halo, saya melihat Lapak "{name}" di Lapak Anggota AB2TI.`
- Listing: `Halo, saya melihat produk "{title}" di Lapak Anggota AB2TI.`

Malformed values omit that channel rather than emitting an unsafe link.

No validation schema change was required for 4C.3; generation is strict on output.

---

## 9. Privacy boundary

**Exposed:** Lapak public name, summary/description, location_text, explicit Lapak contact, published listing content, active category name, public images, price indication.

**Not exposed:** User email, Member email/phone/legal_name/address, membership application data, account-link status, `user_id`, `source_application_id`, `created_by`, moderation notes/status, internal IDs.

Search does not query private Member/User columns.

---

## 10. Media

Reuses Spatie collections from 4C.1:

- Lapak cover: `lapak-cover`
- Listing gallery: `listing-images` (thumb conversion already registered)

Cards use listing thumb, then Lapak cover, then a non-breaking color placeholder. File Manager admin routes are not used. No second image backend.

Gallery is a small keyboard-accessible control set (previous/next + labelled thumbnails). No third-party gallery library.

---

## 11. Navigation and homepage

`useNavigation().frontendMenus` now includes **Lapak Anggota** → `lapak.index` (after Teknologi Tani). Navbar, mobile nav, and footer stay in sync. Visibility does not depend on there being products. Global header CTA placement was later corrected in `docs/implementation/pre-4c4-public-navigation-membership-cta-repair.md`.

Homepage (no shell redesign):

- Pathway card: Lapak Anggota / “Jelajahi Lapak” intent via existing pathway list (`sm:2 lg:3` grid).
- `LatestLapakListings`: up to 3 `publicEligible()` listings, deterministic latest-published order, eager-loaded media/category/Lapak. Empty preview remains visible.

---

## 12. SEO / canonical / OG

Central `PublicMetadataResolver` only. Added `forLapak()` and `forLapakListing()`. Directory uses existing `forPublicInformationalPage('lapak', …)`.

| URL | Title / description | Canonical | OG image |
|---|---|---|---|
| `/lapak` | Lapak Anggota AB2TI + classified intro (no checkout promise) | `/lapak` (query stripped) | Platform default |
| `/lapak/{slug}` | Lapak name; summary then description | `/lapak/{slug}` | Cover, else default |
| `/lapak/{slug}/produk/{listing}` | `{title} — {lapak name}` | nested path | Primary listing image, else Lapak cover, else default |

`og:type` is `website` (not `article`, not Product). Twitter/X uses the shared `summary_large_image` card. Indexable `index, follow` on these public pages via the page `seo` prop (same override pattern as Publishing/Knowledge).

**Structured data:** the platform has no generic schema.org pipeline. Product/Offer JSON-LD is deferred so the site is not represented as an e-commerce checkout service. No fake ratings, stock, shipping, or reviews.

**Analytics:** no seller view counters. Generic page analytics, if already present, is unchanged.

---

## 13. Tests

- `tests/Feature/Lapak/LapakPublicDeliveryTest.php`
- `tests/Unit/Lapak/LapakPublicContactTest.php`
- Updated public-experience and owner-workspace assertions that previously required `/lapak` 404

Coverage: directory eligibility/search/category/pagination/empty; storefront moderation 404s and privacy; listing eligibility including unavailable/inactive category/unpublished parent; nested ownership; contact safety; SEO; CMS prefix ownership; homepage leakage.

---

## 14. Deferred to Phase 4C.4

- Abuse/contact hardening (rate limits, spam)
- Full accessibility and mobile/responsive QA pass
- Malformed media/data edge cases
- SEO edge cases (very long titles, missing summaries)
- Search/pagination edge cases
- Moderation race conditions at publish time
- Broader public privacy audit
- Full acceptance/regression matrix
- Optional structured data if a generic capability is added later
