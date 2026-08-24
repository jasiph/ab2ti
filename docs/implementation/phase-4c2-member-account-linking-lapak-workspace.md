# AB2TI 2.0 — Phase 4C.2 Member account linking & Lapak Saya workspace

**Status:** Implemented  
**Date:** 2026-08-22  
**Does not start:** Phase 4C.3 public storefront (implemented separately: `docs/implementation/phase-4c3-public-lapak-experience.md`).  
**Does not introduce:** Spatie `member` role, auto-link by email/phone, File Manager for members, checkout, orders, payment, shipping, chat, ratings, Koperasi, or Community.

Site-access provisioning for Members who do not yet have a User (capability-gated activation, Berikan Akses Situs) is documented in `docs/implementation/member-account-provisioning-site-access.md`. This 4C.2 file is not reopened as a Lapak phase.

---

## 1. Account ↔ Member schema

Platform identity infrastructure (not Lapak-specific):

```text
users.id  ←  members.user_id  (nullable, unique, nullOnDelete)
members.linked_at             (nullable timestamp)
```

| Cardinality | Enforcement |
|---|---|
| One User ↔ at most one Member | Unique index on `members.user_id` |
| One Member ↔ at most one User | Single nullable FK column |

`user_id` is **not** fillable. `source_application_id` and `created_by` remain provenance, not ownership.

A join table was not used: V1 is strictly 1:1 and the same FK authorizes future member-only services (Kartu Anggota, Koperasi badge) without a Lapak-specific link table.

---

## 2. Secure Capability Grant reuse decision

**Reuse** the platform Secure Capability Grant with a dedicated purpose. Do **not** use `/akses/{secret}` (revision loginless session).

| Topic | Decision |
|---|---|
| Purpose | `member-account-link` (`config/secure_capability.php` + `config/membership.php`) |
| Target | `member` / `members.id` |
| TTL | 168 hours |
| Secret | Digest only; plaintext shown once to staff for out-of-band delivery |
| Reissue | Live siblings revoked |
| Consume | On successful confirm |
| Auth | **Required before show/confirm** (guest → login with intended URL) |
| Elevation | Purpose cannot grant Spatie permissions |

Email delivery is not established. Staff copy the invitation URL and send it through a trusted external channel. The URL is flashed once (`member_account_link_url`) and is not stored in plaintext.

Public Fortify registration remains closed. Claim requires an existing User account.

---

## 3. Linking / claim flow

```text
Member (staff detail)
  ↓ IssueMemberAccountLink (purpose member-account-link)
claim/link capability (digest, TTL, revoke-on-reissue)
  ↓ /akun/tautkan/{secret}  (auth required)
authenticated User reviews Member identity
  ↓ explicit POST “Hubungkan akun saya”
LinkUserToMember (lock Member + User, invariants)
  ↓ consume grant
members.user_id + linked_at
  ↓
redirect /akun/lapak
```

Staff roles `super-admin`, `membership-admin`, and `editor` cannot confirm a claim onto the staff account (`User::isPlatformStaff()`).

Matching email or phone never creates the link.

---

## 4. Admin initiation

Staff with `membership-members.update` on Member detail:

- see status: Belum terhubung / Undangan dibuat / Terhubung / Undangan kedaluwarsa or dibatalkan
- issue or rotate invitation
- revoke unused invitation
- never see the secret after leaving the flash

Linked members cannot be re-invited.

---

## 5. Ownership authorization

```text
User → linked Member (members.user_id)
     → Member's Lapak (lapaks.member_id unique)
        → Member's Listings
```

Owner HTTP uses `ownsLapak()` / `ownsListing()` and FormRequest `authorize()`, **not** `authorize()` against Lapak policies. `Gate::before` for `super-admin` would otherwise bypass ownership.

Submitted `member_id` / `lapak_id` are ignored. Owner create derives Member from the session; listing create derives Lapak from the linked Member.

Members receive **no** `lapak.view|manage|moderate` and no Spatie `member` role.

---

## 6. Member vs admin

| Actor | May |
|---|---|
| Owner | Create own Lapak if none; edit owner-facing fields; upload own images; manage own listings; choose **active** categories; change availability; submit draft/rejected; view moderation notes |
| Admin (`lapak.*`) | All 4C.1 CRUD/moderation, including publish/reject/suspend and category taxonomy |

Owner cannot publish.

---

## 7. Owner moderation workflow

4C.1 statuses preserved. Additional transitions for owner resubmit: `published → pending_review`, `rejected → pending_review`.

| State | Owner may |
|---|---|
| `draft` | Edit content; submit → `pending_review` |
| `pending_review` | Content **locked**; availability still allowed |
| `published` | Availability anytime; **content save unpublishes** to `pending_review` (smallest safe model; no revision table) |
| `rejected` | See note; edit; resubmit → `pending_review` |
| `suspended` | View only; cannot self-publish; availability locked |

---

## 8. Lapak Saya workspace

Authenticated routes (not `/admin`, not public `/lapak`):

| Route | Screen |
|---|---|
| `GET /akun/tautkan/{secret}` | Confirm link |
| `GET /akun/lapak` | Unlinked / Empty / Overview |
| `GET/POST /akun/lapak/buat` + store | Create Lapak |
| `GET/PUT /akun/lapak/{lapak}/ubah` | Edit Lapak |
| `POST /akun/lapak/{lapak}/kirim` | Submit Lapak |
| `/akun/lapak/produk` | Listing index/create/edit/submit/availability |

Unlinked users see an explanation (no Member picker, no email-claim form). Staff hitting the workspace receive 403.

One-Member-one-Lapak remains `CreateLapak` + unique `lapaks.member_id`.

---

## 9. Media authorization

File Manager `GET admin/files/json` lists **all** platform files and requires `media.view`. Members must not receive `media.*` or `cms.*`.

Owner controllers pass `existingCoverId = null` and `fileIds = []`. Only `new_cover` / `new_image` uploads are applied. Posting someone else’s `existing_*_asset_id` does not attach.

---

## 10. Contact privacy

Lapak `contact_phone` / `contact_whatsapp` / `contact_email` are seller-public fields. They are **not** copied from `members.email` / `phone`. The owner form states they will become public when the Lapak is published (public storefront itself is Phase 4C.3).

---

## 11. Navigation

- Public `frontendMenus` unchanged (no Lapak).
- Authenticated **linked** users: “Lapak Saya” on public navbar/sidebar and AppTopbar account menu.
- Unauthenticated visitors do not see it.

CMS reserved first segment: `akun`.

---

## 12. Tests

- `tests/Feature/Membership/MemberAccountLinkTest.php`
- `tests/Feature/Lapak/OwnerLapakWorkspaceTest.php`
- `tests/Feature/Lapak/OwnerLapakMediaSecurityTest.php`

Coverage: valid/invalid/expired/revoked/consumed claim, replay, already-linked, email non-inference, staff cannot claim, `/akses` isolation, IDOR, moderation lifecycle, one-Lapak invariant, media isolation.

---

## 13. Deferred to Phase 4C.3

Public `/lapak` family is implemented in Phase 4C.3 (`docs/implementation/phase-4c3-public-lapak-experience.md`).
