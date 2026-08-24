# AB2TI 2.0 — Member account provisioning & site access activation

**Status:** Implemented  
**Date:** 2026-08-23  
**Does not start:** Koperasi, Community, or a Lapak reopen.  
**Does not introduce:** public `/register`, email/phone auto-link, plaintext passwords, stored invitation secrets, casual unlink of an established User ↔ Member pair, automatic Lapak creation, a new Member lifecycle state, or a mail/notification subsystem.

Phase 4C Lapak Anggota remains closed. This is a platform identity patch on top of Phase 4C.2 linking. Lapak workspace details stay in `docs/implementation/phase-4c2-member-account-linking-lapak-workspace.md`.

---

## 1. Problem

Verified `Member` records often exist before the person has ever had a website `User`. Staff previously had to create a User by a separate process, then issue a `member-account-link` invitation. The operational workflow is now:

```text
Member
  ↓ Berikan Akses Situs (staff confirms intended login email)
  ↓ secure invitation (shown once)
  ↓ member activates a new account, or logs in to an existing one and confirms
  ↓ User ↔ Member
  ↓ member services (currently Lapak Saya; not created by this patch)
```

---

## 2. Identity boundary

```text
User  = authentication / website account
Member = verified AB2TI membership identity
```

`User != Member`. A website account does not prove membership. A Member record is not a login. The explicit 1:1 link `members.user_id` (+ `members.linked_at`) is the only bridge. Email or phone equality is never authorization and never creates that link.

---

## 3. Final provisioning model

Reuse Secure Capability purpose **`member-account-link`** (target `member`, TTL 168 hours). A second purpose was not added: both “link existing User” and “activate then link” consume the same Member-bound invitation. Invitation context (not extra authority) is stored on the grant as JSON `payload`.

Existing-account invitations are bound to the intended **immutable User identity**, not merely to an email address or possession of the invitation:

```text
existing-account:
  { "intended_email": "...", "existing_account": true, "intended_user_id": 123 }

new-account:
  { "intended_email": "...", "existing_account": false }
```

`intended_email` is display/login context. Confirm authorization is `authenticated_user.id === intended_user_id`. Email comparison is never the root primitive. `intended_user_id` is not shown in member/public UI.

Secrets remain HMAC-SHA256 digest-only. Plaintext URL is flashed once (`member_account_link_url`) for out-of-band delivery. Reissue revokes live siblings. Success consumes the grant. Replay, expiry, revocation, and wrong purpose return the project’s safe 404.

---

## 4. Existing User flow

```text
staff confirms intended email that already belongs to a User
  ↓ issue invitation (no silent link)
  ↓ guest opens /akun/tautkan/{secret}  → login guidance
  ↓ url.intended = invitation URL (non-admin, so LoginResponse honors it)
  ↓ login
  ↓ Confirm page (legal_name + membership_number only)
  ↓ POST confirm → LinkUserToMember
  ↓ consume grant; redirect PostAuthenticationHome (/akun/lapak for linked Members)
```

Staff accounts (`isPlatformStaff()`) cannot be invited or used to claim. A User already linked to another Member cannot be invited or claim again.

Issue time resolves `User` by the confirmed login email once. If a User exists, the grant stores that User’s id as `intended_user_id`. Confirm (`LinkUserToMember`) requires `existing_account === true` and `user.id === intended_user_id`. A wrong User receives HTTP 404; the grant is **not** consumed, so the intended User can still confirm. Email may change after issue: the invitation stays bound to that User id. Whoever later owns the old email cannot claim.

Existing-account invitations that lack a valid `intended_user_id` (legacy/malformed) fail closed and must be reissued. New-account invitations cannot be confirmed by an already-existing User; they must use activation. Existing-account invitations cannot be used to create a new User.

---

## 5. New User activation flow

```text
staff confirms intended login email that does not belong to a User
  ↓ issue invitation
  ↓ guest opens valid URL → account setup (name, intended email, password)
  ↓ POST /akun/tautkan/{secret}/aktivasi
  ↓ transaction: lock Member, validate grant, email free, create User, link, consume
  ↓ Auth::login + session regenerate
  ↓ PostAuthenticationHome → /akun/lapak
```

Public `/register` stays closed. The activation form is invitation-driven and is not in public navigation.

---

## 6. Account identity / email

Login identifier is Fortify `email`. Staff must confirm **Email akun situs**. Member contact email may be suggested; it is not copied silently into `users.email`, and provisioning does not change Member contact fields.

If that email already belongs to a User, staff sees that an account exists and still issues a **linking** invitation — never auto-link.

---

## 7. Password / authentication

Staff never choose, generate, or store a Member password. The Member sets `Password::default()` + confirmed on activation. After that the account is a normal User (Fortify login, password reset, optional 2FA). Email verification is **not** enabled application-wide and was not added only for Members.

---

## 8. Atomicity

`ActivateMemberSiteAccess` runs in a DB transaction: lock Member, refuse if already linked, refuse email mismatch / occupied email, create User, `LinkUserToMember` (locks Member + User, sets `user_id` / `linked_at`, consumes grant). Failure rolls back so an activation attempt cannot leave an orphan User intended to be linked in the same request.

Concurrency: one activation succeeds; stale/replayed/revoked attempts fail safely. `members.user_id` remains UNIQUE.

---

## 9. Admin UX (Akses Situs)

Member detail panel states (separate from membership status):

| State | Meaning | Primary action |
|---|---|---|
| Belum aktif | No User linked | Berikan Akses Situs |
| Undangan aktivasi tersedia | Live unused invitation | Salin (once) / Buat ulang / Cabut |
| Aktif | Linked (`linked_at` + User identity) | No further invitation |
| Kedaluwarsa / dicabut | Last invitation not live | Berikan Akses Situs again |

Staff never see token digest, purpose string, or raw payload. Unlinking an established account is **out of scope** (revoking an unused invitation is allowed).

---

## 10. Authorization

Admin issue/revoke requires admin shell (`platform-staff`) plus `membership-members.update` (`MemberPolicy::update`). Editors without that permission cannot provision. Ordinary Users and linked Members cannot use `/admin` provisioning endpoints.

---

## 11. Post-activation / Lapak

Redirect uses `PostAuthenticationHome` (linked Member → `/akun/lapak`). Activation does **not** create a Lapak. An existing staff-created Lapak on that Member becomes owner-accessible immediately. A Member with no Lapak sees the existing empty “Buat Lapak Saya” state.

---

## 12. Intentional limitations

- No in-app email/WhatsApp delivery of invitations.
- No general “Putuskan Akun” / unlink recovery.
- No password-recovery subsystem beyond Fortify.
- Grant `payload` is invitation context only; it is not extra authorization. `intended_user_id` is interpreted only by the member-account-link confirm/issue flow.
- Nested `DB::transaction` in `LinkUserToMember` runs as a savepoint inside activation; rollback still covers User create + link.
- Tests for immutable User binding live in `MemberSiteAccessProvisioningTest` (wrong User, email mutation/reuse, legacy payload, branch isolation).
