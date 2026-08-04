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

### 2026-08-04 — One hat on IPCR/IWOT, server-side status clamp, signature-upload error
- **Commit:** pending
- **Summary:** Production-readiness pass over the two new modules.
  (1) **IPCR and IWOT now obey the "one hat at a time" rule** that Leave
  already had. `IpcrAccess::isManager` (which `IwotAccess` delegates to) is now
  `hasManagerRole() && ! ViewMode::isEmployee()`, so an admin in employee mode
  is just a ratee: their own sheets only, no ratee picker, no approving, no
  deleting. The old comment claiming IPCR was deliberately outside the toggle
  is gone — it let an admin previewing as an employee see everyone's forms.
  (2) **Nobody approves their own** IPCR or IWOT, matching the leave rule.
  (3) **Status is clamped server-side.** The editor only offers draft/submitted
  to a non-manager, but the request accepted any status — a ratee could POST
  `status=approved`. Both controllers now force draft/submitted for the owner.
  (4) `canEdit` on an IPCR also covers a **returned** form (it was draft-only,
  so a returned form could not be corrected).
  (5) **Signature-upload errors are readable again.** A failed upload can put a
  PHP warning *before* the JSON body, which broke `JSON.parse` and left the
  user with a useless "Upload failed (422)". Both the shared picker and the
  leave print page now parse from the first brace and name the temp-dir case.
- **Files touched:** `app/Support/{IpcrAccess,IwotAccess}.php`,
  `app/Http/Controllers/{IpcrController,IwotController}.php`,
  `resources/views/partials/signature-picker.blade.php`,
  `resources/views/leave/print.blade.php`,
  `tests/Feature/{IpcrTest,IwotTest}.php`.
- **Features affected:** 2, 26, 27, 28.
- **Verified:** `php artisan test` green (191, +6 new); `npm run build` clean.
  Reproduced the 422 in the browser and traced it to the dev machine's PHP:
  `upload_tmp_dir` is empty in the Herd php.ini and `php artisan serve`'s child
  process has no usable TMP, so **every** upload fails locally (leave included).
  Proved it by serving with `php -d upload_tmp_dir=…`, where the same upload
  returns 200. **Not a production problem** — cPanel sets `upload_tmp_dir`.
- **Next step:** Commit + push; deploy still owes the `/setup` round-trip for
  the two IPCR/IWOT migrations.
- **Open questions / notes:** Local fix for the user's machine is one line in
  `C:\Users\User\.config\herd\bin\php84\php.ini`:
  `upload_tmp_dir = "C:\Users\User\AppData\Local\Temp"`, then restart the dev
  server. Left for the user to apply — it is their PHP install, not the repo.

### 2026-08-04 — IWOT added; IPCR prints the official Form E; e-signatures on both
- **Commit:** pending
- **Summary:** Worked from the office templates the user supplied
  (`IWOT-TEMPLATE (1).xlsx`, `IPCR-TEMPLATE.xlsx`) plus a photo of a real
  signed Form E.
  (1) **IWOT is a feature now** — the Individual Work Output Target sheet, the
  targets half of the cycle the IPCR later rates against. Same on-screen matrix
  the personnel already know (the shared `Components/Ipcr/Matrix`, with the
  achieved-standard picker switched off since nothing is achieved yet), its own
  tables, access rules, draft→submit→approve workflow and nav item. Printing
  reproduces the office template exactly: three header lines, the eleven-column
  matrix on a landscape page, then PREPARED BY / APPROVED BY.
  (2) **IPCR printing is the official Form E**, not the generic sheet: orange
  Reviewed/Approved and rater's bands, the blue Output · Success Indicator ·
  Actual Accomplishment · Rating (Q/Qn/T3/A4) · Remarks grid, numbered outputs,
  the summary ladder and "From Rater/s and Recommendations for Development
  Purpose". The matrix no longer prints with it — that sheet IS the IWOT.
  **Form E is LANDSCAPE, Arial, `table-layout:fixed`** — the first cut was
  portrait Times and everything wrapped. The commitment paragraph runs full
  width with the ratee signing beneath it on the right, and each summary figure
  spans the four rating columns rather than sitting in one narrow cell.
  **Palette and wording come from the office file, not the 461st FWFMS copy**
  the user first showed: the band fills were read out of
  `IPCR-TEMPLATE.xlsx`'s `styles.xml` (theme 5 tint .6 = `#f8cbad` peach bands,
  theme 8 tint .6 = `#bdd7ee` grid head), and the labels are the office's —
  "Reviewed by" (no colon), `Ql1/Qn2/T3/A4`, "Actual Accomplishments",
  "Comments and Recommendations for Development Purposes", "Overall Equivalent
  **Adjectival** Rating", "(FORM E)" on its own line above a centred title,
  legend outside the table. Signatory cells are the template's three stacked
  rows (ink band → name → designation) with the Date merged down all three.
  (3) **E-signatures on both**, the way CS Form No. 6 already works: one image
  per named block, uploaded straight from the print page (`FormSignatures` +
  the shared `partials/signature-picker`). Six blocks on Form E, two on the
  IWOT. The ratee/employee signs their own blocks only; supervisor blocks
  belong to a manager. An account e-signature stands in until an image is
  uploaded onto the form — the NCOIC/CO usually have no account here.
