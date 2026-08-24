# AB2TI 2.0 — Phase 4C.1 Lapak Anggota domain & admin moderation

**Status:** Implemented  
**Date:** 2026-08-22  
**Does not start:** Phase 4C.2 was member self-service; Phase 4C.3 is the public storefront (implemented separately).  
**Does not introduce:** cart, checkout, payment, shipping, escrow, orders, commissions, chat, reviews, wishlists, seller plans, Community, or Koperasi.

---

## 1. Domain model

Canonical ownership:

```text
Member (members)
  └── Lapak (lapaks)                 one per Member (unique member_id)
        └── LapakListing (lapak_listings)
              └── LapakCategory (lapak_categories)   platform taxonomy
```

- A Lapak is the member storefront. A listing is not the Lapak.
- Listings belong to the Lapak, not to `User`.
- Categories are platform-owned and reusable across Lapak.
- Future self-service authorization is designed as `Account → Member → Lapak → Listing`. 4C.1 does **not** create the Account→Member link.

## 2. Tables and models

| Concept | Table | Model |
|---|---|---|
| Storefront | `lapaks` | `App\Models\Lapak` |
| Product / listing | `lapak_listings` | `App\Models\LapakListing` |
| Platform category | `lapak_categories` | `App\Models\LapakCategory` |

`Member::lapak()` is a `HasOne`. `Lapak::member()` / `listings()`. `LapakListing::lapak()` / `category()`.

Staff-assisted create is justified: there is no member workspace yet, so operators onboard a Lapak by selecting an existing `Member`.

`member_id` is set only on create and is not editable afterwards.

## 3. One-Member-one-Lapak

1. **Application:** `CreateLapak` locks the Member row, rejects a second Lapak with `UniqueMemberLapakException`.
2. **Validation:** `StoreLapakRequest` `unique:lapaks,member_id`.
3. **Database:** `lapaks.member_id` unique foreign key, `restrictOnDelete`.

## 4. Moderation lifecycle

Shared enum `LapakModerationStatus` on both Lapak and listing (not a single `is_active`):

| Value | Admin label |
|---|---|
| `draft` | Draf |
| `pending_review` | Menunggu tinjauan |
| `published` | Tayang |
| `rejected` | Ditolak |
| `suspended` | Ditangguhkan |

Allowed transitions:

```text
draft           → pending_review | published
pending_review  → published | rejected | draft
published       → suspended | draft
rejected        → draft
suspended       → published | draft
```

Reject and suspend require a note. Publish Lapak requires name, slug, and at least one contact channel. Publish listing requires title, slug, and an **active** category.

Actions: `TransitionLapakModeration`, `TransitionLapakListingModeration`.

## 5. Availability

Listing-only enum `LapakListingAvailability`: `available` | `unavailable`.

A listing may be `published` and still `unavailable`. Public eligibility requires both published moderation **and** available, plus a published Lapak and an active category.

Scopes: `Lapak::publicEligible()`, `LapakListing::publicEligible()`. These exist for 4C.3; they are not wired to public routes in this phase.

## 6. Price representation

`LapakPriceMode`: `fixed`, `from`, `negotiable`, `hidden`.

- `fixed` / `from` require `price_amount` (`decimal(12,2)`, nullable otherwise).
- `price_unit` is an optional display label (e.g. `kg`).
- IDR formatting (`Rp …`) happens in `LapakViews` only.
- No Money framework, currencies table, tax, discount, or payment state.

## 7. Media

Reuses File Manager + Spatie Media Library copy (same pattern as Publication/Knowledge covers):

| Owner | Collection | Cardinality |
|---|---|---|
| Lapak | `lapak-cover` | single + `thumb` |
| Listing | `listing-images` | many + `thumb` |

`App\Support\Lapak\LapakMedia` copies from `File` (`default` collection) or a new upload that is first stored as a `File`. No separate storage subsystem.

## 8. Slugs

Helpers: `LapakSlug`, `LapakListingSlug` (unique per Lapak), `LapakCategorySlug`.

- Empty posted slug is generated from name/title.
- Collision is resolved with `-2`, `-3`, … inside the helper (not in controllers).
- Posted slug on update is kept when it remains unique for that record; title changes do not rewrite an existing slug unless the slug field is cleared.

Public URL stability is prepared; public routes are not registered.

## 9. Content rendering

Descriptions are plain textarea fields. Tiptap / `EditorialContent` is not used. Admin views do not render unsanitized HTML.

## 10. ACL

Added to `config/acl.php` group `lapak`:

- `lapak.view`
- `lapak.manage`
- `lapak.moderate`

Assigned to existing Spatie role `membership-admin`. No new Lapak role. No Spatie `member` role. `editor` does not receive Lapak. `Gate::before` for `super-admin` is unchanged.

Enforcement: route `permission:` middleware + `LapakPolicy` / `LapakListingPolicy` / `LapakCategoryPolicy`. Navigation visibility is UX only.

## 11. Admin screens

| Route name | Screen |
|---|---|
| `admin.lapak.storefronts.index/create/show/edit` | List, staff create, detail, edit |
| `admin.lapak.storefronts.moderate` | Status change |
| `admin.lapak.listings.index/create/show/edit` | List with filters, create, detail, edit |
| `admin.lapak.listings.moderate` | Listing status change |
| `admin.lapak.listings.availability` | Availability only |
| `admin.lapak.categories.index/store/update/toggle` | Taxonomy; no destroy |

Admin nav group **Lapak Anggota** (permission `lapak.view`). Public `frontendMenus` unchanged.

Staff can see: whose Lapak, which Lapak a product belongs to, publication status, availability, and why it is not public (`ineligible_reason`).

## 12. Account → Member linking audit

**Linking does not exist.**

| Question | Result |
|---|---|
| Relation present? | No |
| Where represented? | `User` has no `member_id` / `member()`. `Member` has `source_application_id` and `created_by` (staff who approved), not a User FK |
| Usable for ownership auth? | No |
| 4C.2 requirement? | Yes: a **controlled** Account→Member link must be added before member self-service |

4C.1 does not invent an ad-hoc link. Admin selects `Member` directly.

## 13. Public exposure

- No public Lapak named routes in this phase (`lapak.index` / `lapak.show` were added in Phase 4C.3).
- CMS reserved first segment `lapak` so `/lapak` cannot become a CMS page.
- Public shell / `frontendMenus` unchanged in 4C.1.

## 14. Seed / factories

Factories: `LapakFactory`, `LapakListingFactory`, `LapakCategoryFactory`.

No production taxonomy seed. Categories are created through admin. Temporary demo products are not encoded as business truth.

## 15. Deferred

**4C.2:** Account→Member link, ownership policies for the authenticated member, “Lapak Saya”, member listing CRUD, seller login/claim.

**4C.3:** Public storefront, listing search/detail, public nav item, public serializers beyond eligibility scopes.

## 16. Known limitations

- Member picker on create lists at most 200 Members without a Lapak.
- Collision of a posted slug is auto-suffixed rather than shown as a hard error (same helper style as other domains).
- No destructive delete of Lapak/listing/category; deactivate/suspend instead.
- SQLite tests enable the unique constraint; operator MySQL must run the forward migration.
