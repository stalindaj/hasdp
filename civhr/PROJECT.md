# CivDir — Civilian's Directory

One system, one login, for the 15th Strike Wing's civilian personnel. Hosted on
the office cPanel, maintained through GitHub. CivDir is the umbrella for three
systems sharing one `users` database:

1. **Leave** — this build (complete)
2. **IPCR** — teammate's separate system; CivDir only tracks compliance
   (shared-login contract in [SHARED_LOGIN.md](SHARED_LOGIN.md))
3. **Learning & Development** — tracker for now; full system later

**Status: software finished and production-ready — 72 tests passing. The only
thing between here and live is the ~30-minute cPanel checklist in
[DEPLOY.md](DEPLOY.md).**

---

## What is built

### Leave workflow

- File CS Form No. 6 → admin approves → print-exact form for wet signature
  (matches the office's actual form: two seals, PAF header, signature blocks
  with rank/designation, wellness leave under "Others")
- Signatories: **7.A** Marie Cris Uri (auto) · **7.B** recommender (admin
  types name / rank / office, changeable anytime; prints rank left, PAF
  right, office on the title line) · **7.C/D** LTC Mission (auto)
- Applicant details auto-fill from the profile and are locked on the form
  (only the date is editable)
- **6.C is computed, not typed**: pick the inclusive dates and the working
  days are counted automatically — weekends and holidays excluded
  (maternity counts calendar days per R.A. 11210). The 2026 proclamation
  (incl. both Eids) is seeded; admins maintain later years under
  **Admin → Holidays**.

### Leave credits (PH/CSC rules)

- VL/SL auto-accrue **+1.25 monthly** (no cron needed), append-only auditable
  ledger
- Wellness 5/yr · SPL 3/yr, reset annually
- Approval deducts the right balance; insufficient credits auto-split into
  days without pay; disapproval refunds
- Mandatory/forced 5-day rule tracked, one-click year-end forfeiture
- Admin adjusts any balance by clicking it (reason kept in history)

### Dashboard & admin

- Admin: big boxes (IPCR / Leave / L&D), all-17-employee table (IPCR ✓
  toggles, days used, L&D hours), pending sidebar
- Employee card: balance table "as of month", credit history, IPCR
  (view-only), L&D log, their leaves
- "View as employee" switch (access actually changes)
- Full user management: create / edit / reset password / deactivate
- Login = Employee Number + password (initial password `password123`,
  changed by each employee on day one)

---

## Day one after go-live

- Admins set everyone's **true opening balances** from the 201 files
  (click a balance → adjust, reason recorded)
- Everyone changes `password123`
- Replace placeholder emails (Montejo, Candido, Garcia, Mission) with real
  ones
- Drop in a clean PAF seal PNG

## Parked (build later)

- Dark military-style UI redesign
- L&D as a full system (uploads, certificates)
- IPCR integration with the teammate's system (see
  [SHARED_LOGIN.md](SHARED_LOGIN.md))
- Email notifications via cPanel SMTP
