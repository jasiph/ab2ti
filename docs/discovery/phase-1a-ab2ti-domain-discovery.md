# Phase 1A — AB2TI Existing Site & Domain Discovery

**Status:** Discovery only. Not an architecture decision. Not a schema. Not an implementation plan.  
**Date inspected:** 2026-08-19  
**Repository HEAD:** `38c952afee952a05c3d09f160b9bf789b1ab0c06` (`chore/ab2ti-foundation-hardening-b`)  
**Live legacy site inspected:** `https://ab2ti.org` (WordPress REST API + public pages)  
**Canonical policy:** `docs/project/CONSTITUTION.md`  
**Current platform facts:** `docs/project/ARCHITECTURE_BASELINE.md`

This document records what exists today so Phase 1B can make product and information-architecture decisions. It does **not** treat the current Laravel kernel as AB2TI 2.0, and it does **not** treat `ab2ti.org` as the desired AB2TI 2.0 architecture.

Classification keys used in later sections:

| Code | Meaning |
|---|---|
| **A** | Platform capability (keep and reuse) |
| **B** | Domain feature (future application module) |
| **C** | CMS content (editable pages / editorial HTML) |
| **D** | Structured domain data (records, not just pages) |
| **E** | External / legacy process to replace |
| **F** | Archive / migration content |
| **G** | Needs product decision |

Disposition keys used in the mapping:

`KEEP` · `ADAPT` · `REPLACE` · `MIGRATE CONTENT` · `ARCHIVE` · `NEEDS DECISION`

---

## 1. Executive Summary

AB2TI 2.0 currently has a **platform kernel**, not an AB2TI business application. After Phase 0, the repository can authenticate staff, authorize admin modules, edit HTML pages with Tiptap, manage media, and render a public landing plus CMS pages. It cannot yet represent members, cooperatives, news archives, galleries, programs, documents, or regional contribution workflows.

The live organization site (`ab2ti.org`) is a **WordPress publishing site** with a small page set, a news stream, a few agricultural articles, gallery/media, documents, and **external registration forms**. Association membership and Koperasi Pusat membership are already separate on the legacy site. That separation is a domain rule for AB2TI 2.0 and must not be collapsed.

The existing CMS **can** host editable organization pages such as Tentang AB2TI. It **cannot**, by itself, host news listing, member directories, cooperative administration, seed catalogs, or moderated community submissions. Those are later domain decisions, not CMS defaults.

The current `User` model is a **platform account** (staff/admin). It is not a member record. Public registration is disabled (ADR 0002). Future AB2TI members and cooperative members must not be assumed to be the same type of authenticated user.

Residual Presbi/church influence is naming and IA residue, not resurrected church modules: `/sermons` and `/events` aliases, a landing component still named `ServiceEvents`, unused Jetstream dashboard cards, and possible leftover church tables in developer databases.

**Do not implement from this document.** Phase 1B should answer the product questions in §10 before any schema, routes, or Vue pages are designed.

---

## 2. Current Repository Capability Map

### 2.1 What the kernel actually provides

| Capability | Reality in this repo | Relevance to AB2TI domains |
|---|---|---|
| Public website shell | `FrontendLayout`, landing (`/`), CMS catch-all `/{slug}` | Can become the public site; current nav/IA is placeholder |
| Auth identity | Fortify + Jetstream Inertia; `User` = name/email/password + Spatie roles | Staff/admin accounts only today |
| ACL | `config/acl.php` + Spatie + `permission:` middleware on `/admin/*` modules | Pattern future modules must follow |
| CMS pages | `Page`: unique slug, HTML content, meta, `settings` JSON, `is_active`, cover media | Organization pages (About, Contact, similar) |
| Tiptap editor | `RichEditor.vue` on CMS page form only | Dynamic editable HTML; custom div/block/image extensions |
| Block templates | HTML snippets inserted into Tiptap | Reusable layout fragments inside pages |
| Landing hero | `Hero` + Spatie `hero-backgrounds` | Homepage campaign/banner content |
| Landing welcome copy | `WelcomeSection` (image via URL, not Spatie) | Homepage intro; not a general CMS |
| Media library | `File` + Spatie Media Library + File Manager | Images/files for pages, heroes, future content |
| i18n | `id` / `en`, session locale, `lang:merge` | UI strings, not content translation |
| Admin UI | `AppLayout` + `useNavigation` permission-filtered menus | Staff backend |
| API tokens | Jetstream API feature + Sanctum `GET /api/user` | Not a public AB2TI API |

### 2.2 Backend inventory (relevant)

Present under `app/`:

| Kind | What exists | Notes |
|---|---|---|
| Models | `User`, `Page`, `BlockTemplate`, `Hero`, `WelcomeSection`, `File` | No member, news, gallery, program, or cooperative models |
| Admin controllers | Users, Roles, Pages, BlockTemplates, Heroes, WelcomeSection, FileManager, Dashboard | Resource CRUD; inline `$request->validate()` |
| Public controllers | `PageController@show` | Active pages only |
| Support | `CmsPath` | Reserved first segments + slug rules (ADR 0003) |
| Actions | Fortify/Jetstream only | `CreateNewUser` exists but public registration is off |
| Commands | `acl:sync`, `lang:merge` | ACL sync must not be run against the active DB as a side effect |
| Middleware | `SetLocale`, `HandleInertiaRequests`, Spatie `permission:` | Shared Inertia auth props use dotted keys `auth.user.roles` / `auth.user.permissions` |

