# Upgrade Guide: v2.0.0 to v2.1.0

This guide covers the complete production upgrade from the v2.0.0 release
(the previous master baseline) to v2.1.0 (the 2.x release line).

> **Important:** v2.1.0 contains breaking framework, runtime, administration,
> authentication, configuration, and database changes. Treat this as a planned
> production migration, not a routine dependency update.

The file-level source comparison is available at:
https://github.com/haoyuqi/laravel-backend-lab/compare/v2.0.0...v2.1.0

## Supported upgrade path

- Start from a working v2.0.0 deployment.
- Upgrade directly to the released v2.1.0 tag in production.
- Test the same application and database snapshot in staging first.
- The legacy admin and Filament admin must use the same default database
  connection. The migration command reads the legacy **admin_users** table from
  that connection.
- A custom legacy admin table or separate legacy admin database is not supported
  by the automated command and requires a separate migration plan.

## Breaking changes at a glance

| Area | v2.0.0 | v2.1.0 | Required action |
| --- | --- | --- | --- |
| PHP | 8.0.2 or newer | 8.3 or newer | Upgrade the production runtime before deployment. |
| Laravel | 9.x | 13.x | Review custom framework integrations and the new application structure. |
| Admin panel | Laravel Admin | Filament 3 | Migrate admin accounts, permissions, workflows, and bookmarks. |
| Admin URL | Legacy Laravel Admin URL | **/admin** | Update bookmarks, proxies, monitoring, and runbooks. |
| Admin identity | Username-based **admin_users** | Email-based **users** | Assign an email to every retained administrator. |
| Production access | Laravel Admin roles and permissions | **ADMIN_EMAILS** allowlist | Set the allowlist before enabling production traffic. |
| Legacy admin data | Laravel Admin tables | Tables removed | Export anything that must be retained before schema migration. |
| Application bootstrap | Kernel and Handler classes | Laravel fluent bootstrap | Move custom middleware and exception integration to **bootstrap/app.php**. |
| User model | **App\\User** | **App\\Models\\User** | Update custom imports and type references. |
| Localization | **resources/lang** and overtrue package | Root **lang** and laravel-lang | Move custom language overrides. |
| Frontend build | Laravel Mix development workflow | Laravel Mix with compatibility patch | Use **npm ci** and **npm run prod** for production. |
| Health check | Application-specific checks | **/up** | Point infrastructure health probes to the new endpoint. |
| Test stack | PHPUnit 9 / Dusk 7 | PHPUnit 12 / Dusk 8 | Update custom tests and extensions. |

## Complete change inventory

### Runtime and framework

- PHP is upgraded from 8.0.2+ to 8.3+.
- Laravel is upgraded from 9.x to 13.x.
- Production frontend builds require Node.js 20 and npm.
- The CI database matrix covers SQLite, MySQL 8.0 and 8.4, and PostgreSQL 17
  and 18. Production should use a currently supported database version.
- Redis 7 is recommended for cache, sessions, and queues.
- Laravel's health endpoint is available at **/up**.

### Application structure

The application now follows Laravel's streamlined bootstrap structure:

- Middleware, routing, and exception configuration live in
  **bootstrap/app.php**.
- Application service providers are registered in **bootstrap/providers.php**.
- Console scheduling and console routes live in **routes/console.php**.
- The old **app/Http/Kernel.php**, **app/Console/Kernel.php**, and
  **app/Exceptions/Handler.php** files are removed.
- Obsolete framework middleware and providers are removed.
- The user model moves from **App\\User** to **App\\Models\\User**.
- Factories and seeders use **database/factories** and **database/seeders** with
  their standard PSR-4 namespaces.
- Visitor recording is registered globally and page-view counting is appended
  to the web middleware group. Recheck custom middleware ordering if the
  application has local extensions.

### Administration and database behavior

Laravel Admin, its log viewer, **app/Admin**, and **config/admin.php** are
removed. Filament 3 provides the replacement administration panel at
**/admin**.

The replacement panel includes:

- Visitor management
- Visitor log management
- IP blacklist management
- Blacklist operation logs
- Dashboard page-view, unique-visitor, and system-health widgets
- Horizon and Telescope authorization using the production admin allowlist

