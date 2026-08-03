# CLAUDE.md — CivDir (Civilian's Directory)

Guidance for Claude Code when working in this repo. Read this before making changes.

## What this is

CivDir is the 15th Strike Wing's civilian-personnel system: **one login** for
Leave (CS Form No. 6), IPCR compliance, and Learning & Development. The
headline feature is a **print-exact CS Form No. 6** generated from tracked
leave-credit data, signed on-screen (e-signatures) or by pen.

It is **live in production** on the office cPanel and maintained through GitHub
(`stalindaj/hasdp`, the app lives in the `civhr/` subfolder).

## Stack (exact versions — do not drift)

| Layer | Choice | Version |
|---|---|---|
| Language | PHP | **8.3** (`^8.3`; the host is pinned to 8.3 — see below) |
| Framework | Laravel | **13.21** (`^13.8`) |
| Front-end bridge | Inertia.js (Laravel + React) | **2.0** |
| UI | React | **18.2** |
| Styling | Tailwind CSS | **3.x** (`^3.2`) — **not** v4, despite `@tailwindcss/vite` being present |
| Build | Vite | **7.0** |
| Auth scaffolding | Laravel Breeze (React) | **2.4** |
| Tests | PHPUnit | **12.5** |
| Node (build only) | Node | **24 LTS** |
| DB (prod) | MySQL, **InnoDB** | via cPanel |
| DB (local dev) | SQLite | `database/database.sqlite` |

> **Pin Composer to the host's PHP** so it never resolves a dependency that
> needs a newer runtime: `composer config platform.php 8.3.0` is already set.

## Commands

```bash
# install
composer install
npm install

# dev (local)
php artisan serve            # app on :8000 (or use the launch.json 'civdir' on :8123)
npm run dev                  # Vite HMR

# build assets — REQUIRED before committing front-end changes (see deploy note)
npm run build

# tests — the whole suite must stay green
php artisan test
php artisan test --filter=SomeTest

# migrations (local)
php artisan migrate
php artisan migrate:fresh --seed
```

## Non-negotiable conventions

- **Always `npm run build` before committing** any change under
  `resources/js/**` or the Blade views that reference Vite assets. The
  production host has **no Node**, so `public/build/` is committed to git and
  served as-is. Forgetting this ships a stale bundle.
- **`vendor/` is committed too** — the host has no Composer. After changing PHP
  dependencies, run `composer install` locally and commit `vendor/`.
- **Every feature ships with tests.** The suite is currently ~150 tests; never
  commit with a red suite. Verify user-visible print/UI changes in a real
  browser, not just by reading markup — the CS Form 6 is coordinate-positioned
  and easy to break silently.
- **6.C working days are computed server-side**, never taken from the client
  (weekends + the `holidays` table are skipped; calendar-day types like
  maternity count all). See `App\Support\WorkingDays`.
- **Signature blocks** on CS Form 6 are frozen into each application at filing
  time (`applicant_sig`, `hr_officer_sig`, etc.), but **e-signature images**
  resolve live from the signatory's account, so uploading one later fixes every
  form. Civilians print no rank/branch (`employees.is_civilian`).
- **Credit ledger is append-only** (`App\Support\CreditLedger`): VL/SL accrue
  +1.25/month lazily on read (no cron), approvals deduct, edits to an approved
  leave re-apply the ledger. Never edit balances in place. **At year-end the
  unused part of the 5 mandatory/forced VL days is forfeited** (also lazy on
  read, one `event_key='forfeit-fl-<year>'` row per closed year, recomputed so
  back-recording a leave into a closed year gives the days back). A full year
  therefore carries forward **10 VL and 15 SL**, not 15/15; SL never forfeits.
- **Admin ≠ employee, one hat at a time** (`App\Support\ViewMode`,
  `LeaveWorkflow`): an admin processes others' leave and **cannot file their
  own** — they switch to employee mode (top-bar toggle) first, which drops all
  admin access while on. Filing is `employee`-middleware-guarded; certifying
  (7.A), naming signatories (7.B/C/D) and deciding are `canDecide` (admin-only);
  the applicant owns boxes 1–6 and signs only their own 6.D block
  (`canSignBlock`). Nobody certifies or decides their own leave.
- Follow Laravel/Pint style; match the surrounding code's comment density.
- **Do not add the Claude `Co-Authored-By` trailer to commits** (project
  preference).

## Deploy (shell-less cPanel — hard-won, read carefully)

The host has **no SSH, no Composer, no Node, no per-domain PHP control**. The
whole app is served from inside `public_html`. Because of that:

1. **Routine deploy:** push to GitHub → cPanel → Git™ Version Control →
   `civdir-live` → **Pull or Deploy → Update from Remote**.
2. **Migrations run from the browser** via a one-time route: set
   `SETUP_TOKEN` in the server `.env`, visit
   `/setup/<token>` (runs `migrate` + `db:seed`, idempotent), then blank the
   token again. There is no `php artisan` on the server.
3. **Security:** a deny-all `.htaccess` at the repo root keeps `.env` (with the
   DB password) unreachable; `civhr/public/.htaccess` re-allows the served
   folder. `FORCE_HTTPS=true` + `SESSION_SECURE_COOKIE=true` once AutoSSL is on.

### Host quirks that WILL bite (don't re-learn these)

- **PHP version is account-wide only.** Per-domain selectors, MultiPHP Manager,
  `.htaccess AddHandler`, and CGI wrappers are all disabled/absent here. Target
  whatever the account is set to (currently 8.3) and pin Composer to match.
- **MySQL defaults to MyISAM** → migrations fail with "key too long". Fixed by
  `'engine' => 'InnoDB'` in `config/database.php` + `Schema::defaultStringLength(191)`.
- **`rank` is a reserved word in MySQL 8** — never use it unquoted in raw SQL
  (do the comparison in PHP). SQLite (local) hides this; MySQL (prod) 500s.
- **Server clock is UTC** → `config/app.timezone` is `Asia/Manila`. Logs stay
  UTC (≈8h behind PH time).
- **`.env` passwords with `#`** get truncated — wrap in double quotes.
- **Raise upload limits** (MultiPHP INI Editor: `upload_max_filesize` 10M,
  `post_max_size` 12M) or phone photos of signed forms/signatures bounce.
- To see a real error behind a bare "500", flip `APP_DEBUG=true` briefly, then
  back to `false`; or read `civhr/storage/logs/laravel.log` (bottom-most
  `production.ERROR:`).

## Where things live

- `app/Http/Controllers/LeaveController.php` — filing, editing, drafts,
  decisions, signatories.
- `app/Support/{WorkingDays,CreditLedger,LeaveWorkflow}.php` — domain logic.
- `resources/views/leave/print.blade.php` + `_sigblock.blade.php` — the
  coordinate-positioned CS Form 6 (points from the official CSC PDF; keep the
  numbers).
- `resources/js/Pages/Leave/Show.jsx` — the leave detail + edit + signatory UI.
- `database/migrations/` — schema; each new one needs the `/setup` round-trip
  to reach prod.