Not present (do not invent in later phases unless an ADR says so):

- `app/Http/Requests`
- `app/Policies`
- `app/Services`
- `app/Enums`, repositories, observers
- News/posts, tags, menus, scheduling, drafts beyond `is_active`
- Membership, cooperative, program, document, or community modules

Dominant pattern: **route → controller → inline validation → Eloquent (+ Spatie Media) → Inertia**.

### 2.3 Routes that matter

Public:

- `GET /` — landing (hero + welcome + hardcoded program cards)
- `GET /about`, `/sermons`, `/events`, `/contact` — CMS aliases to those slugs
- `GET /{slug}` — CMS catch-all, last, reserved-path constrained
- `GET /language/{language}` — `id`/`en`
- Fortify login / password reset (registration **not** registered)
- Jetstream `/user/*` profile, 2FA, API tokens

Authenticated:

- `GET /dashboard` and `GET /admin/dashboard` — **auth only**, no module permission
- `/admin/users`, `/admin/roles`, `/admin/files`, `/admin/heroes`, `/admin/pages`, `/admin/block-templates`, `/admin/welcome-section` — Spatie `permission:` per action

### 2.4 Vue pages that matter

| Area | Pages |
|---|---|
| Public | `Frontend/Landing/Welcome`, `Frontend/Page/Show` |
| Admin | Users, Roles, Pages, BlockTemplates, Heroes, WelcomeSection, FileManager, Dashboard |
| Account | Jetstream Profile, API tokens, Auth screens |

There are **no** Vue pages for news, members, koperasi, gallery, programs, or documents.

### 2.5 Permissions currently defined

From `config/acl.php`:

- `users.view|create|update|delete`
- `roles.manage`
- `cms.view|create|update|delete` — pages, block templates, welcome section
- `access settings` — defined, **unused**
- `media.view|create|update|delete` — file manager
- `heroes.view|create|update|delete`

Seeded roles: `super-admin` (Gate::before bypass) and `user` (only `media.view`). The seeded `user` role is **not** an AB2TI member role.

### 2.6 What this kernel can support vs cannot

**Can support now (with content only, no new modules):**

- Editable Tentang AB2TI / Contact / similar organization pages
- Nested CMS URLs such as `about/team` if product wants that
- Homepage hero/welcome copy
- Image embedding from the media library into page HTML
- Staff administration of users/roles/content/media

**Cannot support without new domain work (do not stretch CMS into these):**

- News/press listing, categories, authors, dates as first-class publishing
- Member registration, verification, cards, regional relationships
- Koperasi membership, shares, cooperative frontend/backend
- Structured seed/variety catalogs
- Program/event lifecycle (ICBB Award as a typed object)
- Community submission + moderation
- Document libraries with access control by membership
- Public self-service accounts

---

## 3. Existing Reusable Component Inventory

Classification is by **current use**, not by hoped-for reuse. Feature-specific means “tied to one current feature or leftover name,” not “AB2TI business feature.”

### 3.1 General / reusable

| Component | Status |
|---|---|
| `Base/BaseButton` | Canonical in admin |
| `Base/BaseCard` | Canonical in admin |
| `Base/BaseInput` | Canonical in admin |
| `Base/BaseSelect` | Canonical in admin |
| `Base/BaseList` | Canonical in admin |
| `Base/BaseModal` | Canonical in admin + confirm dialog |
| `Base/BasePagination` | Canonical in admin |
| `Checkbox`, `InputLabel`, `InputError`, `TextInput` | Jetstream/auth forms; overlap with Base |
| `ActionMessage`, `ActionSection`, `FormSection`, `SectionTitle`, `SectionBorder` | Jetstream profile/API forms |
| `Dropdown`, `DropdownLink`, `NavLink`, `ResponsiveNavLink` | Navigation primitives |
| `LanguageSwitcher`, `ThemeToggle` | Global UI |
| `Banner`, `UndoToast` | Feedback |
| `ApplicationLogo`, `ApplicationLogoWithText`, `ApplicationMark`, `AuthenticationCard`, `AuthenticationCardLogo` | Branding / auth chrome |

Unused candidates (do **not** promote or delete in this phase):

- `Base/BaseSearchSelect`
- `DashboardStatCard`

### 3.2 Layout / navigation

| Item | Status |
|---|---|
| `Layouts/AppLayout.vue` | Canonical admin |
| `Layouts/FrontendLayout.vue` | Canonical public |
| `Layouts/Partials/AppTopbar`, `AppSidebar`, `AppFooter`, `FrontendNavbar`, `FrontendSidebar`, `SystemToast` | In use |
| `Composables/useNavigation.js` | Single source of admin + public menus |
| `Layouts/GuestLayout.vue` | Present; **no callers** |
| `Layouts/WebLayout.vue` | Present; **no callers** |

### 3.3 CMS / editor

| Item | Status |
|---|---|
| `Components/RichEditor.vue` | Only used by `Admin/Pages/Form.vue` |
| `TiptapExtensions/DynamicBlock.js` | Custom node for block-template wrappers |
| `TiptapExtensions/GlobalDiv.js` | Preserves `div` + class/style/id |
| `TiptapExtensions/GlobalAttributes.js` | class/style on heading, paragraph, lists, blockquote, image |
| `BlockTemplatePickerModal.vue` | Inserts saved HTML blocks |
| Admin Pages Form/Index | CMS authoring UI |
| Admin BlockTemplates Form/Index | HTML snippet admin (textarea, **not** Tiptap) |
| `Frontend/Page/Show.vue` | Public renderer (`v-html`) |

