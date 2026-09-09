# Upgrade Guide: 1.x to 2.x

This document details the migration path from 1.x (Laravel 9 + `encore/laravel-admin`) to 2.x (Laravel 13 + Filament v3).

---

## 1. Prerequisites & Environment

Before upgrading, ensure your hosting environment meets the following requirements:

- **PHP**: `^8.3`
- **Database**: MySQL `^8.0` or PostgreSQL `^14.0`
- **Node.js**: `^20.0`
- **Composer**: `^2.0`
- **Redis**: `^7.0` (recommended for caching and queue management)

---

## 2. Pre-Upgrade Backup (Mandatory)

Because 2.x drops 9 legacy `admin_*` tables and reorganizes administrator accounts into the standard `users` table, take a full database snapshot before proceeding.

### Using Spatie Laravel Backup:
```bash
php artisan backup:run --only-db
```

### Or using native database dump:
```bash
# MySQL
mysqldump -u <user> -p <database> > backup_pre_v2_upgrade.sql

# PostgreSQL
pg_dump -U <user> -d <database> -F c -b -v -f backup_pre_v2_upgrade.dump
```

---

## 3. Step-by-Step Upgrade Process

### Step 1: Fetch Code and Install Dependencies

Put the application into maintenance mode and update the repository:

```bash
php artisan down

git fetch origin
git checkout 2.x # or target release tag v2.1.0

composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

### Step 2: Migrate Administrator Accounts

The legacy `admin_users` table authenticated accounts by `username`. Filament v3 authenticates accounts via `email` on the standard `users` table.

Run the interactive migration tool:

```bash
php artisan admin:migrate-users
```

> [!NOTE]
> If your 1.x deployment customized database connection or table names in `config/admin.php`, specify `--connection=<name>` and `--table=<custom_table>` (or configure `LEGACY_ADMIN_CONNECTION` and `LEGACY_ADMIN_USERS_TABLE` in `.env`).

**Interactive Options per Account**:
- **Enter Email**: Supply a valid, unique email address for the administrator. The command transfers the account, preserving the existing bcrypt password hash and credentials.
  - If the email belongs to an existing user, you will be prompted to Overwrite, Skip, or Re-enter. Overwrite requires explicit confirmation and preserves the existing account's creation date.
- **`s` (Skip)**: Skips the account for now (account remains in `admin_users`).
- **`d` (Discard/Delete)**: Permanently discards obsolete or test legacy accounts so they will not block the schema cleanup.

> [!IMPORTANT]
> The command is idempotent and transaction-safe. If interrupted, re-running it resumes with only the remaining unmigrated accounts.

### Step 3: Run Database Migrations & Legacy Cleanup

Execute database migrations:

```bash
php artisan migrate --force
```

**Safeguard Protection**:
- If any accounts remain in `admin_users` (or custom table configured via `LEGACY_ADMIN_USERS_TABLE`), the migration **aborts immediately** with a `RuntimeException` to prevent data loss.
- Once legacy admin accounts are empty (or on a fresh install), the migration drops all 9 legacy tables:
  `admin_operation_log`, `admin_user_permissions`, `admin_role_users`, `admin_role_permissions`, `admin_role_menu`, `admin_permissions`, `admin_roles`, `admin_menu`, and `admin_users` (or `LEGACY_ADMIN_USERS_TABLE`).


### Step 4: Configure Admin Email Whitelist

In production (`APP_ENV=production`), panel access is restricted to emails configured in `ADMIN_EMAILS`.

Add or update `ADMIN_EMAILS` in `.env`:

```env
ADMIN_EMAILS=admin@example.com,developer@example.com
```

### Step 5: Optimization and Cache Warming

Clear and rebuild all application caches:

```bash
php artisan optimize:clear
php artisan optimize
php artisan filament:optimize
```

### Step 6: Restart Background Workers & Exit Maintenance

Restart queue workers and Horizon to reload new class definitions:

```bash
php artisan horizon:terminate
php artisan queue:restart
php artisan up
```

---

## 4. Post-Upgrade Verification Checklist

- [ ] Navigate to the panel login page.
- [ ] Log in with a migrated administrator's email and their original password.
- [ ] Confirm access to the dashboard, PV/UV metrics, and system health status.
- [ ] Confirm visitor records (`/visitors`) and logs (`/visitor-logs`).
- [ ] Confirm blacklist records (`/black-lists`) and logs (`/black-list-logs`).
- [ ] Verify Horizon dashboard (`/horizon`) and Telescope (`/telescope`) access.

---

## 5. Rollback Procedure

If unexpected issues occur during deployment:

1. Enable maintenance mode:
   ```bash
   php artisan down
   ```
2. Revert code checkout to previous release tag:
   ```bash
   git checkout master # or prior tag v2.0.0
   composer install --no-dev --optimize-autoloader
   npm ci && npm run prod
   ```
3. Restore database snapshot:
   ```bash
   # MySQL
   mysql -u <user> -p <database> < backup_pre_v2_upgrade.sql

   # PostgreSQL
   pg_restore -U <user> -d <database> --clean backup_pre_v2_upgrade.dump
   ```
4. Clear caches and restart workers:
   ```bash
   php artisan optimize:clear
   php artisan queue:restart
   php artisan up
   ```
