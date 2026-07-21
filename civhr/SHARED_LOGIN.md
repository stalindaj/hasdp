# Shared login — contract for all 15SW systems

All systems (CivHR/Leave, IPCR, Learning & Development) share **one database**
and **one `users` table** so a person has a single account across every system.

- **Database:** `strikew_hris` (or whatever you named the shared DB), host
  `localhost`, on the same cPanel account.
- **Do NOT create a second `users` table.** This CivHR (Laravel) app creates
  and owns `users` via migrations. Other systems read/authenticate against it.
- **Password hashing:** bcrypt. Laravel writes hashes with `Hash::make()`.
  Any other system logs a person in with PHP `password_verify($input, $hash)`
  — no config needed, bcrypt is bcrypt on both sides.

## The `users` table (created by CivHR migrations)

| column            | type              | notes                                  |
|-------------------|-------------------|----------------------------------------|
| id                | BIGINT PK         |                                        |
| name              | VARCHAR           | display name                           |
| email             | VARCHAR UNIQUE    | the login identifier                   |
| password          | VARCHAR(255)      | **bcrypt** hash — never plain text     |
| employee_id       | BIGINT NULL (FK)  | → `employees.id`                       |
| is_active         | TINYINT(1)        | 0 = disabled; block login if 0         |
| email_verified_at | TIMESTAMP NULL    |                                        |
| remember_token    | VARCHAR NULL      |                                        |
| created_at / updated_at | TIMESTAMP    |                                        |

Roles are separate (a person can hold several):

- `roles` — `id`, `name` (e.g. `superadmin`, `admin`, `employee`, `hr_officer`,
  `recommender`, `approver`), `label`
- `role_user` — `user_id`, `role_id`

## How another system authenticates (raw PHP example)

```php
$pdo = new PDO('mysql:host=localhost;dbname=strikew_hris;charset=utf8mb4', $dbUser, $dbPass);

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
$stmt->execute([$inputEmail]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($inputPassword, $user['password'])) {
    // logged in — $user['id'] is the shared person id
    // roles: SELECT r.name FROM roles r
    //        JOIN role_user ru ON ru.role_id = r.id
    //        WHERE ru.user_id = ?
}
```

## Rules to keep it working

1. One shared DB, one `users` table. Other systems add **their own** feature
   tables (IPCR: `ipcr_*`; L&D: `trainings`, `enrollments`, …) in the same DB.
2. Reference `users.id` as `user_id` in those tables (FKs work because it's one
   database).
3. Only CivHR migrations change the `users` / `roles` tables. If another system
   needs a new user column, coordinate — don't `ALTER` it independently.
4. Per-system permissions come later: a person may be `employee` in Leave but an
   approver in IPCR. Handle that with per-system role tables when you get there;
   the shared `role`/`role_user` here is for CivHR.