### 3.4 Media

| Item | Status |
|---|---|
| `FileManager/FileGrid`, `FilePickerModal`, `FilePreviewModal` | File Manager UI |
| `MediaPickerModal.vue` | Thin wrapper around `FilePickerModal` for the editor |
| Admin FileManager Index/Form | Media CRUD |
| `browser-image-compression` | Client-side compress before upload |

### 3.5 Admin infrastructure

| Item | Status |
|---|---|
| Admin Users / Roles pages | Staff account + ACL UI |
| `usePermission.js` | Frontend visibility only |
| `useConfirm.js` + `ConfirmDialog.vue` | Admin destructive confirms |
| Dashboard page | Neutralized foundation placeholder |
| Profile / API token Jetstream pages | Self-service for authenticated users |

### 3.6 Feature-specific (current platform features)

| Item | Status |
|---|---|
| `Frontend/Landing/Components/HeroSection.vue` | Landing hero |
| `Frontend/Landing/Components/WelcomeSection.vue` | Landing welcome |
| `Frontend/Landing/Components/FooterSection.vue` | Public footer; hardcoded contact + placeholder program anchors |
| `Frontend/Landing/Components/ServiceEvents.vue` | Hardcoded “Program Kami” cards |
| `WhatsAppFloat.vue` | Public WhatsApp FAB (`wa.me/622518423005`) |
| Admin Hero / WelcomeSection pages | Landing CMS-adjacent, not general pages |

### 3.7 Legacy / questionable

| Item | Why it is questionable |
|---|---|
| Public routes `/sermons`, `/events` | Church URL names; labeled Farming Tech / News |
| `ServiceEvents.vue` filename + `events` prop | Church leftover naming; content is AB2TI program blurbs |
| `Components/Welcome.vue` | Jetstream Laravel/Laracasts/Tailwind cards still present |
| `Pages/Auth/Register.vue`, `VerifyEmail.vue` | Views exist; features disabled |
| `Pages/PrivacyPolicy.vue`, `TermsOfService.vue` | Jetstream terms feature disabled |
| Jetstream `PrimaryButton` / `SecondaryButton` / `DangerButton` / `Modal` / `DialogModal` / `ConfirmationModal` | Parallel family to Base |
| Seeded `admin@admin.com` / `user@user.com` | Dev seed identities, not AB2TI identities |
| Footer `#program` links | Not real pages |

### 3.8 Obvious duplicate patterns (report only; do not refactor)

1. **Buttons:** BaseButton (admin) vs Primary/Secondary/DangerButton (auth/profile).
2. **Modals:** BaseModal + ConfirmDialog vs Modal / DialogModal / ConfirmationModal.
3. **Inputs:** BaseInput vs TextInput.
4. **Media pickers:** MediaPickerModal is an alias of FilePickerModal.
5. **Welcome naming:** `Pages/Frontend/Landing/Welcome.vue` vs `Components/Welcome.vue`.
6. **Landing programs:** footer “Program” list vs `ServiceEvents` cards vs future real program domain — three different concepts colliding on the word “program.”

Reusable/general components must remain distinct from feature-specific ones. Future AB2TI UI should reuse Base + existing CMS/media primitives rather than cloning them inside a feature folder.

---

## 4. CMS & Tiptap Capability Audit

### 4.1 Page / content model

Table `pages`:

- `title`
- `slug` (unique, normalized, nested allowed, e.g. `about/team`)
- `content` — **HTML string**, nullable
- `meta_title`, `meta_description`
- `settings` — JSON; currently used for `related_links[]` (`label`, `url`)
- `is_active` — boolean; inactive pages 404 on the public site
- timestamps
- Spatie collection `page-cover` (single file) + `thumb` conversion (400×220)

There is no: author, published_at, excerpt, category, tags, revision history, draft vs published beyond `is_active`, scheduling, localization of content, or page hierarchy table (nesting is slug-only).

### 4.2 Slug handling

- `CmsPath::normalize` lowercases, trims slashes, collapses `//`.
- Valid shape: `a-z0-9` segments with `-`/`_`, slash-separated.
- First segment cannot be in `config/cms.php` reserved list (`admin`, `login`, `register`, `dashboard`, etc.).
- Explicit aliases **not** reserved: `/about`, `/sermons`, `/events`, `/contact` (they *are* CMS pages with dedicated routes).
- Catch-all `GET /{slug}` is last and excludes reserved first segments (ADR 0003).

Implication: a future public URL such as `/anggota` or `/berita` is available as a CMS page **or** as a future application route, but not both. Product must choose. Do not claim those slugs as CMS pages if they will become domain modules.

### 4.3 Tiptap implementation

`RichEditor.vue` uses `@tiptap/vue-3` + StarterKit + Image.

Custom extensions:

| Extension | Role |
|---|---|
| `GlobalDiv` | Treats ordinary `div` as a block node; keeps `class`, `style`, `id` |
| `GlobalAttributes` | Keeps `class`/`style` on headings, paragraphs, lists, blockquote, image |
| `DynamicBlock` | Node for `.tiptap-block-wrapper` / `.tiptap-block-content` |

