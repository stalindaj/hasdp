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
