# 0001 — Retain cleaned Laravel/Vue kernel; Constitution is governance

Date: 2026-08-19
Status: Accepted

## Context

AB2TI 2.0 is being built on a repository imported from a previous Laravel/Inertia/Vue application. Phase 0A removed Presbi/church business modules and left a platform kernel (auth, users/roles infrastructure, CMS pages, media, i18n, layouts).

Without written governance, future Cursor/AI-assisted work can reintroduce church domain, drop developer databases, skip server-side authorization, or invent parallel architectures.

## Decision

- AB2TI **retains** the cleaned Laravel 12 + Inertia 2 + Vue 3 + Tailwind 3 platform kernel as the implementation base.
- `docs/project/CONSTITUTION.md` is the **canonical** engineering policy.
- Cursor Project Rules in `.cursor/rules/` **operationalize** the Constitution; they do not override it.
- AB2TI **business-domain** architecture (news, programs, membership, etc.) will be introduced **incrementally**, with ADRs when material.
- Legacy Presbi/church-domain behavior is **not** part of AB2TI and MUST NOT be resurrected.
- Residual issues listed in `docs/project/FOUNDATION_BACKLOG.md` remain unresolved until a dedicated hardening or product-decision task.

## Consequences

- Feature work starts from existing controllers, Inertia pages, and Spatie packages rather than a greenfield rewrite.
- Database safety rules forbid `migrate:fresh` and silent DROP of leftover church tables on developer databases.
- New admin capabilities must plan server-side authorization even though the current kernel is UI-gated.
- Public URL aliases `/sermons` and `/events` stay until an IA ADR says otherwise.