On update, wrappers `.tiptap-block-wrapper` are **unwrapped** and only inner HTML is stored. Templates are therefore **copied HTML**, not live-linked block instances. Editing a block template later does not update pages that already inserted it.

Block templates themselves are authored as a raw HTML textarea, not Tiptap.

### 4.4 Editor toolbar

Toolbar: Bold, Italic, H2, H3, bullet list, Image (media picker), Blocks (template picker), Code/Preview toggle.

Bubble menu: Bold, Italic, H2, H3.

Not present: link UI (StarterKit link exists but `openOnClick: false` and there is no toolbar button), tables, YouTube, attachments other than images, undo/redo buttons, heading H1/H4, ordered-list button (StarterKit supports it; toolbar does not expose it).

Code view is a textarea of HTML. That is the escape hatch for layout-heavy organization pages.

### 4.5 JSON vs HTML storage

**HTML is stored. JSON is not.** Tiptap’s document JSON is never persisted. Public rendering is `v-html="page.content"`.

Implication: the CMS is a trusted-staff HTML publisher. XSS risk is accepted as an admin-only authoring surface. Future untrusted community authors must not write into this field without a different pipeline.

### 4.6 Media embedding and images

- Cover: File Manager library or new upload (compressed in the browser); copied onto the page as `page-cover`.
- Inline images: media picker inserts an HTML snippet (`div` + `img` with File Manager URL).
- URLs are current media URLs, not a stable attachment ID in the HTML. If a file is force-deleted, page HTML can break.
- Hero backgrounds are a separate Spatie collection, not CMS page media.
- Welcome section image is an `image_url` string, not Spatie — inconsistent with Hero/Page.

### 4.7 SEO / meta

Present: `meta_title` (defaults to title on create), `meta_description`, Inertia `<Head>` title + description on `Frontend/Page/Show.vue`.

Absent: Open Graph, Twitter cards, canonical URL, sitemap, robots, JSON-LD, per-locale content.

Landing title is hardcoded (`Selamat Datang`), not a CMS page.

### 4.8 Dynamic page rendering

`PageController@show` loads active page + media and renders `Frontend/Page/Show.vue`: cover banner, title, breadcrumb Home → title, HTML article, optional related links.

This is sufficient for **About AB2TI and similar organization pages** if content is migrated/authored as HTML. It is a single-page template, not a layout system with selectable templates.

### 4.9 CMS permission controls

| Action | Permission |
|---|---|
| List/show pages, block templates, welcome-section edit form | `cms.view` |
| Create pages / block templates | `cms.create` |
| Update pages, toggle, welcome-section update, block templates | `cms.update` |
| Delete pages / block templates | `cms.delete` |
| Media used by the editor | `media.*` |

There is no per-page permission, no “editor vs publisher,” and no public-vs-member visibility. `is_active` is the only publication switch. Frontend nav does not auto-build from pages.

**Welcome Section admin route exists but is missing from the sidebar children** in `useNavigation.js` (only the parent CMS `active` matcher includes it). Operators must know the URL. Discovery finding only; not fixed here.

### 4.10 Can the CMS support Tentang AB2TI?

**Yes, as CMS content (C),** with these caveats:

- Vision/mission, secretariat, and similar copy fit HTML pages.
- Nested subpages are technically possible via slugs.
- Related links can point to other pages.
- A gallery of many photos, a news index, or a member directory does **not** fit this model well.
- Current public nav points at slug `about`, while the live site uses `/tentang-ab2ti/`. URL choice is a Phase 1B IA decision (`NEEDS DECISION`).

Tiptap customizations (div/class/style preservation, block templates, code view) are **platform capabilities to retain** for those organization pages.

---

## 5. Authentication & ACL Capability Audit

### 5.1 Current identity

`User` is a Laravel authenticatable with:

- name, email, hashed password
- optional `email_verified_at` column (Fortify email-verification **feature disabled**; `MustVerifyEmail` not implemented)
- Spatie `HasRoles`
- Jetstream profile-photo trait still on the model though the **feature is disabled**
- 2FA columns / Fortify 2FA enabled
- Sanctum API tokens (Jetstream API feature on)

Provisioning today:

- Admin creates users (`users.create`) and assigns an existing role
- Public self-registration **disabled** (ADR 0002)
- Password reset works
- Login rate limit 5/minute

There is no member number, region, membership status, KTP, photo-for-card, cooperative share, or organization-unit relation on `User`.

### 5.2 How current identity relates to future actors

This is **not** a design. It is an implication map.

| Future actor | Current capability | Architectural implication |
|---|---|---|
| Platform administrator / super-admin | Exists (`super-admin` Gate bypass) | Keep as platform identity |
| Staff / secretariat | Can be modeled as `User` + roles/permissions | Likely the same User table with new permission groups later |
| AB2TI member (asosiasi) | **Does not exist** | Must not assume a member is a `User`. Legacy registration is an external form + payment, not an account |
| Koperasi Pusat member | **Does not exist** | Separate from AB2TI member. Also must not assume it is the same User type |
| Cooperative administrator | **Does not exist** | Could later be a User with a koperasi permission group — product decision |
| Public visitor | Unauthenticated | Default for news, about, gallery |
| Regional contributor | **Does not exist** | Submission + moderation would need a distinct identity/workflow decision |

