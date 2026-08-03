# Progress Log

> Running log of every commit and work session — newest at the top.
> Every commit gets an entry. Every session ends with a closing entry stating
> the next step.

---

## Template — copy this for each new entry

```
### YYYY-MM-DD — <short title>
- **Commit:** <hash or "pending">
- **Summary:** <what changed and why>
- **Files touched:** <list>
- **Features affected:** <FEATURES.md row #s, if any>
- **Next step:** <exact next action so the next session resumes instantly>
- **Open questions / notes:** <anything unresolved>
```

---

## Log

### 2026-08-03 — Applicant may name signatories while pending
- **Commit:** pending
- **Summary:** Restored (and tightened) the applicant's ability to edit the
  7.A/7.B/7.C-D signatory blocks. Earlier the admin/employee split made naming
  signatories admin-only; but naming who signs is not deciding the leave, and
  the applicant needs it so the admin can suggest changes before approving. New
  `LeaveWorkflow::canEditSignatories`: admin any time (not cancelled); owner
  only while PENDING. After a decision the blocks lock for the applicant and
  only an admin may adjust them. `setSignatory` now gates on this; Show.jsx
  drives the SignatoriesCard editors off a new `can.edit_signatories` prop.
  `canDecide` (7.A certification + 7.C/7.D decision) stays admin-only.
- **Files touched:** `app/Support/LeaveWorkflow.php`,
  `app/Http/Controllers/LeaveController.php`,
  `resources/js/Pages/Leave/Show.jsx`, tests `ApplicantEditsLeaveTest.php`.
- **Features affected:** 2, 4.
- **Verified:** `php artisan test` green (165). New tests: applicant names all
  three blocks while pending; applicant blocked after approval while admin
  still edits. UI edit affordances are the same boolean-driven SignatoriesCard
  already in use; the employee-mode toggle was uncooperative under the
  automated browser this run, so the applicant-pending case is covered by the
  HTTP-level tests rather than a click-through.
- **Next step:** Commit + push. No migration; routine pull deploy (the pending
  `/setup` is still owed from the earlier forfeiture migration + DP re-seed).

### 2026-08-03 — Nav slim-down, Personnel merge, new Director for Personnel
- **Commit:** pending
- **Summary:** (1) Merged the Users and Employees admin pages under a single
  "Personnel" nav item with Accounts/Records tabs (`PersonnelTabs.jsx`); both
  existing pages/toolsets kept. (2) Removed Holidays and Audit from the navbar
  (desktop + mobile) and surfaced them as quick-buttons in the admin dashboard
  header (Audit superadmin-only). (3) Changed the fixed 7.C/7.D Director for
  Personnel from LTC Adrian Lee G Mission to **MARICEL C TABACO** in
  `RosterSeeder` — recorded civilian (name only, no rank/PAF) since no rank was
  given; emp_no sentinel 'mission' and placeholder email kept so re-seeding
  renames the existing signatory in place (verified: one approver, no orphan).
- **Files touched:** `resources/js/Layouts/AuthenticatedLayout.jsx`,
  `resources/js/Components/PersonnelTabs.jsx` (new),
  `resources/js/Pages/Admin/{Users,Employees}/Index.jsx`,
  `resources/js/Pages/Dashboard.jsx`, `database/seeders/RosterSeeder.php`,
  `FEATURES.md`.
- **Features affected:** 22, 24, 25.
- **Verified:** `php artisan test` green (164); `npm run build` clean;
  browser-checked the slimmed nav, dashboard Holidays/Audit buttons, Personnel
  Accounts↔Records tabs, and the new DP signatory label (MARICEL C TABACO,
  one approver) after a local re-seed.
- **Next step:** Commit + push. **Deploy note:** no migration, but the DP
  rename only reaches prod via a re-seed (run `/setup`) OR by editing the
  'mission' record under Admin → Personnel → Records. Existing frozen
  `approver_sig` on past applications is unchanged by design; only new leaves
  pick up the new DP.
- **Open questions / notes:** DP recorded as civilian pending confirmation of
  her rank — flip `is_civilian`/`rank` in RosterSeeder if she is military.

### 2026-08-03 — Admin/employee split + year-end mandatory-leave forfeiture
- **Commit:** pending
- **Summary:** (1) Cleanly separated admin from employee: an admin can no
  longer file a leave — filing routes are guarded by a new `employee`
  middleware and `leave.index` redirects admins to the requests queue. To file,
  an admin switches to employee mode, which strips all admin access (nav,
  server gates) while on; a banner makes the current hat explicit. Certifying
  7.A, naming 7.B/C/D and deciding are now admin-only (`canDecide`); the
  applicant may sign only their own 6.D block (`canSignBlock`). (2) Added lazy
  year-end forfeiture of unused mandatory leave to `CreditLedger`: a closed
  year now carries forward 10 VL and 15 SL (forfeit = min(5, earned) − availed),
  netted into the year's row on the printed leave card. Also bootstrapped the
  progress-tracking workflow files (this file, FEATURES.md, ARCHITECTURE.md).
- **Files touched:** `app/Support/{ViewMode.php (new),LeaveWorkflow.php,CreditLedger.php}`,
  `app/Http/Middleware/{EnsureEmployeeMode.php (new),HandleInertiaRequests.php}`,
  `app/Http/Controllers/{LeaveController.php,DashboardController.php,LeaveLedgerController.php}`,
  `bootstrap/app.php`, `routes/web.php`,
  `database/migrations/2026_08_03_010000_add_event_key_to_leave_credit_entries.php` (new),
  `resources/js/Layouts/AuthenticatedLayout.jsx`,
  `resources/js/Pages/Leave/{Index.jsx,Requests.jsx,Show.jsx}`,
  `resources/views/leave/print.blade.php`, `CLAUDE.md`,
  tests: `ApplicantEditsLeaveTest.php`, `BlockSignatureTest.php`,
  `CreditLedgerTest.php`, `AdminEmployeeSeparationTest.php` (new).
- **Features affected:** 2, 4, 7, 10 (see FEATURES.md).
- **Verified:** `php artisan test` green (163 passed); `npm run build` clean;
  browser-checked the admin queue redirect, employee-mode banner/switch, and
  filing gate on the dev server (:8123).
- **Next step:** Commit (no Co-Authored-By trailer). Deploy needs the `/setup`
  round-trip because a migration was added (`add_event_key…`).
- **Open questions / notes:** Forfeiture is browser-verifiable only for an
  employee with a 2025 accrual start (seed employees are current-year), so it's
  covered by tests rather than a screenshot.
