# Deploying CivDir to cPanel (shell-less)

The host has **no Node, no Composer, no SSH** — so the repo carries everything:

- `public/build` — compiled assets, built locally, committed
- `vendor/` — PHP dependencies, committed
- `/setup/<token>` — one-time web route that replaces `php artisan migrate`
  and `db:seed` (guarded by `SETUP_TOKEN` in `.env`)

Never run `npm` or `composer` on the server. There is nothing to run.

---

## First launch (~30 min, all in cPanel)

### 1. Database — ✅ done

`strikew_civdir`, user `strikew_app`, ALL PRIVILEGES. The password lives only
in the server's `.env` — never in chat, a commit, or a ticket.

### 2. GitHub token

GitHub → Settings → Developer settings → **Fine-grained tokens** → new token,
**Read-only** access to the `hasdp` repository only. Copy it once.

### 3. Clone the repo

cPanel → **Git™ Version Control** → Create:

- Clone URL: `https://<TOKEN>@github.com/stalindaj/hasdp.git`
- Repository path: `civdir`

### 4. Point the subdomain

cPanel → **Domains** → the subdomain's **document root** →
`civdir/civhr/public`.

### 5. PHP version

cPanel → **MultiPHP Manager** → set the subdomain to **PHP 8.3 or 8.4**
(`composer.json` requires `^8.3`; anything older white-screens).

### 6. Create `.env`

cPanel → **File Manager** → `civdir/civhr/` → new file `.env`. Paste the
prepared text (it already contains `APP_KEY` and `SETUP_TOKEN`), then fill in
`DB_PASSWORD` with the password from step 1.

`APP_DEBUG=false` matters: with it on, a stack trace on any error would leak
the database credentials to whoever hit the page.

### 7. Run setup

Visit `https://<subdomain>/setup/<SETUP_TOKEN>` once. It runs the migrations
and seeders (roles, the 14 CSC leave types, the full 15SW roster) and prints
what it did. Then edit `.env` and **blank the token**
(`SETUP_TOKEN=`) to disable the page.

### 8. Sign in

Login = **employee number** + `password123`. Superadmin: `5867`. Day one:
admins key in the true opening balances from the 201 files, everyone changes
their password.

---

## Routine deploy (after the first)

Locally:

```bash
npm run build
git add -A && git commit -m "…"
git push
```

On the server: cPanel → **Git™ Version Control** → **Pull or Deploy** →
Update from Remote.

If the push included **new migrations or seeder changes**: set `SETUP_TOKEN`
in `.env` again, visit `/setup/<token>`, blank it again. The route is
idempotent — re-running migrate + seed is always safe
(`test_the_seeders_are_idempotent`).

**Forgetting `npm run build` before pushing is the easiest way to break the
site** — the server cannot build assets itself, so it would keep serving the
previous bundle while the PHP side moves on.

---

## Notes

- **Config is not cached** on the server (no shell to run `config:cache`).
  `.env` edits therefore take effect immediately — that's intentional; do not
  cache config through some workaround, or `/setup` re-runs and `.env` edits
  will silently stop working.
- **Emails are not wired yet.** The workflow records every step, but nothing
  is sent on transition. When notifications are added they'll use the cPanel
  mailbox over SMTP (`mail.<domain>`, port 465).
- **The PAF seal** on the printed form needs a clean PNG; placeholder until
  then.
