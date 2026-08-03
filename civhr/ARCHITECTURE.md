# Architecture

> The map of the codebase. Concise — a map, not a copy of the code.

## Overview

CivDir (Civilian's Directory) is the 15th Strike Wing's civilian-personnel
system: one login for Leave (CS Form No. 6), IPCR compliance, and Learning &
Development. The headline feature is a print-exact, coordinate-positioned CS
Form No. 6 generated from a tracked leave-credit ledger and signed on-screen
(e-signatures) or by pen. Live in production on a shell-less cPanel host; see
`CLAUDE.md` for stack versions, deploy flow, and host quirks.

## Stack

- PHP 8.3 · Laravel 13 · Inertia.js 2 (Laravel + React 18) · Tailwind 3 · Vite 7
- DB: MySQL/InnoDB (prod), SQLite (local + tests). `public/build/` and `vendor/`
  are committed (host has no Node/Composer).

## Directory map

- `app/Http/Controllers/` — `LeaveController` (filing, edits, decisions,
  signatories, print), `DashboardController` (admin roster + employee card +
  credit adjustments + recorded leaves), `LeaveLedgerController` (printable
  CSC-style VL/SL leave card), `Admin/*` (users, employees, balances, holidays,
  audit).
- `app/Support/` — domain logic:
  - `LeaveWorkflow` — statuses + permission gates (`isAdmin`, `canFile`,
    `canDecide`, `canSignBlock`, `canCancel`, `canPrint`). Admin/employee split
    lives here.
  - `ViewMode` — the admin's employee-mode hat (session `view_mode`).
  - `CreditLedger` — append-only VL/SL ledger: lazy monthly accrual, approval
    deductions, and lazy year-end forfeiture of unused mandatory leave.
  - `WorkingDays` — server-side 6.C computation (skips weekends + `holidays`).
  - `LeaveCredits` — gross length-of-service estimate (pre-ledger fallback).
  - `SignatureImage` — signature upload/trim.
- `app/Http/Middleware/` — `EnsureAdmin`, `EnsureSuperadmin`,
  `EnsureEmployeeMode` (guards filing), `HandleInertiaRequests` (shares
  `auth.isAdmin/viewMode`, flash), `SecureRequests`.
- `resources/js/Pages/Leave/` — `Index` (my leave), `Requests` (admin queue),
  `Create`, `Show` (detail + edit + signatories + process). `Layouts/
  AuthenticatedLayout.jsx` — nav + employee-mode banner/toggle.
- `resources/views/leave/` — `print.blade.php` + `_sigblock.blade.php` (the
  coordinate-positioned CS Form 6, points from the official CSC PDF),
  `ledger.blade.php` (the leave card).
- `database/migrations/` — schema; each new one needs the `/setup` browser
  round-trip to reach prod (no `php artisan` on the host).

## Key entities & data flow

- `Employee` (HR record) ⇄ `User` (login, roles). `Role` many-to-many.
- `LeaveApplication` — one CS Form 6. Signature blocks (`applicant_sig`,
  `hr_officer_sig`, `recommender_sig`, `approver_sig`) frozen at filing;
  `signature_uploads` JSON holds per-block ink images.
- `LeaveCreditEntry` — append-only ledger row: `kind` (vl/sl/wellness/spl),
  `amount`, `period` (`YYYY-MM` for accruals), `event_key`
  (`forfeit-fl-<year>`). Balance = SUM(amount). Unique keys keep accrual +
  forfeiture idempotent.
- Flow: employee files (boxes 1–6, 6.D) → admin certifies 7.A + names 7.B/C/D +
  decides 7.C/7.D → approval deducts credits via `CreditLedger` → employee
  prints, wet-signs, uploads scan.

## Gotchas

- **Admin and employee are separate hats.** An admin cannot file; they switch
  to employee mode (drops admin access) to file. Nobody certifies/decides their
  own leave. `LeaveWorkflow::isAdmin` already accounts for the hat.
- **Ledger is lazy, no cron.** Accrual and year-end forfeiture post on read
  (`CreditLedger::ensureUpToDate`). Never edit balances in place.
- **CS Form 6 is coordinate-positioned** — verify print/UI changes in a real
  browser, not just markup.
- `rank` is a reserved word in MySQL 8; compare in PHP. Server clock is UTC,
  app tz Asia/Manila. See `CLAUDE.md` for the full host-quirk list.