ADR 0002 already states that user provisioning / invitation onboarding is a future domain decision. Re-enabling Fortify registration without that decision would mix “website account” with “member.”

The seeded role name `user` is especially dangerous if reused to mean “AB2TI member.” It currently only grants `media.view`.

### 5.3 ACL chain (convention future modules must follow)

```
ROLE / PERMISSION
        ↓  defined in config/acl.php, synced with php artisan acl:sync
BACKEND AUTHORIZATION
        ↓  Route::middleware('permission:…') on /admin/* (and Gate::before for super-admin)
ADMIN NAVIGATION / UI
        ↓  useNavigation.js permission field (hide menu)
           usePermission.js (hide buttons) — UX only, never the security boundary
```

Conventions:

1. Add permissions to `config/acl.php` first; do not scatter strings.
2. Sync only when the task explicitly requires it; `--clean` is destructive to extra DB rows.
3. Protect every new admin route with `permission:` middleware. Frontend hiding is not authorization.
4. Add a `useNavigation` entry with the same permission names.
5. Dashboard remains auth-only unless product changes that.
6. Do not use leftover names (`culture.*`, `artikel.*`, sermons, jemaat) for new AB2TI modules.
7. There are still no Policies or Form Requests; new modules should not invent a parallel authorization style without an ADR.

`access settings` is unused. Jetstream `profile.show` is visible to every authenticated user.

### 5.4 Admin navigation map (today)

| Menu | Permission | Backend |
|---|---|---|
| Dashboard | none (auth) | `DashboardController` |
| User Management | `users.view` (+ create/update/delete on actions) | `UserController` |
| Roles & Permission | `roles.manage` | `RoleController` |
| File Manager | `media.view` | `FileManagerController` |
| Hero Manager | `heroes.view` | `HeroController` |
| Pages | `cms.view` | `Admin\PageController` |
| Block Templates | `cms.view` | `BlockTemplateController` |
| System Settings | none → `profile.show` | Jetstream profile (not `access settings`) |
| Welcome Section | `cms.view` / `cms.update` | **routed, not listed in sidebar children** |

Public nav (not ACL): Home, About AB2TI (`about`), Farming Tech (`sermons`), News (`events`), Contact (`contact`).

---

## 6. Legacy AB2TI Information / Feature Inventory

Inspected from the live WordPress site and REST API on 2026-08-19. This is the **current public organization site**, not a specification for AB2TI 2.0.

### 6.1 Legacy technical shape

WordPress with:

- Pages (laman)
- Posts (pos) + categories
- Media / attachments
- A custom type `project` (Proyek) — **empty** in the API
- Typical WP menus

This is a publishing CMS, plus embedded external forms and downloadable documents. It is not an operational membership system.

### 6.2 Public areas (conceptual — do not copy as the 2.0 menu)

| Legacy area | Live URL / source | What it actually is |
|---|---|---|
| Home | `/` | Hero/slogan, ICBB Award highlight, “AB2TI Terkini” (posts), media clips, secretariat |
| Tentang AB2TI | `/tentang-ab2ti/` | Vision, mission (organizational copy) |
| Teknologi Tani | `/teknologi-tani/` + category `teknologi-tani` (3 posts) | Mix of landing copy and cultivation articles (IF16, budidaya, biopori) |
| Galeri | `/galeri/` | Media gallery page |
| Anggota | `/anggota/` | Member-facing documents + **external registration form** + card fee instructions |
| Koperasi AB2TI Pusat | `/koperasi-ab2ti-pusat/` | Cooperative explanation, RMP/PT AB2TI PSN, **separate** registration, investment form |
| Berita | category `/category/berita/` (~62 posts) | News, commentary, regional activity stories, press-like pieces |
| ICBB-AB2TI Award | `/icbb-ab2ti-award/` (+ archive pages) | Program explanation + yearly winner archives |
| Provibio | `/provibio/` | Product/knowledge page for a biofertilizer + leaflet downloads |
| Documents | Linked from Anggota / Koperasi / Provibio | Tata niaga benih, SK varietas IF-16, SK RMP, leaflets |
| External registration | Embedded on Anggota / Koperasi pages | Online forms; payment proof required |
| RMP journal | category `/category/rmp/` (~5 posts) | Construction/progress news for Rice Milling Plant Indramayu |
| Contact / secretariat | homepage + footer | Address at Kompleks ICBB, Bogor; phone/fax; `ab2ti.pusat@gmail.com` |

WordPress pages published (API): `tentang-ab2ti`, `teknologi-tani`, `galeri`, `anggota`, `koperasi-ab2ti-pusat`, `icbb-ab2ti-award`, `icbb-ab2ti-award-2022-2`, `provibio`, `home`, plus a WP `sample-page`.

### 6.3 Membership as practiced on the legacy site

AB2TI association registration (Anggota):

- External form
- Attachments: photo, KTP, transfer proof
- Fee: Rp 50.000 for card print/shipping
- Bank: Mandiri `1330000077677` **a.n. Koperasi AB2TI** (association fee paid to the cooperative account)

Koperasi Pusat registration:

- Separate form
- Explicit statement: **an AB2TI member is not automatically a cooperative member**
- Open to AB2TI members **and** the public
- Simpanan wajib Rp 1.000.000 converted to Category A shares (RMP PT. AB2TI PSN)
- Optional sukarela up to Rp 49.000.000
- Category B shares from Rp 51.000.000 — investor track, **different rules**, not the same as cooperative membership
- Separate “INVESTASI” form for RMP

