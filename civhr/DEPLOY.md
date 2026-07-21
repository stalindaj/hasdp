# Deploying CivHR to cPanel

The host has **no Node**, so compiled assets are built locally and committed
(`public/build` is intentionally tracked in git). Never run `npm` on the server.

---

## 1. Create the live database (cPanel — you must do this yourself)

In cPanel → **MySQL® Databases**:

1. **Create database** — name it `civhr`. cPanel prefixes it, so the real name
   becomes `strikew_civhr`.
2. **Create user** — name it `civhr`, i.e. `strikew_civhr`. Use cPanel's
   password generator and save the password in your password manager.
3. **Add user to database** → tick **ALL PRIVILEGES**.

Write the password straight into the server's `.env` (step 3). Do not paste it
into chat, a commit, or a ticket.

---

## 2. Get the code onto the server

cPanel → **Git™ Version Control** → clone the repo, or pull into an existing
checkout. Point the domain/subdomain's **document root** at the repo's
`public/` directory.

Then, from cPanel → **Terminal** (or SSH) in the project root:

```bash
composer install --no-dev --optimize-autoloader
```

If Composer is unavailable on the host, tell me and we will commit `vendor/`
instead — same trade-off we already made for `public/build`.

---

## 3. Configure `.env` on the server

Copy `.env.example` to `.env`, then set:

```dotenv
APP_NAME="CivHR"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain-here

# Printed in the header of CS Form No. 6
AGENCY_NAME="PHILIPPINE AIR FORCE"
AGENCY_ADDRESS="Col Jesus Villamor Air Base"
AGENCY_ADDRESS_2="Pasay City, Metro Manila"
AGENCY_LOGO_LEFT="images/paf-logo.png"     # service seal, header left
AGENCY_LOGO_RIGHT="images/agency-logo.png" # unit seal, header right
AGENCY_BRANCH_SUFFIX="PAF"                  # printed after ranked signatories

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=strikew_civhr
DB_USERNAME=strikew_civhr
DB_PASSWORD=the-password-from-step-1

# cPanel mailbox — sends the routing notifications
MAIL_MAILER=smtp
MAIL_HOST=mail.your-domain-here
MAIL_PORT=465
MAIL_USERNAME=no-reply@your-domain-here
MAIL_PASSWORD=the-mailbox-password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="no-reply@your-domain-here"
MAIL_FROM_NAME="${APP_NAME}"
```

`APP_DEBUG=false` matters: with it on, a stack trace on any error would leak
your database credentials to whoever hit the page.

Generate the app key (once, on the server):

```bash
php artisan key:generate
```

---

## 4. Build the schema and reference data

```bash
php artisan migrate --force          # --force: non-interactive, required in production
php artisan db:seed --force          # roles + the 14 CSC leave types; idempotent
php artisan storage:link
```

`db:seed` is safe to re-run on every deploy — both seeders use
`updateOrCreate`, covered by `test_the_seeders_are_idempotent`.

---

## 5. Cache for production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Re-run these after **any** `.env` change — once config is cached, edits to
`.env` are ignored until you re-cache.

---

## 6. Create the first superadmin

```bash
php artisan tinker
```

```php
$u = App\Models\User::create([
    'name' => 'Your Name',
    'email' => 'you@your-domain',
    'password' => Hash::make('set-a-strong-password-here'),
    'is_active' => true,
]);
$u->roles()->sync(App\Models\Role::where('name', 'superadmin')->pluck('id'));
```

Then sign in and use **Users** to create everyone else.

---

## Routine deploy (after the first)

Locally:

```bash
npm run build
git add public/build && git commit -m "build assets"
git push
```

On the server:

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Forgetting `npm run build` before pushing is the easiest way to break the
site** — the server cannot build assets itself, so it would keep serving the
previous bundle while the PHP side moves on.

---

## Notes

- **Leave credits are typed in, not computed.** The HR officer keys the
  balances into 7.A at certification time. There is no leave-credit ledger yet;
  if you want the balances tracked and carried forward automatically, that is a
  separate build.
- **Emails are not wired yet.** The workflow records every step, but nothing is
  sent on transition. `MAIL_MAILER=log` locally writes to
  `storage/logs/laravel.log`. Say the word and I will add the notifications.
- **The agency logo** on the printed form is a dashed placeholder. Send me the
  logo file and I will drop it in.