Administrator authentication changes from legacy usernames in
**admin_users** to email addresses in the application's standard **users**
table. The interactive **admin:migrate-users** command processes every legacy
account as one of the following:

- Assign an email and migrate the account.
- Skip the account and leave it in **admin_users** for a later run.
- Discard the account and delete it without creating a replacement user.

For a migrated account, the command copies its name, password hash, remember
token, and original creation time. It deletes the source **admin_users** row in
the same database transaction as the destination user write.

If the chosen email already exists in **users**, overwriting it requires an
explicit confirmation. The existing user's creation time is retained.

The following legacy administration data is **not migrated** to Filament:

- Roles
- Permissions
- Role-to-user and role-to-permission assignments
- Custom menu configuration
- Laravel Admin operation logs

After all legacy accounts have been migrated or deliberately discarded, the
schema migration removes these tables:

- **admin_operation_log**
- **admin_user_permissions**
- **admin_role_users**
- **admin_role_permissions**
- **admin_role_menu**
- **admin_permissions**
- **admin_roles**
- **admin_menu**
- **admin_users**

The cleanup migration intentionally has no automatic down migration. A full
database backup is the only supported way to recover this data.

### Packages

The following application packages are removed:

- **encore/laravel-admin**
- **laravel-admin-ext/log-viewer**
- **fruitcake/laravel-cors**
- **haoyuqi/download-bing-wallpaper**
- **laravel/helpers**
- **laravel/ui**
- **overtrue/laravel-lang**

Notable replacements and upgrades include:

- Filament 3 replaces Laravel Admin.
- The Bing wallpaper integration is maintained inside the application.
- **laravel-lang/common** replaces **overtrue/laravel-lang**.
- Horizon moves to 5.x, Telescope to 5.x, Tinker to 3.x, and Spatie Laravel
  Backup to 10.x.
- PHPUnit moves to 12.x, Dusk to 8.x, Collision to 8.x, Debugbar to 4.x,
  and Laravel IDE Helper to 3.x.
- Composer's post-autoload hook now runs **filament:upgrade**.

If the project has private extensions that import a removed package or an old
framework class, update those extensions before production deployment.

### Routes and user-facing behavior

- Filament administration is served from **/admin**.
- The temporary **/filament** path is no longer available.
- Legacy Laravel Admin routes are removed.
- The public application routes remain, but controller declarations use modern
  class-based route syntax.
- The old Laravel UI package and its generated authentication controllers are
  removed. Any private code that used them must be migrated separately.
- Horizon remains available at **/horizon** and Telescope at **/telescope**,
  subject to their authorization gates.

### Localization

- Language files move from **resources/lang** to the root **lang** directory.
- Chinese translations are updated through **laravel-lang/common**.
- English validation, pagination, password, and HTTP status translations are
  included.
- Move any deployment-specific translation overrides to the new directory and
  compare them against the new vendor translations.

### Operational and data-compatibility changes

- The Fruitcake CORS package is removed in favor of Laravel's built-in CORS
  middleware. Re-test any deployment-specific CORS policy.
- Backup configuration is upgraded for the new Spatie package version. Review
  archive compression, encryption, destinations, retention, and notification
  recipients before relying on scheduled backups.
- Existing schedules are registered in **routes/console.php**: backups, visit
  statistics, file cleanup, blacklist cache cleanup, Telescope pruning, Bing
  wallpaper download, GeoIP cleanup, and the minute-level time event.
- Blacklist and visitor-log relationships now include soft-deleted related
  records so historical administration records remain readable.
- Invalid sort-count requests redirect to **/error** under the current request
  validation flow.
- The default Faker locale changes to **zh_CN**.
- The test suite uses a dedicated **testing** database connection and supports
  **TEST_DB_*** overrides. This prevents normal test runs from using the primary
  application database.

### Environment and configuration

Preserve the existing **APP_KEY**. Regenerating it will invalidate encrypted
application data, sessions, and cookies.

Review every production environment variable against the new
**.env.example**. The important changes are:

| Variable | Action |
| --- | --- |
| **APP_VERSION** | Set to **2.1.0**. |
| **ADMIN_EMAILS** | Add a comma-separated list of authorized production administrator emails. |
| **APP_KEY** | Preserve the existing value; do not regenerate it. |
| **APP_PREVIOUS_KEYS** | Optional comma-separated prior keys for key rotation compatibility. |
| **BCRYPT_ROUNDS** | Add if a non-default password hashing cost is required; the example uses 12. |
| **BROADCAST_CONNECTION** | Replaces **BROADCAST_DRIVER**; a compatibility fallback is retained. |
| **CACHE_STORE** | Replaces **CACHE_DRIVER**; a compatibility fallback is retained. |
| **MAIL_MAILER** | Replaces **MAIL_DRIVER**. |
| **MAIL_FROM_ADDRESS** | Set an explicit sender address. |
| **MAIL_FROM_NAME** | Set the sender display name. |
| **QUEUE_FAILED_DRIVER** | Review the failed-job storage driver. |
| **REDIS_CLIENT** | Review the Redis client selection. |
| **SESSION_DOMAIN** | Set when sessions must be shared across subdomains. |
| **SESSION_SECURE_COOKIE** | Enable for HTTPS production deployments. |
| **TEST_DB_*** | Test-only database overrides; do not point tests at production. |

The example Pusher variables are removed. Keep equivalent private settings only
if local application code still uses them.

The default cache prefix format also changes. Existing cache entries might not
be visible after deployment; plan for a cold cache and do not depend on old
cached values surviving the upgrade.

In production, an empty **ADMIN_EMAILS** value denies access to the Filament,
Horizon, and Telescope administration surfaces. Email comparison is
case-insensitive.

### Build, CI, and tests

- Code style is checked with Laravel Pint.
- Application tests run against SQLite, MySQL, and PostgreSQL.
- Browser tests run with Node.js 20 and Laravel Dusk.
- Laravel Mix remains the asset builder. A repository script applies the
  compatibility adjustment required by the current webpack dependency graph.
- The production asset command is **npm run prod**, not **npm run build**.

## Production upgrade procedure

### 1. Rehearse with a production database snapshot

Restore a recent production snapshot in an isolated staging environment and run
this entire procedure there first. Verify representative visitor, blacklist,
queue, scheduled task, Horizon, Telescope, and administration workflows.

Record the initial account counts before changing anything:

~~~sql
SELECT COUNT(*) AS legacy_admin_users FROM admin_users;
SELECT COUNT(*) AS application_users FROM users;
~~~

Decide which legacy administrators must be retained and collect a unique email
address for each one. Decide whether legacy roles, menu definitions, and
operation logs must be exported for audit or archival purposes.

### 2. Back up the current release and database

Create and verify both a deployment/code rollback point and a full database
backup before entering the irreversible part of the upgrade.

Using the existing v2.0.0 application backup integration:

~~~bash
php artisan backup:run --only-db
~~~

Or use the database-native backup command appropriate for the deployment:

~~~bash
# MySQL
mysqldump --single-transaction --routines --triggers DATABASE_NAME > pre-v2.1.0.sql

# PostgreSQL
pg_dump --format=custom --file=pre-v2.1.0.dump DATABASE_NAME
~~~

Do not continue until the backup exists, is non-empty, and has been test-restored
or validated using the database platform's restore tooling.

Also retain a secure copy of the current production environment configuration.
Do not commit or paste that file into an issue, pull request, or deployment log.

### 3. Enable maintenance mode

~~~bash
php artisan down --retry=60
~~~

Stop or drain queue workers and prevent the scheduler from starting new work
during the database migration window.

### 4. Deploy the v2.1.0 release tag

Deploy the immutable release tag rather than the moving **2.x** branch:

~~~bash
git fetch --tags origin
git checkout v2.1.0
~~~

The **2.x** branch may be used in staging before the release tag is created, but
production should use the final tag.

### 5. Update production environment configuration

At minimum, add or review:

~~~dotenv
APP_VERSION=2.1.0
ADMIN_EMAILS=admin@example.com
BCRYPT_ROUNDS=12
BROADCAST_CONNECTION=log
CACHE_STORE=file
QUEUE_FAILED_DRIVER=database
REDIS_CLIENT=phpredis
SESSION_SECURE_COOKIE=true
~~~