Member-only knowledge on the Anggota page: tata niaga benih (AB2TI seed may only be multiplied/traded by members / koperasi) and SK varietas IF-16.

**No evidence** on the public site of: login for members, digital membership card, status lifecycle, regional org tree, or secretariat back-office.

### 6.4 Publishing mix observed in Berita

Recent posts mix:

- Organizational/regional activity (e.g. AB2TI Karanganyar)
- Opinion / press (Kompas, policy, food sovereignty)
- Media coverage / video embeds
- Not a clean split of “news” vs “press release” vs “education”

Teknologi Tani posts are few and instructional. Provibio is product documentation. Varieties (IF8/IF16) appear inside articles and documents, not as a catalog.

### 6.5 Programs / initiatives observed

- ICBB-AB2TI Award (2021–2024 archives, winners lists)
- Bank Benih / konservasi / pemuliaan (mission language, not a module)
- RMP / PT AB2TI PSN (cooperative-owned enterprise)
- Rakernas and similar events inside news
- Footer/kernel placeholders also mention Pelatihan and Advokasi

ICBB is **both** a physical address (Kompleks ICBB) and a partner/program brand. Do not treat “ICBB” as one object.

### 6.6 Community / regional contribution (legacy)

Regional stories appear as **editor-published WordPress posts**, not as a member submission channel. No public upload/moderation workflow was observed. The future requirement for regional submission is **new**, not a lift-and-shift of current WP.

---

## 7. Preliminary Domain Boundaries

These are discovery boundaries, not modules to build and not tables to create.

```
                    ┌─────────────────────────────────────┐
                    │         PLATFORM KERNEL             │
                    │  Auth, ACL, CMS pages, Tiptap,      │
                    │  Media, i18n, admin/public layouts  │
                    └─────────────────────────────────────┘
                                      │
          ┌───────────────┬───────────┼───────────┬────────────────┐
          ▼               ▼           ▼           ▼                ▼
   Public publishing   AB2TI       Koperasi     Agriculture/    Programs /
   (site + CMS +       membership  Pusat        knowledge       initiatives
    possible news)     (asosiasi)  (separate)   (editorial      (generic,
                                                and/or data)    not ICBB-only)
          │
          └──── Community / regional contribution (moderated intake)
```

### 7.1 Public / publishing

Likely a mix of **C** (organization pages) and **B/D** (news if it needs listing, authors, categories). Do not force all WordPress posts into `pages`.

### 7.2 AB2TI membership

Bounded domain **B + D + E**. Long-term: registration, verification, administration, profile, region/org relation, status lifecycle, documents, physical/online card. Today: external form. **Not** the same as a platform `User` and **not** the same as koperasi membership.

### 7.3 Koperasi Pusat AB2TI

Separate bounded domain **B + D + E** inside the same platform eventually. May include cooperative registration, administration, member frontend, admin backend, cooperative pages, services, later business modules (RMP/shares are coupled in the legacy story and may need their own sub-boundary).

Hard rule: association member ≠ cooperative member. Cooperative is also open to the public.

### 7.4 Agriculture / knowledge

Observed as:

- Editorial articles (Teknologi Tani posts)
- Product page (Provibio)
- Documents (SK, tata niaga, leaflets)
- Variety names inside prose (IF16, IF8, AB2TI super)

**G — do not decide yet** whether this is CMS content, structured data, or both.

### 7.5 Programs / initiatives

ICBB Award is one instance with yearly archives. RMP news is another. Do not architect the platform around Award alone. A generic Program/Event/Initiative concept is **G**.

### 7.6 Community / regional contribution

Future intake channel (information, activity reports, articles, photos) with moderation. Not present on the legacy site as a workflow. **B**, later, after publishing and identity decisions.

### 7.7 What must not be inferred from Presbi

Do not map jemaat → anggota, congregation → regional organization, sermons → teknologi tani, events → berita, church letters → documents. Those were removed church modules. AB2TI domains must be introduced on their own terms.

---

## 8. Legacy → AB2TI 2.0 Mapping

For each important legacy capability: type (A–G), disposition, and notes. Uncertain items stay `NEEDS DECISION`.

