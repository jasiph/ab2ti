# AB2TI 2.0 — Phase 4A Homepage & Global Public Shell

**Status:** Implemented  
**Date:** 2026-08-22  
**Does not start:** Phase 4B (Publication / Teknologi Tani public redesign), Phase 4C (Lapak Anggota), Member Portal, Kartu Anggota, Koperasi operations, Community, Award workflow, payments, or a page builder.  
**Does not reopen:** Phase 3 Membership (RC closed). Homepage and navigation **link** to `/keanggotaan` only.

---

## 1. Inherited public-site state

Phase 2D already composed a public site from existing owners: `FrontendLayout`, `useNavigation().frontendMenus`, homepage Hero + Welcome CMS tables, published Publication/Knowledge previews (limit 3), and a Membership CTA to `/keanggotaan`.

Gaps that still made the site feel like stacked feature pages:

- Header promoted **Log in** instead of membership
- Mobile nav was a local overlay (no Escape, no focus trap, not `BaseModal`)
- No skip link
- Topbar/footer advertised `facebook.com/ab2ti` (not an authoritative Phase 1A social account)
- Footer used slate tokens, duplicated a “Program” column, and mixed English copyright
- Hero used a fixed 600px height, auto-slider without reduced-motion, and custom CTA chrome
- Latest content sections disappeared entirely when empty
- Homepage title was hardcoded `Selamat Datang`
- Welcome composition lived in a `routes/web.php` closure
- WhatsApp FAB bounced continuously (`green-500`, scroll-gated)

Church/Presbi public destinations (`/sermons`, `/events`, ServiceEvents) were already retired in Phase 2C/2D. No church copy remained on the homepage.

---

## 2. Removed / replaced legacy remnants

| Item | Action |
|---|---|
| `facebook.com/ab2ti` in topbar and footer | Removed (not an authoritative configured social account) |
| Landing-coupled `FooterSection.vue` | Replaced by `Layouts/Partials/PublicFooter.vue` |
| Custom mobile overlay (`bg-gray-900/80`) | Replaced by canonical `BaseModal` |
| Header Register / Login as primary public action | Login de-emphasized; Register remains gated by `canRegister` (disabled) |
| Bouncing WhatsApp FAB | Static brand-token FAB from `config/organization.php` contact |
| Duplicate footer “Program” column | Removed; primary nav is the single public IA |
| Unsplash / Laravel version / church leftovers | Already gone; tests still guard them |

Reusable generic infrastructure kept: `FrontendLayout`, `NavLink`, `ResponsiveNavLink` (`tone="public"`), `BaseButton`, `BaseModal`, `PublicationCard`, `KnowledgeArticleCard`, logos, `PublicMetadataResolver`, `heroes` / `welcome_sections`.

---

## 3. Public information architecture

Single named-route contract in `useNavigation().frontendMenus` (header, mobile nav, footer):

1. **Beranda** → `welcome` (`/`)
2. **Tentang AB2TI** → `about` (`/about`, CMS alias)
3. **Publikasi** → `publications.index` (`/publikasi`)
4. **Teknologi Tani** → `knowledge.index` (`/teknologi-tani`)
5. **Keanggotaan** → `membership.index` (`/keanggotaan`)
6. **Kontak** → `contact` (`/contact`, CMS alias)

Not in public navigation: admin, Member Portal, Kartu Anggota, Koperasi operations, Community, Award, Lapak Anggota, empty modules, or coming-soon placeholders.

Labels are i18n keys. No internal domain jargon in the public menu.

Lapak Anggota can be appended to `frontendMenus` later **only when a public route exists**. See §20.

---

## 4. Global layout

Canonical public shell remains `resources/js/Layouts/FrontendLayout.vue`.

It now provides:

- skip link → `#main-content`
- utility topbar (secretariat WhatsApp/email from config; theme; language)
- sticky header + primary nav
- mobile navigation (`BaseModal`)
- page slot (`<main id="main-content">`)
- canonical footer
- optional WhatsApp contact FAB (same phone as secretariat, not a second product)

No separate headers for Membership, Publishing, Knowledge, or CMS pages. Those pages already use `FrontendLayout` and inherit this shell.

`WebLayout.vue` remains unused leftover; not deleted in this phase.

No global announcements region (none was justified).

---

## 5. Header / navigation

- Identity: existing AB2TI mark + `ApplicationLogoWithText` (home link, focus ring)
- Desktop primary nav from `frontendMenus` (`lg` and up; six items plus CTA need the larger breakpoint)
- Active state via `NavLink` (`aria-current="page"`)
- Membership CTA: `BaseButton` → `route('membership.index')` (`/keanggotaan`), **not** a new apply flow
- Staff **Log in** / **Dashboard** is secondary text, hidden on the smallest screens (still in the mobile menu)
- Keyboard: focus-visible rings; `min-h-control` tap targets
- No mega-menu