- **Files touched:** `app/Http/Controllers/IwotController.php` (new),
  `app/Models/Iwot{Form,FormGroup,FormRow}.php` (new),
  `app/Support/{FormSignatures,IwotAccess}.php` (new),
  `app/Http/Controllers/IpcrController.php`, `app/Models/IpcrForm.php`,
  `app/Support/IpcrAccess.php`, `routes/web.php`,
  `resources/js/Pages/Iwot/{Index,Form,Show}.jsx` (new),
  `resources/js/Components/Ipcr/Matrix.jsx`,
  `resources/js/Layouts/AuthenticatedLayout.jsx`,
  `resources/views/{ipcr,iwot}/print.blade.php`,
  `resources/views/partials/signature-picker.blade.php` (new),
  `database/migrations/2026_08_04_040000_create_iwot_tables_and_form_signatures.php` (new),
  `tests/Feature/{IwotTest.php (new),IpcrTest.php}`.
- **Features affected:** 26, 27, 28.
- **Verified:** `php artisan test` green (185); `npm run build` clean.
  Browser-checked on :8123 — seeded the office IWOT example and confirmed the
  printed sheet matches the xlsx cell for cell; attached a signature and
  watched it render over the name with Replace/✕ controls; dressed IPCR #3 with
  the two supervisors and all six signatures and confirmed the Form E layout
  against the photo (bands, grid, rating columns, rater's block).
- **Next step:** Commit + push. **Deploy needs the `/setup` round-trip** — two
  migrations are pending (`…ipcr_text_dates_and_selected_band`,
  `…create_iwot_tables_and_form_signatories`).
- **Open questions / notes:** (1) The IWOT and the IPCR are still separate
  records. The obvious next step is "start an IPCR from an approved IWOT" so the
  targets and standards carry over instead of being retyped — not built, not
  asked for yet. (2) Seeded Baguio's real IWOT + IPCR (Officer Career and Pilot
  Profile System Development) as **drafts** at the user's instruction — do not
  approve them. (3) Found while rating that sheet: the second output's
  **Timeliness** standards mix "3 sessions per week" with day-of-month values
  ("23rd Day of the Succeeding Month"…), so a numeric comparison reads 2 < 23
  and scores Poor. The engine is behaving correctly on inconsistent standards —
  that row needs rewriting in units of sessions on the office template itself.

### 2026-08-04 — IPCR editor rebuilt as the real matrix + FORM E sheets
- **Commit:** pending
- **Summary:** The native IPCR port kept the data model but replaced the
  teammate app's WYSIWYG sheet with a generic card-and-label editor, and three
  things in the integration were plainly wrong. Fixed all of them:
  (1) **The sheet is back.** `Ipcr/Form` now renders the actual
  **IPCR Form Matrix** (Major Final Output × Timeliness × the three measures ×
  five Performance Standards) followed by **FORM E**, laid out like the printed
  form. Clicking a Performance Standards cell marks it green with a check,
  copies its % into the matching Qlty/Time/Qty field in Form E and re-rates the
  measure; A4, average point score, intervening total, overall point score,
  numerical and adjectival ratings all recompute live.
  (2) **Form E dates are text, not dates.** They are filled from the rating
  period ("January - June 2026" → "January" on Reviewed/Approved, "June 2026"
  on Discussed/Assessed/Final Rating); as `date` columns they failed validation
  and were dropped on save. Migrated the five columns to strings.
  (3) **One place to name signatories.** The duplicate "typed signatories" card
  is gone — the Form E cells are the signatories, and `reviewer_sig` /
  `approver_sig` are frozen from `fe_reviewed_by` / `fe_approved_by`.
  Also: "Add: Intervening Activity" is a real add/remove list again (its total
  feeds the overall point score, capped at 5); the picked standard band is
  persisted (`ipcr_form_rows.selected_band`) so the check survives a reload;
  ratings are re-derived server-side from the % against the stored standards so
  a score never depends on the browser; `Ipcr/Show` now displays the two
  generated sheets read-only; and **printing generates both sheets** — the
  matrix on a landscape page, Form E on a portrait one.