| Legacy capability | Type | Disposition | Notes |
|---|---|---|---|
| Laravel/Inertia/Vue kernel, auth, ACL, layouts, Base UI | **A** | **KEEP** | Constitution: preserved platform |
| CMS pages + Tiptap + custom extensions + block templates | **A** + **C** | **KEEP** | Retain for editable organization pages |
| File / media manager | **A** | **KEEP** | Distinct from a future document domain |
| Hero + welcome section | **A** / **C** | **ADAPT** | Fine for homepage; not a general CMS |
| Public i18n for UI chrome | **A** | **KEEP** | Does not translate CMS HTML |
| Tentang AB2TI (visi/misi) | **C** | **MIGRATE CONTENT** | Fits current `Page` model; URL slug is IA |
| Contact / secretariat | **C** | **ADAPT** | Footer currently hardcodes address; may stay CMS, config, or both |
| Home landing | **C** / **B** | **NEEDS DECISION** | Mix of hero, news teasers, award highlight, media |
| Berita / “AB2TI Terkini” | **G** (C vs B/D) | **NEEDS DECISION** | ~62 WP posts; mixed news/opinion/activity. CMS `Page` is a poor news archive |
| Press / media coverage | **G** | **NEEDS DECISION** | Currently indistinguishable from Berita |
| Teknologi Tani landing | **C** | **ADAPT** | Can be a CMS page; current kernel wrongly aliases `/sermons` |
| Teknologi Tani articles | **G** (C vs D) | **NEEDS DECISION** | Few instructional posts; may be editorial or knowledge records |
| Provibio product page | **G** (C vs D vs B) | **NEEDS DECISION** | Product + claims + PDFs; may be content, catalog, or koperasi service later |
| Galeri | **G** (A/C vs B) | **NEEDS DECISION** | Media library exists; no gallery album feature |
| Documents (SK, tata niaga, leaflets) | **G** (C vs D vs B) | **NEEDS DECISION** | File Manager can store files; membership-gated docs need a domain |
| Anggota page copy | **C** | **MIGRATE CONTENT** | Keep informational pages; do not treat the form as the system |
| AB2TI member registration (external form) | **E** | **REPLACE** (eventually) | Internal membership is expected; timing is product |
| Physical membership card + Rp 50.000 fee | **B** / **E** | **NEEDS DECISION** | Card lifecycle, payment, and shipping are not in the kernel |
| Member-only tata niaga / seed trading rules | **B** / **C** / **D** | **NEEDS DECISION** | Policy content vs enforced business rule |
| Koperasi Pusat pages | **C** + **B** | **ADAPT** | Pages can be CMS; membership cannot |
| Koperasi registration (external form) | **E** | **REPLACE** (eventually) | Separate from AB2TI registration |
| Category A shares / simpanan | **D** / **B** | **NEEDS DECISION** | Cooperative finance; not CMS |
| Category B investor shares | **D** / **B** | **NEEDS DECISION** | Explicitly not cooperative membership |
| RMP / PT AB2TI PSN | **G** | **NEEDS DECISION** | Cooperative enterprise vs news topic vs future module |
| ICBB-AB2TI Award | **B** / **C** / **F** | **NEEDS DECISION** | Do not make Award the program architecture; archives should be preserved |
| Award winner lists / yearly pages | **F** / **C** | **MIGRATE CONTENT** | Historical pages exist |
| ICBB as office location | **C** | **KEEP** (as contact data) | Do not merge with Award entity |
| Regional activity stories | **C** / **F** | **MIGRATE CONTENT** | Today they are posts, not a contribution workflow |
| Regional submission + moderation | **B** | **NEEDS DECISION** | Future requirement; not on legacy site |
| Varieties (IF16, IF8, etc.) | **G** (C vs D) | **NEEDS DECISION** | Named in articles/docs; no catalog on the site |
| WordPress itself | **E** | **REPLACE** (as runtime) | Content may migrate; WP is not the 2.0 platform |
| WP `project` CPT (empty) | **G** | **ARCHIVE** or ignore | No live content found |
| `/sermons` and `/events` aliases in this repo | leftover IA | **REPLACE** (when IA ADR exists) | Constitution forbids renaming until an IA ADR |
| Public Fortify registration | — | **KEEP** disabled | Until identity product decision |
| Seeded role `user` | — | **NEEDS DECISION** | Name collides with “member” conceptually |

**KEEP** the platform. **Do not** reproduce the WordPress menu as the 2.0 IA. **MIGRATE** organizational copy and historical posts/archives after the content model is chosen. **REPLACE** external forms only when membership domains are designed. Mark mixed publishing types as **NEEDS DECISION** rather than stuffing them into `pages`.

---

## 9. Potential Domain Conflicts / Coupling Risks

1. **Association vs cooperative membership.** Legacy already separates them, but both fees currently go to the same Koperasi AB2TI bank account. Payment plumbing can blur the domain boundary if modeled naively.

2. **`User` vs member vs koperasi anggota.** Using one authenticatable for all three will couple ACL, profiles, and public registration. The kernel `User` is staff-shaped.

3. **CMS pages vs application routes.** Slugs such as `anggota`, `berita`, `koperasi`, `galeri` will collide if first created as CMS pages and later needed as modules (same class of problem as `/sermons`).

4. **News vs CMS pages vs knowledge vs programs.** WordPress used posts + a few pages for almost everything. Stretching `pages.content` into a news system or seed catalog will freeze a bad model.

5. **ICBB naming.** Place (Kompleks ICBB), partner (Indonesian Center for Biodiversity and Biotechnology), and program (ICBB-AB2TI Award) are easy to collapse into one table/menu.

6. **RMP / PT AB2TI PSN vs Koperasi vs AB2TI.** Enterprise, cooperative, and association are nested in storytelling. A single “organization” model would be wrong.

7. **Media library vs gallery vs membership documents.** One Spatie `files` table cannot express albums, member-only PDFs, and page images without later policy. Keep File Manager as platform infrastructure until that is decided.

8. **Hero/Welcome vs CMS.** Two extra content models already exist for the homepage. Adding a third “sections” system without a decision will fragment authoring.

9. **Welcome Section image_url vs Spatie collections.** Inconsistent media handling; risk of parallel patterns in new features.

10. **Tiptap HTML copies of block templates.** Authors may think blocks stay linked; they do not. Fine for org pages; bad if used as a component framework.

