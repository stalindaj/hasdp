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