---

## 6. Mobile navigation

`FrontendSidebar.vue` uses `BaseModal` (Escape, focus trap, body scroll lock, restore focus, closeable backdrop).

- Trigger: hamburger `lg:hidden`, `aria-expanded`, `aria-controls="frontend-sidebar"`
- Same `frontendMenus` as desktop (semantic parity)
- Same membership CTA
- Login/Dashboard at the bottom, not the primary action
- Resize to `lg` closes the dialog so desktop and mobile cannot diverge while open
- `ResponsiveNavLink` public tone uses `min-h-control`

No local modal system.

---

## 7. Homepage narrative

`Welcome.vue` answers:

1. **What is AB2TI?** — hero + welcome/intro (CMS with code fallback)
2. **Who is it for?** — orientation copy (farmers, members, public, agricultural stakeholders)
3. **What value?** — association + knowledge + publications + membership
4. **What can the visitor do now?** — four pathway cards to live routes
5. **What services exist?** — latest published Publications and Teknologi Tani, with empty states that still name the family

It is not a link directory. Unfinished products are not listed.

---

## 8. Hero

Still fed by the `heroes` table (active row, `hero-backgrounds` `large` conversion). No feature-local media pipeline.

- Fallback copy is i18n (identity + value), not English demo leftovers
- Default primary CTA → `/keanggotaan` unless the CMS hero supplies `button_url`
- Secondary CTA → Teknologi Tani (implemented public family)
- Gradient fallback when no images
- First image eager / `fetchpriority=high`; further slides lazy
- Auto-advance disabled when `prefers-reduced-motion`
- `min-h` instead of fixed 600px; content readable on small screens
- Decorative images use empty `alt` (heading carries the text)

---

## 9. Homepage sections

| Section | Source | Empty behavior |
|---|---|---|
| Hero | `heroes` | Gradient + fallback copy |
| Intro | `welcome_sections` (plain fields) | i18n fallback; default CTA `/about` |
| Pathways | Code-controlled | Always present; only live routes |
| Latest publications | `Publication::published()` limit 3, `PublicationViews::publicCard` | Section stays; “no publications yet” + index link |
| Latest Teknologi Tani | `KnowledgeArticle::published()` limit 3, `KnowledgeArticleViews::publicCard` | Same pattern |
| Membership invite | URL only `membership.index` | Always present; reuses Membership copy (not a second intake) |

No Lapak, Award, Koperasi, or Community sections.

Queries use `with('media')`. Previews reuse existing view-models; homepage does not duplicate Publication/Knowledge domain logic.

---

## 10. CMS boundary

**Structural / code-controlled**

- Layout, header, footer, breakpoints
- Navigation contract and membership CTA placement
- Section component types and order
- Pathway cards (which families exist)
- Empty-state presence
- Skip link and a11y wiring

**Editorial / CMS-editable**

- Hero row (`heroes` + media)
- Welcome intro fields (`welcome_sections`: title, copy, image URL, CTA)
- CMS pages `/about`, `/contact`, and other active `pages`
- Publication and Knowledge **bodies** remain on their own editorial pipeline (`EditorialContent`)

**Not in Phase 4A**

- Page builder / editable Vue trees
- Arbitrary HTML for homepage section chrome
- Bypassing sanitization

`welcome_sections` content is **plain text** interpolated in Vue (not Tiptap). Do not treat it as a second HTML editor. Rich HTML continues to go through `EditorialContent` on CMS Page / Publication / Knowledge.

---

## 11. Reusable components

Added only where homepage + shell share a real need:

| Component | Role |
|---|---|
| `PublicContainer` | Horizontal rhythm (`max-w-7xl` + responsive padding) |
| `PublicSection` | Vertical section spacing + optional muted tone |
| `SectionHeading` | `h2` + description + optional action slot |

Feature-specific (not a design system): `HeroSection`, `WelcomeSection`, `HomepagePathways`, `LatestPublications`, `LatestKnowledge`, `MembershipInvite`.

Existing cards and `Base*` primitives reused. No `EmptyState` abstraction (a paragraph was enough). No `PublicCard` (domain cards already exist).

---

## 12. Footer

`Layouts/Partials/PublicFooter.vue`:

- Organization short name + Phase 1A tagline (i18n)
- Same `frontendMenus`
- Secretariat address, phone, email, WhatsApp from `config/organization.php`
- Membership CTA → `/keanggotaan`
- Copyright: legal name + existing “All rights reserved.” translation

No invented social URLs, extra legal pages, or Koperasi claims.

---

## 13. SEO

Homepage `WelcomeController` still calls `PublicMetadataResolver::forWelcome()`. No parallel SEO stack.