- **Files touched:** `resources/js/Components/Ipcr/{rating.js,Matrix.jsx,FormE.jsx}` (new),
  `resources/js/Pages/Ipcr/{Form.jsx,Show.jsx}`,
  `resources/views/ipcr/print.blade.php`,
  `app/Http/Controllers/IpcrController.php`,
  `app/Models/{IpcrForm.php,IpcrFormGroup.php}`,
  `database/migrations/2026_08_04_030000_ipcr_text_dates_and_selected_band.php` (new),
  `tests/Feature/IpcrTest.php`.
- **Features affected:** IPCR.
- **Verified:** `php artisan test` green (177); `npm run build` clean.
  Browser-checked on :8123 — clicked the "Very Satisfactory" cell of Quality
  and watched 90 land in Qlty %, Ql1 become 4, and the footer read 4.00 / Very
  Satisfactory; saved, reopened (green check, %, rating and auto-filled dates
  all round-tripped) and rendered `/ipcr/{id}/print` with both sheets.
- **Next step:** Commit + push. **Deploy needs the `/setup` round-trip** — a
  migration was added.
- **Open questions / notes:** Superseded in part by the entry above — IPCR
  printing is now the official Form E alone, and the matrix moved to the new
  IWOT feature. (There is no separate "IWOT app" on disk; a search of the whole
  Desktop found none. It was built from the office xlsx template instead.)

### 2026-08-03 — 7.A is computed from the ledger, not retyped
- **Commit:** pending
- **Summary:** 7.A only ever showed figures once an admin typed/drafted them:
  the printed grid was blank and the applicant's card showed raw balances plus
  "HR certifies these figures…". The arithmetic was already implemented for the
  admin prefill, just not shared. Extracted it into
  `App\Support\LeaveCertification` (`computed()` from the ledger, `merged()`
  = admin's saved figures win, computed elsewhere) and used it in three places:
  the print blade's 7.A grid + "As of", the applicant's `CreditSummaryCard`,
  and `LeaveController::creditPrefill` (which now delegates). `creditPrefill`
  is sent to the owner as well as the admin. The side the leave draws on shows
  the days deducted; the other side prints "—" (null "less" ≠ zero).
  **Deliberately unchanged:** the strict `$certified` flag still gates the HR
  officer's name/signature on the printout — the numbers are computed facts,
  but certifying stays a human act, so an uncertified form shows figures over a
  blank signature line.
- **Files touched:** `app/Support/LeaveCertification.php` (new),
  `app/Http/Controllers/LeaveController.php`,
  `resources/views/leave/print.blade.php`,
  `resources/js/Pages/Leave/Show.jsx`, tests `CivilianAndDraftTest.php`.
- **Features affected:** 4, 6, 9.
- **Verified:** `php artisan test` green (167; +2 new — 7.A computed before
  certification, and a certified 7.A still winning). Browser-checked as the
  non-admin applicant on a pending 2-day vacation leave: card reads
  `VL 2.5 / 2 / 0.5` and `SL 2.5 / — / 2.5`; the printed grid reads Total
  Earned 2.5/2.5, Less 2/—, Balance 0.5/2.5, "As of 3 August 2026", with the
  HR name + signature still blank.
- **Next step:** Commit + push. No migration; routine pull.

### 2026-08-03 — Applicant can view/print their own CS Form 6 at any stage
- **Commit:** pending
- **Summary:** `canPrint` only allowed APPROVED-or-admin, so an applicant could
  not read back the form they were filing — and the earlier view-mode-aware
  `isAdmin` made it worse (an admin in employee mode also lost preview of their
  own pending leave). Now anyone who can see the application can print it at
  any stage: `isAdmin || owner`, cancelled still blocked. It is the same sheet
  the admin previews; an undecided form simply prints with 7.C/7.D blank. The
  My Leave list also offers the form on every non-cancelled row ("View form"
  while pending, "Print form" once approved) instead of only after approval.
- **Files touched:** `app/Support/LeaveWorkflow.php`,
  `resources/js/Pages/Leave/Index.jsx`, tests `LeaveTest.php`.
- **Features affected:** 6.
- **Verified:** `php artisan test` green (165). Browser-checked end to end as a
  real non-admin (logged in as employee 5797 with a pending leave): My Leave
  row shows "View form", the print route renders the CS Form 6, the print
  button is there, and the only signing control is `pickSig('applicant')` —
  6.D only, 7.x still admin-only. Detail page shows the print button and the
  signatory "change" editors while pending.
- **Next step:** Commit + push. No migration; routine pull.

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
