# Cooperative admin — PSN share operations

Staff procedure for day-to-day PSN share administration at `/admin/koperasi`. This is not Member self-service, not online share purchase, and not a general accounting ledger. No real personal data is included here.

K3 delivered the workspace. K3H (2026-09-11) hardened stale edits, concurrency, idempotency, audit, unresolved-PSN gating, inactive-membership rules, and mobile confirmation.

## Ordinary vs financial work

Ordinary Cooperative administration (finding a member, reading status, reading PSN history) is read-only.

Financial mutation is deliberate:

1. Open Member detail (read-only).
2. Understand current resolved PSN (or unresolved reconciliation).
3. Choose **Kelola Saham PSN**.
4. Enter the **desired total** units, effective date, reason, and note.
5. Review before / change / after.
6. Confirm with **KONFIRMASI PERUBAHAN SAHAM**.
7. A new ledger row is written. Previous rows stay.

Do not expect inline share fields on the Member list or detail page.

## Stale edit handling

If two officers open the same Member, and one confirms after the other has already changed the shares, the later confirmation is **rejected**. The first officer must review the latest count. The system will not apply a stale 5→6 confirmation on top of a current 7.

## Concurrency model

The server locks the membership and PSN participation row, recalculates `SUM(units)` from the ledger, then inserts one adjustment. Current units, delta, and value submitted by the browser are not trusted.

## Idempotent financial confirmation

Each confirmation has a one-time request token. Retrying the same confirmed request (slow network, double tap, refresh after a lost response) must not create a second `ParticipationEntry`. A new visit to Kelola Saham PSN issues a new token for a new intended change.

## Correction workflow

If an earlier administrative adjustment was wrong:

- Do not delete or edit it.
- Enter the correct **desired total**.
- Use reason **Koreksi administratif** / Administrative correction.
- Optionally point at the previous entry.
- Confirm. The compensating row is new history (example: +3 then −3).

`reverses_entry_id` is reserved for legacy-import reversal, not this workflow.

## Inactive-member rule

Ordinary PSN adjustment is unavailable while Cooperative membership is inactive. Do not reactivate membership as a side effect of a share edit. Reactivate membership first, explicitly, if ordinary adjustment is required.

## Unresolved-PSN rule

If the Member is among unresolved legacy PSN rows, **Kelola Saham PSN** is blocked. Complete **Selesaikan Rekonsiliasi** first. Unresolved PSN is not the same as confirmed zero participation.

## Mobile safe-edit workflow

On a phone: Member detail → Kelola Saham PSN → type the desired integer total → review → confirm. There is no swipe mutation and no tiny adjacent Save/Delete on a financial table. Leaving the page discards an unconfirmed draft.

## Ledger immutability

Share history is append-only for ordinary administration. Openings, import rows, and previous adjustments are not rewritten.

## Backup philosophy

Do not dump SQL for every share change. Protection is:

```text
immutable ledger
+ audit provenance
+ compensating corrections
+ routine database backups
```

Take a verified database backup before exceptional operations such as legacy import commit/replace, not before each administrative +1/−1 share adjustment.

## Effective dates

Effective date and created-at are different. Operator timezone is Asia/Jakarta. Ordinary administrative adjustments may not be future-dated.