- Canonical path `/`
- Title falls back to `config('organization.name')` when no welcome title
- Description from welcome copy or `public_delivery.default_description`
- Open Graph + Twitter/X from the same resolver
- OG image: hero/welcome featured URL, else platform default hierarchy

Other public routes keep their existing resolver methods. Shared `seo` prop in `HandleInertiaRequests` is unchanged except controllers still override.

---

## 14. Accessibility / HCI

- Skip link
- `h1` in the hero; section `h2`; pathway `h3`
- Focus-visible on nav, logo, CTAs, theme, language
- `min-h-control` on public nav, mobile links, language, theme
- Membership vs login: primary button vs muted text (not color-only)
- Hero images decorative; welcome image uses section title as alt
- Readable `max-w-2xl` / `max-w-3xl` on intro and CTA copy
- Reduced-motion on hero fade/slider
- Empty states explain the family instead of a blank page
- Mobile tap targets on hamburger, FAB (44px+), CTA

Audience: mixed digital literacy. Interaction is static; no decorative motion beyond the optional hero slider.

---

## 15. Responsive behavior

- Utility topbar: email from `sm`; WhatsApp icon always if configured
- Primary nav from `lg`; hamburger below `lg`
- Membership CTA remains visible in the header at all widths
- Hero stacks CTAs on small screens; `min-h` grows with breakpoint
- Pathway cards: 1 / 2 / 4 columns
- Content cards: 1 / 2 / 3 columns
- Footer: 1 then 3 columns
- Images `object-cover` with intentional aspect boxes
- Containers share `PublicContainer` padding

Publication/Knowledge **index/detail** visual redesign is Phase 4B; they inherit the shell only.

---

## 16. Performance considerations

- Homepage queries: 1 hero (+ eager media), 1 welcome, 1 publications (+ media), 1 knowledge (+ media)
- `published()` scopes only
- `limit(3)` previews
- Hero first image eager; others lazy
- Media `large` conversion reused
- WhatsApp FAB no longer scroll-listens or animates
- No new JS libraries
- No page-builder payload

---

## 17. Tests

Extended `tests/Feature/PublicExperience/PublicExperienceTest.php`:

- Homepage public, `Frontend/Landing/Welcome`, no auth
- Published-only previews (existing)
- Empty hero/welcome/previews do not 500
- Shared `organization` contact; no social Facebook key
- Membership CTA is `membership.index`, not apply
- `/keanggotaan` and `/keanggotaan/daftar` still the Membership pages
- Public routes 200 as guests; `/admin/*` redirects
- No sermons, Unsplash, `facebook.com/ab2ti`, Member Portal, or Lapak labels in the Inertia HTML payload
- No `lapak.*` named routes
- SEO fields present (`canonical_url`, `og_image`, `og_type`, `twitter_card`)

Existing Membership, Publishing, Knowledge, CMS sanitization, and `PublicMetadataHttpTest` remain.

Mobile `BaseModal` ESC/focus is covered by the existing modal primitive, not a new Vue unit harness.

---

## 18. Deliberately deferred public features

- Full Publication index/detail HCI (Phase 4B)
- Full Teknologi Tani index/detail HCI (Phase 4B)
- Lapak Anggota (Phase 4C)
- CMS About/Contact content authorship (editorial, not code)
- Member Portal, User↔Member linkage, Kartu Anggota, QR
- Koperasi public/operational UI
- Community, AB2TI Award submission
- Payments / checkout
- Global announcements
- Verified social-network accounts
- Page builder

---

## 19. Inputs for Phase 4B

Phase 4B should treat the public shell as frozen:

- Keep `FrontendLayout` / `frontendMenus` / `PublicContainer` rhythm
- Redesign Publication and Teknologi Tani **page bodies**, not a second header
- Homepage previews already consume `publicCard` view-models — prefer evolving those over homepage-local cards
- Empty-state language already exists (`No publications yet`, `No knowledge articles yet`)
- Do not put unpublished content on the homepage
- `/about` and `/contact` remain CMS aliases; missing pages are a content ops issue, not a 4A routing bug

---

## 20. Lapak Anggota integration readiness

**Not implemented in 4A.** Public `/lapak` family, nav item, and homepage pathway were added in Phase 4C.3.

Ready for a later append:

- Public IA is one array in `useNavigation().frontendMenus`
- Header/mobile/footer stay in sync automatically
- Homepage pathways are an explicit live-route list; Lapak can be added only when the route exists
- Membership remains `Member` (association). Product model “one Member → zero or one Lapak → many listings” does not require shell changes in 4A
- Do not add a dead or “coming soon” Lapak link until a real pattern exists

---

## Durable facts also recorded in ARCHITECTURE_BASELINE.md

`WelcomeController`, `config/organization.php`, shared Inertia `organization` prop, `PublicFooter`, `BaseModal` mobile nav, `PublicContainer` / `PublicSection` / `SectionHeading`.
