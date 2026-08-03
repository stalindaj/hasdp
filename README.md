# Civilian Personnel Management System (15th Strike Wing)

One Laravel system, one login, three functions for civilian personnel:

- **Leave** — print-exact CS Form No. 6 with tracked leave credits.
- **IPCR** — Individual Performance Commitment and Review.
- **Learning & Development** and IPCR compliance tracking.

The whole app lives in [`civhr/`](civhr/) (Laravel 13 + Inertia/React). The IPCR
module was originally a separate PHP + MySQL app; it has been **converted
natively into Laravel** — same data model, same rating logic, same navy/gold
look, and the same "scanning radar" sign-in page — so there is now a single
codebase, a single database, and a single set of accounts.

## IPCR at a glance

| Piece | Where |
|---|---|
| Schema (`ipcr_forms` → `ipcr_form_groups` → `ipcr_form_rows`) | `civhr/database/migrations/2026_08_04_000000_create_ipcr_form_tables.php` |
| Models + rating maths | `civhr/app/Models/IpcrForm.php`, `IpcrFormGroup.php`, `IpcrFormRow.php` |
| Access rules | `civhr/app/Support/IpcrAccess.php` |
| Controller + routes | `civhr/app/Http/Controllers/IpcrController.php`, `routes/web.php` (`ipcr.*`) |
| Pages | `civhr/resources/js/Pages/Ipcr/{Index,Form,Show}.jsx` |
| Print | `civhr/resources/views/ipcr/print.blade.php` |
| Sign-in (radar design) | `civhr/resources/js/Pages/Auth/Login.jsx` |

**Roles.** Managers (admin / superadmin / HR officer / approver) see and edit
everyone's IPCR and do the rating; a ratee sees and edits their own draft.
Accounts are the shared `users` table — login is employee number or email.

**Rating logic (unchanged from the original).** Each Major Final Output carries
Quality / Timeliness / Quantity ratings; the group average is their mean, and
the overall numerical rating is the mean of the group averages, mapped to the
CSC 5-point adjectival scale (Outstanding ≥ 4.5 … Poor < 1.5), computed
server-side on save.

## Running locally

Requires [Laravel Herd](https://herd.laravel.com) (PHP 8) and Node.

```bash
cd civhr
composer install && npm install
php artisan migrate --seed        # or migrate:fresh --seed
npm run build                     # or: npm run dev
php artisan serve --port=8123
```

Sign in, open **IPCR** in the top nav, create a form, rate it, view and print.

See [`civhr/CLAUDE.md`](civhr/CLAUDE.md) for stack pins and the shell-less
cPanel deploy notes.

## Rollback

This work is on the **`ipcr-integration`** branch; **`main`** is the system
before the IPCR conversion.

```bash
git checkout main                 # back to the pre-IPCR system
```
