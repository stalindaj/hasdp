# Features

> Authoritative list of every feature in this system. The context-window
> insurance file — if a session loses context, this restores the full picture.
> Update it whenever a feature is added, changed, or removed.
>
> Status legend: 🟢 done · 🟡 in progress · 🔴 planned · ⚫ deprecated

| # | Feature | Purpose | Status | Key files / paths | Depends on |
|---|---------|---------|--------|-------------------|------------|
| 1 | Auth & roles | One login; roles employee/admin/superadmin/hr_officer/approver | 🟢 done | `app/Models/{User,Role}.php`, `routes/auth.php`, `RoleSeeder` | — |
| 2 | Admin/employee separation | Admin cannot file; switches to employee mode to file; one hat at a time | 🟢 done | `app/Support/{ViewMode,LeaveWorkflow}.php`, `EnsureEmployeeMode`, `HandleInertiaRequests`, `AuthenticatedLayout.jsx` | 1 |
| 3 | File CS Form No. 6 | Employee files leave (boxes 1–6), signs 6.D | 🟢 done | `LeaveController@create/store`, `Leave/Create.jsx` | 1,2 |
| 4 | Process / decide leave | Admin certifies 7.A, names 7.B/C/D, approves/disapproves 7.C/7.D | 🟢 done | `LeaveController@decide/saveDraft/setSignatory`, `Leave/Show.jsx` | 2,3 |
| 5 | Applicant edits boxes 1–6 | Applicant corrects their half any time until cancelled; admin may too | 🟢 done | `LeaveController@update`, `canEdit` | 3 |
| 6 | Print-exact CS Form 6 | Coordinate-positioned printable form, e-sign or pen | 🟢 done | `resources/views/leave/print.blade.php`, `_sigblock.blade.php` | 3,4 |
| 7 | Per-block signature images | Ink image per block; applicant only 6.D, admin any | 🟢 done | `LeaveController@storeBlockSignature/…`, `LeaveWorkflow::canSignBlock` | 4,6 |
| 8 | Signed-form upload | Applicant uploads wet-signed scan after approval | 🟢 done | `LeaveController@storeSignedForm/signedForm` | 4 |
| 9 | Credit ledger (VL/SL) | Append-only; +1.25/mo lazy accrual, approvals deduct | 🟢 done | `app/Support/CreditLedger.php`, `LeaveCreditEntry` | 4 |
| 10 | Year-end mandatory-leave forfeiture | Unused of 5 forced VL days forfeited at year-end (closed year → +10 VL/+15 SL) | 🟢 done | `CreditLedger::ensureForfeitures`, migration `…add_event_key…` | 9 |
| 11 | Wellness (5/yr) & SPL (3/yr) | Annual entitlements that reset each year | 🟢 done | `CreditLedger::balances` | 9 |
| 12 | Forced-leave tracking | 5 mandatory VL days/yr; any VL availment counts | 🟢 done | `CreditLedger::forcedLeaveStatus` | 9 |
| 13 | Admin balance adjustments | Opening balances / corrections as ledger rows | 🟢 done | `DashboardController@adjustCredit` | 9 |
| 14 | Admin-recorded leaves | Key in paper/pre-go-live leaves as approved | 🟢 done | `DashboardController@recordLeave` | 9 |
| 15 | Printable leave card | CSC-style VL/SL card straight from the ledger | 🟢 done | `LeaveLedgerController`, `leave/ledger.blade.php` | 9 |
| 16 | Admin dashboard | Roster IPCR/leave/L&D status at a glance | 🟢 done | `DashboardController@adminDashboard`, `Dashboard.jsx` | 1 |
| 17 | Employee dashboard | Own IPCR/balances/L&D | 🟢 done | `DashboardController@employeeDashboard` | 1 |
| 18 | Employee card (admin) | One employee's full card + ledger history | 🟢 done | `DashboardController@showEmployee`, `Dashboard/Employee.jsx` | 9,16 |
| 19 | IPCR compliance toggles | Per-semester done flags | 🟢 done | `DashboardController@toggleIpcr`, `IpcrRecord` | 16 |
| 20 | Learning & Development | Submit training w/ proof; admin approves; hours toward target | 🟢 done | `LdController`, `LdEntry` | 1 |
| 21 | E-signatures (account) | Upload your own (or anyone's, if admin) e-signature | 🟢 done | `SignatureController`, `users.signature_path` | 1 |
| 22 | Admin: Personnel (Accounts + Records) | Manage logins/roles/signatures and plantilla HR records under one nav item with two tabs | 🟢 done | `Admin/{UserController,EmployeeController}`, `Components/PersonnelTabs.jsx` | 1 |
| 23 | Admin: balances grid | All-employee balance grid, editable | 🟢 done | `Admin/BalanceController` | 9 |
| 24 | Admin: holidays | Non-working days for 6.C; reached via a dashboard quick-button | 🟢 done | `Admin/HolidayController`, `Holiday`, `Dashboard.jsx` | — |
| 25 | Audit trail (superadmin) | Every act logged + export; reached via a dashboard quick-button | 🟢 done | `Admin/AuditController`, `LeaveApplicationAction`, `Dashboard.jsx` | 1 |
| 26 | IPCR form (Matrix + FORM E) | Fill the performance-standards matrix and Form E on-screen, auto-rate from the % achieved, then print the official Form E | 🟢 done | `IpcrController`, `IpcrForm/Group/Row`, `Components/Ipcr/{rating.js,Matrix,FormE}`, `Pages/Ipcr/*`, `views/ipcr/print.blade.php` | 1 |
| 27 | IWOT (work output targets) | Set the period's targets + performance standards on the same matrix, save a draft, print the official sheet | 🟢 done | `IwotController`, `IwotForm/Group/Row`, `IwotAccess`, `Pages/Iwot/*`, `views/iwot/print.blade.php` | 1,26 |
| 28 | E-signatures on IPCR/IWOT | One signature image per named block, dropped onto the printed sheet (as CS Form 6 already does) | 🟢 done | `App\Support\FormSignatures`, `*_signatures` routes, `views/partials/signature-picker.blade.php` | 21,26,27 |
| 29 | Portal dashboards | The 15SW personnel-portal look on both dashboards: status ticker, HUD hero, Quick Access, dark panels/roster. Forms stay white | 🟢 done | `Components/Portal.jsx`, `Pages/Dashboard.jsx`, `DashboardController` | 1,16,17 |

## Feature detail notes

### 2 — Admin/employee separation
- `LeaveWorkflow::isAdmin` = admin role AND not in employee mode (`ViewMode`).
- Filing routes guarded by `employee` middleware (`EnsureEmployeeMode`) → an
  admin is redirected to `leave.requests`. `leave.index` also redirects admins
  to the queue; nav shows "Leave requests" vs "My Leave" per hat.
- `canDecide` (7.A / signatories / decision) is admin-only; `canSignBlock` lets
  the applicant sign only their own 6.D. Nobody decides their own leave.

### 10 — Year-end mandatory-leave forfeiture
- CSC Sec. 25 Rule XVI: 5 VL days/yr are mandatory; the unused remainder does
  not carry over. Forfeit = min(5, VL earned that year) − VL availed that year.
- Lazy on read, only for closed years; one `event_key='forfeit-fl-<year>'` row,
  recomputed each read so back-recording a leave into a closed year refunds it.
- On the printed leave card the forfeiture is netted into the year's Earned
  column (a full closed year reads 10 VL / 15 SL), with a "less N mandatory
  leave not availed" note in Particulars.

### 26 — IPCR form (Matrix + FORM E)
- Two sheets, one record. The **matrix** holds each Major Final Output with its
  three measure rows (Quality / Timeliness / Quantity), their targets and the
  five Performance Standards descriptors; **FORM E** holds the actual
  accomplishment, the % achieved per measure, the Ql1/Qn2/T3/A4 ratings, the
  intervening activities and the signatory blocks.
- **Auto-rating:** the % achieved is compared against the numbers parsed from
  that measure's five descriptors (Outstanding → 5 … Poor → 1). Clicking a
  descriptor cell marks it (persisted in `ipcr_form_rows.selected_band`) and
  copies its % down. A rating typed by hand wins; anything left blank is
  derived server-side on save, so a score never depends on the browser.
- **Scores:** A4 = mean of the given Ql/Qn/T; average point score = mean of the
  A4s; overall = average + the intervening-activity total; the numerical rating
  is that capped at 5.00, and the adjectival rating is its CSC band.
- **Form E dates are free text** — filled from the rating period ("January -
  June 2026" → "January" / "June 2026"), never calendar dates.
- Signatories are the Form E cells themselves; `reviewer_sig`/`approver_sig`
  are frozen from "Reviewed by" / "Approved by" at save.
- `/ipcr/{id}/print` renders the **official Form E** from the office template:
  the commitment paragraph, the orange Reviewed/Approved band, the blue rating
  grid (Output · Success Indicator · Actual Accomplishment · Q/Qn/T3/A4 ·
  Remarks), the summary ladder, and the rater's block — with an e-signature
  slot on each of the six named blocks.

### 27 — IWOT (Individual Work Output Target)
- The targets half of the cycle: the same matrix component as the IPCR, but
  target-setting only, so no achieved-standard picker and no ratings.
- Printed from the office template on a landscape page: three header lines
  (name / position+SG / unit), the matrix, then PREPARED BY (employee) and
  APPROVED BY (NCOIC) with a signature band over each name.
- Workflow mirrors the IPCR: draft → submitted → approved by a manager (or
  returned). Once approved the employee can no longer edit it.

### 28 — E-signatures on IPCR / IWOT
- `App\Support\FormSignatures` stores one image per block in the form's
  `signature_uploads` JSON, trimmed by `SignatureImage` so the ink prints big.
- Who may sign what: on Form E the ratee owns the commitment and "Discussed
  with" blocks, a manager owns the four supervisor blocks; on the IWOT the
  employee owns "Prepared by" and a manager owns "Approved by".
- A signatory with an account falls back to their account e-signature until an
  image is uploaded onto the form itself — the supervisors here rarely have
  accounts, which is why the image belongs to the form.

### 29 — Portal dashboards
- Ported from the standalone portal's `home.php`: status ticker (STATUS /
  DATE / live TIME / CLEARANCE), the HUD hero card with bracket corners and a
  rotating radar sweep behind the avatar, and the Quick Access modules.
- `Components/Portal.jsx` is the whole kit — `Portal` (dark shell + the one
  inline `<style>`), `PortalTicker`, `PortalHero`, `PortalModules`,
  `PortalPanel`, `PortalStat`, `PortalFooter` — so both dashboards share it.
- **Employee view:** hero → Quick Access (IPCR / IWOT / Leave) → My Status
  panels (IPCR semesters, leave balances, L&D) → trainings list.
- **Admin view:** the roster leads, because that is what an admin comes for.
  Compact hero → **Personnel Status Board** (the three summary panels, then
  the 17-row roster with its clickable IPCR semester chips and the pending
  L&D / pending leave panels) → Quick Access (Personnel / Leave requests /
  IWOT / IPCR).
- **The nav bar is the portal's**, on every page: navy/gold, gold underline on
  the active link, the hat switch as a gold pill, and a user chip (initials +
  name + role) opening Account settings / Log Out. Under 1024px it collapses
  to a burger drawer; under 480px the wordmark drops so the seal alone carries
  the brand and the burger still fits.
- **Page bodies stay white outside the dashboards.** Leave, IPCR and IWOT
  sheets — and the white page-title strip above them — keep the light chrome:
  they are data entry and they print. The L&D modals stay white too.
- The dashboard passes no title strip at all, so the portal banner starts
  directly under the nav. Holidays and the audit trail moved from that strip
  into the admin Quick Access modules.
- **Fillable on a phone held sideways.** Under 1024px (`resources/css/app.css`)
  every cell on the IPCR/IWOT sheets goes to 16px with a 38px minimum height —
  below 16px iOS Safari zooms the page the moment you tap a field, which throws
  away your place in the table — and the sheets keep a 62rem minimum width so
  they scroll inside their card instead of collapsing into slivers, with a
  "swipe sideways" hint above. The roster and both sheets scroll in their own
  box; the page itself never scrolls sideways at any width.
- No webfont or external stylesheet — system fonts and one inline `<style>`,
  because the production host is a closed .mil.ph box. The avatar falls back
  to initials since accounts have no profile picture.