Use values appropriate for the deployment. Do not copy the sample administrator
email. Preserve **APP_KEY** and all production credentials.

### 6. Install application dependencies and build assets

~~~bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run prod
php artisan optimize:clear
~~~

Confirm that Composer scripts and the production webpack build finish
successfully before modifying the database.

### 7. Migrate legacy administrator accounts

Run the interactive migration in a real terminal:

~~~bash
php artisan admin:migrate-users
~~~

For every legacy account, choose exactly one outcome:

- Supply a valid, unique email to keep the administrator.
- Skip it to stop the deployment and resolve the account later.
- Explicitly discard it only when permanent deletion is intended.

If an email already belongs to an application user, inspect that account before
approving an overwrite. The existing user's name, password hash, and remember
token will be replaced by the legacy administrator values.

Run the command again if any accounts were skipped. Verify that no legacy
administrator remains before continuing:

~~~sql
SELECT COUNT(*) AS remaining_legacy_admin_users FROM admin_users;
~~~

The expected value is zero. Do not run the schema migrations while retained
accounts remain in this table.

### 8. Run database migrations

~~~bash
php artisan migrate --force
~~~

This is the irreversible point that removes the legacy administration tables.
If the migration refuses to drop them, stop and resolve the remaining
**admin_users** rows; do not bypass the guard manually.

### 9. Rebuild caches and restart workers

~~~bash
php artisan filament:optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan horizon:terminate
~~~

Restart the deployment's process manager so workers return on v2.1.0 code.

### 10. Bring the application online

~~~bash
php artisan up
~~~

## Post-upgrade verification

Verify all of the following before considering the upgrade complete:

- **/up** returns a healthy response.
- A listed administrator can sign in at **/admin** with the assigned email and
  existing password.
- An email not listed in **ADMIN_EMAILS** cannot enter the production panel.
- **/filament** and the old Laravel Admin URL are unavailable.
- Visitor and visitor-log pages load expected historical data.
- Blacklist creation, editing, matching, and operation logs work as expected.
- Dashboard page-view, unique-visitor, and system-health widgets load.
- Horizon and Telescope access follows the same production allowlist.
- Scheduled tasks and queue workers run on the new release.
- Compiled CSS and JavaScript load without browser console or network errors.
- Application error rates, database errors, failed jobs, and response latency
  remain normal.

Keep the database backup until the release has completed its production
observation period.

## Rollback procedure

### Before running admin:migrate-users

A code rollback is sufficient if no database-changing command has run:

~~~bash
git checkout v2.0.0
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run prod
php artisan optimize:clear
php artisan up
~~~

### After admin:migrate-users starts

The command deletes migrated or discarded rows from **admin_users**. A code-only
rollback is no longer sufficient, even if **php artisan migrate --force** has
not run yet.

1. Keep the application in maintenance mode.
2. Stop queue workers and scheduled jobs.
3. Restore the complete pre-v2.1.0 database backup.
4. Redeploy v2.0.0 and reinstall its locked dependencies and assets.
5. Clear caches, restart workers, and verify the legacy administration panel.
6. Bring the application online only after code and database versions match.

Do not rely on **php artisan migrate:rollback** for this release. The legacy
administration cleanup intentionally cannot reconstruct deleted accounts,
roles, permissions, menus, or operation logs.

## Release checklist

- [ ] Staging rehearsal completed with a recent production snapshot.
- [ ] PHP, Node.js, database, and Redis versions meet the requirements.
- [ ] Full database backup created and restore-tested.
- [ ] Existing **APP_KEY** preserved.
- [ ] **APP_VERSION=2.1.0** configured.
- [ ] **ADMIN_EMAILS** configured and reviewed.
- [ ] Every retained legacy administrator has a unique email.
- [ ] Required legacy roles, menus, and logs exported for archival.
- [ ] Composer install and production frontend build succeeded.
- [ ] **admin:migrate-users** completed with zero legacy accounts remaining.
- [ ] Database migrations completed without bypassing the account guard.
- [ ] Filament, Horizon, Telescope, visitors, and blacklist workflows verified.
- [ ] Queues, schedules, monitoring, and health checks verified.
- [ ] Rollback owner, backup location, and observation window recorded.