11. **Frontend permission UI vs backend middleware.** New AB2TI admin UIs must not repeat the old UI-only auth mistake.

12. **Presbi residue as false shortcuts.** Renaming `/sermons` → Teknologi Tani without an ADR, or treating seeded `user` as anggota, would smuggle church IA into AB2TI.

13. **Footer “Program” list vs ICBB Award vs ServiceEvents cards.** Three different “program” notions already exist in the kernel UI.

14. **Community submissions into `Page.content`.** Untrusted HTML in the current CMS would be a security and editorial failure.

15. **Stale ACL rows** (`culture.*` / `artikel.*`) in developer DBs could be mistaken for real modules.

---

## 10. Questions That Must Be Answered Before Phase 1B

Phase 1B should not start from schema. It should answer product/IA questions. Suggested questions (not tickets):

### Identity and access

1. Who may log in to AB2TI 2.0 in the first public release: staff only, or also members / koperasi members / regional officers?
2. If members log in, is that the same `User` model, a related profile, or a different identity?
3. Is email verification required for any actor?
4. What happens to the seeded role name `user`?

### Public information architecture

5. What is the intended public IA (not a copy of WordPress or of `/about|/sermons|/events|/contact`)?
6. Which URLs must be preserved or redirected from `ab2ti.org`?
7. Is Tentang AB2TI a single page, a tree of CMS pages, or a mix?

### Publishing

8. Are Berita, press coverage, kegiatan, and opini one content type or several?
9. Who is allowed to publish, and is there a draft/review workflow in v1?
10. Should Teknologi Tani articles live with Berita, with CMS pages, or in a knowledge base?

### Membership and koperasi

11. Is internal AB2TI registration in scope for the first implementation phase, or only later, with external forms remaining?
12. Same question for Koperasi Pusat.
13. Must the two registrations stay on separate journeys, including payment?
14. Is a digital membership card in v1, or only physical, or neither?
15. Are regional/organizational relationships required before launch?

### Knowledge, programs, community

16. Are varieties, Provibio, and technical documents editorial pages, structured records, or downloads only?
17. Is there a generic Program/Event type, or are Award and RMP one-off content?
18. Is regional community submission in the first roadmap slice, or later?

### Content migration

19. Which WordPress pages/posts/media must be migrated, rewritten, or archived?
20. Who owns content freeze and editorial cleanup (typos, mixed categories, old Award pages)?

Until these are answered, implementations would be guessing.

---

## 11. Recommended Inputs for Phase 1B

Phase 1B should be **information architecture and content-model decisions**, still without database design unless product has answered §10.

Useful inputs to gather (people/content, not code):

1. **Stakeholder map:** secretariat, pengurus, koperasi admin, regional coordinators, public editors — who does what today.
2. **Target audiences** for the first release: public, members, koperasi members, staff.
3. **URL and menu intent:** a proposed IA that is allowed to differ from WordPress and from the current kernel nav.
4. **Content inventory spreadsheet** from WordPress (pages, posts by category, attachments, forms) with keep/rewrite/archive flags.
5. **Form field lists** from the current external AB2TI and koperasi registration forms (field names only; no schema yet).
6. **Document list** (tata niaga, SK, leaflets) with sensitivity: public vs member-only.
7. **Decision on Award/RMP:** content archives vs operational modules.
8. **Identity ADR draft:** staff-only vs member login, without picking table shapes first.
9. **Publishing ADR draft:** CMS pages only vs pages + a post/news type vs more.
10. **Koperasi boundary note:** confirm association ≠ koperasi ≠ RMP investor, in writing, before any shared “member” concept.

Phase 1B should **not**:

- design tables
- create implementation tickets that assume a schema
- rename `/sermons` or `/events` without an IA ADR
- enable public registration
- fold koperasi into membership
- treat this discovery document as a backlog of features to build

---

## Appendix A — Residual Presbi / church influence (report only)

Do not remove in this phase.

| Residue | Location | Influence if ignored |
|---|---|---|
| `/sermons`, `/events` routes + tests | `routes/web.php`, `CmsRoutingTest` | False IA; Constitution requires an ADR to change |
| Nav labels mapped onto those URLs | `useNavigation.js`, footer, lang files | “Farming Tech” / “News” hide church slugs |
| `ServiceEvents.vue` | Landing | Filename/prop still “events” |
| Jetstream Welcome cards | `Components/Welcome.vue` | Laravel marketing leftover on foundation dashboard |
| `culture.*` / `artikel.*` possibly still in some DBs | not in `config/acl.php` | Fake modules in Role UI if DB not cleaned |
| Church tables on old developer DBs | `LOCAL_DATABASE_RECOVERY.md` | Non-canonical schema; do not DROP casually |
| Dotted Inertia auth keys | `HandleInertiaRequests` | Technical debt, not domain |
| Unused GuestLayout / WebLayout | `resources/js/Layouts` | Dead layout paths |

No congregation/jemaat/letter/song modules remain in application code.

## Appendix B — Sources

- Repository inspection of models, routes, ACL, Vue, Tiptap, media, seeders (HEAD `38c952a`).
- `docs/project/ARCHITECTURE_BASELINE.md`, Constitution, ADRs 0001–0003, Foundation Backlog.
- Live site: `https://ab2ti.org` and WP REST: `/wp-json/wp/v2/types`, `pages`, `posts`, `categories`, `project`.

End of Phase 1A discovery.
