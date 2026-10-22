# Upgrade Guide

## v3.0.1 to v3.0.2

This security cleanup removes the unused `public/vendor/laravel-admin/` assets
left behind after the Filament migration, including the PJAX and Bootstrap
Slider scripts flagged by CodeQL. It adds no database migration and changes no
application dependencies.

1. Deploy the new release artifact. If deploying by copying files over an
   existing installation, explicitly remove the old
   `public/vendor/laravel-admin/` directory from every web server and shared
   public volume. A copy-only deployment can leave the vulnerable scripts
   accessible. Preserve the active Horizon, Telescope, and Filament assets.
2. Purge cached copies of the removed paths from any CDN or reverse-proxy
   cache. Verify that the two flagged scripts, `jquery-pjax/jquery.pjax.js` and
   `AdminLTE/plugins/bootstrap-slider/bootstrap-slider.js`, are no longer served
   under `/vendor/laravel-admin/`.
3. Preserve `.env` and `APP_KEY`, set `APP_VERSION=3.0.2`, rebuild configuration
   using the deployment's normal cache policy, and verify admin login and
   the public pages. Use the existing locked dependency/build process when
   preparing the release artifact.

Earlier installations must also complete the applicable upgrade steps below.
Rolling back to an older artifact can restore the removed scripts and their
security findings; prefer keeping this unused directory removed.

## v3.0.0 to v3.0.1

This update adds `2026_10_15_000000_harden_request_logs.php`. It widens both
request-log URL columns to text and adds nullable unique event IDs for retry
deduplication. Existing log rows and their IDs are preserved.

1. Back up the application database, rehearse the migration on a copy, enable
   maintenance mode, pause the scheduler, and stop workers before replacing code.
2. Deploy the locked application dependencies and build frontend assets using
   Node.js 24 LTS. Preserve `.env` and `APP_KEY`, set `APP_VERSION=3.0.1`, and
   clear old compiled configuration.
3. Run `php artisan migrate --force` against the intended application database
   before starting the new workers or reopening traffic. The new jobs require
   the event-ID columns. On MySQL, DDL may commit partially; inspect migration
   status and schema if a migration fails. Do not drop or recreate log tables.
4. Review `TRUSTED_PROXIES`, backup notification settings, and demo access as
   documented in the README. The demos now default to disabled; queue creation
   uses POST, and the item limit is 1,000. Blacklist enforcement queries the
   database on every request, so account for that database load.
5. Rebuild configuration and restart workers on the new code. Queue reservation
   timeouts increase to 180 seconds, exceeding the 120-second job timeout. Existing
   queued jobs remain supported. Check health, admin login, blacklist enforcement,
   and visitor/blocked-request logging before resuming normal traffic.

New URL records omit query strings and fragments; historical rows are preserved.
Before rolling back code, stop workers and writers again. The widened columns
and nullable event IDs can remain in place when restoring the old artifact.
Schema rollback refuses to narrow URL columns while any value exceeds 255
characters, preventing truncation. Do not delete long records to force rollback;
retain the schema or reconcile data under a separately reviewed recovery plan.

## v2.2.1 to v3.0.0

This is a breaking application release. Laravel remains on 13.x; PHP now
requires 8.4+, Filament moves from 3 to 5, Livewire from 3 to 4, and Reverb
replaces Socket.IO/Redis broadcasting. Redis remains required for application
statistics and blacklist features. Install the committed lock files; do not run
`composer update` or the Filament upgrade tools on a production deployment.

### Deployment checklist

1. Rehearse on an isolated copy of production data and configuration. Upgrade
   PHP CLI, web/FPM and worker runtimes to 8.4+, with the extensions required by
   `composer.lock`. Keep the previous release artifact, environment settings,
   database backup and Redis recovery point. Older installations must first
   complete the upgrade procedures for their version below.
2. Enable maintenance mode (`php artisan down`), pause the scheduler and stop
   queue workers and other statistics writers. Maintenance mode alone does not
   stop background jobs. Check for duplicate daily statistics using the SQL
   below before proceeding; retain the backup even after deduplication.
3. Deploy v3.0.0 and preserve the existing `.env` and `APP_KEY`. Set
   `APP_VERSION=3.0.0`. Clear previous compiled configuration with
   `php artisan optimize:clear`. Install dependencies with
   `composer install --no-dev --prefer-dist --no-interaction`, followed by
   `composer check-platform-reqs --no-dev`. Composer hooks discover packages
   and publish the Filament assets.
4. Configure Reverb as described below, or explicitly select
   `BROADCAST_CONNECTION=log` and leave `REVERB_APP_KEY` empty to disable live
   updates. Keep Reverb's application secret server-side. For HTTPS sites,
   expose a TLS WebSocket endpoint through your reverse proxy, including
   WebSocket upgrade headers and the Reverb HTTP publishing endpoints. Point
   `VITE_REVERB_*` at the browser-accessible endpoint and `REVERB_HOST`,
   `REVERB_PORT`, `REVERB_SCHEME` at an endpoint reachable by Laravel. The
   `REVERB_SERVER_*` values control the internal listening socket.
5. Run `npm ci && npm run build` after Composer installation and finalizing
   the frontend connection settings. Deploy the complete `public/build`
   directory and generated Filament assets. Node.js 20 is the CI build target;
   Node/npm and development dependencies are needed on the build machine,
   not on a server receiving prebuilt assets.
6. Review `php artisan migrate:status`, then run `php artisan migrate --force`
   against the intended application database. From v2.2.1 the new migrations
   are, in order:
   - `2026_10_02_000000_make_visitor_statistics_unique.php`: archive duplicate
     snapshots, retain one snapshot per type/date, and add a unique constraint.
   - `2026_10_11_000000_create_admin_operation_logs_table.php`: create the
     audit table without rewriting existing business records.
   Do not reopen admin access before both migrations succeed. On MySQL, DDL
   is not covered by a single rollback transaction; inspect any failed
   migration before retrying instead of assuming the schema is untouched.
7. Rebuild configuration using your normal deployment cache policy
   (`php artisan config:cache`). Stop the old Socket.IO service. Configure your
   process manager to run `php artisan reverb:start` when live updates are
   enabled, and restart workers on the new code. An asynchronous broadcasting
   queue must consume `push-time`; retain other existing queue consumers.
   Resume the once-per-minute scheduler after validating the database.
8. Verify migration status, daily statistics uniqueness and the duplicate
   archive. Check `/up`, admin login/logout, the read-only operation log page
   and a controlled blacklist add/edit/delete, including its audit rows.
   Verify desktop/mobile admin styling and `/time` live delivery/reconnection
   when enabled. Check service logs for errors, then run `php artisan up`.
   Use the deployment's existing controlled access mechanism for checks while
   maintenance mode is enabled; never publish a maintenance bypass secret.

Do not run installation-only commands such as `key:generate`, database seeds
or administrator creation against an existing deployment.

### Rollback

Stop writers and background processes again and keep maintenance mode enabled.
Restore the v2.2.1 artifact, its locked dependencies, frontend assets and
environment configuration; restore Socket.IO only if it was previously used.
Clear/rebuild caches and restart the old workers before reopening traffic.
The new audit and duplicate archive tables may remain to preserve history.
The statistics unique constraint may also remain, but it does not make the
old non-idempotent statistics writer safe to retry; keep that writer paused
until a rollback-compatible plan is verified. Removing the constraint does
not restore discarded snapshots. Exact data restoration requires the backup
and reconciliation of writes made since deployment. Never drop audit history
or reinsert archived rows automatically. See the migration-specific notes below.

### Real-time updates: Socket.IO → Reverb (#137)

The public `push-time` channel, `PushTimeEvent` payload, `push-time` queue,
and once-per-minute schedule are unchanged. Redis broadcasting and the
Socket.IO client have been removed. This change requires no database migration.

1. Install the new locked dependencies with `composer install` and `npm ci`.
2. For live updates, run `php artisan reverb:install` to generate local
   credentials and enable `BROADCAST_CONNECTION=reverb`. Configure `REVERB_*`
   and `VITE_REVERB_*` as described in the README. Remove `VITE_REDIS_PREFIX`
   (or the older `MIX_REDIS_PREFIX`) and `REDIS_BROADCAST_DB`; Reverb subscribes
   to `push-time` directly and does not use a Redis key prefix.
3. Stop the old Socket.IO broadcasting server for this application. Start
   Reverb and the scheduler; asynchronous queues need a worker consuming
   `push-time`. Rebuild assets and verify `/time` receives new rows.
4. To leave real-time updates disabled, keep `BROADCAST_CONNECTION=log` and
   `REVERB_APP_KEY` empty, then rebuild assets. The initial time still renders.

The v2.x sections below describe previous releases; their Socket.IO instructions
are superseded by the v3.0.0 deployment checklist and this section.

### Admin operation logs (#119)

This change adds `admin_operation_logs` and the read-only admin log page. It does
not migrate or rewrite existing users, visitors, blacklists, or interception
logs. Historical admin actions are not backfilled.

1. Back up the application database and deploy in a maintenance window so new
   admin actions cannot run before their log table exists.
2. Deploy the code, then run `php artisan migrate --force` against the intended
   application database using your normal deployment environment. The new
   migration is `2026_10_11_000000_create_admin_operation_logs_table.php`.
3. Verify an authorized admin can view **系统 → 操作日志** and perform a test
   blacklist change, then log out and log back in. Check the login/logout rows,
   resource type/ID, actor, safe before/after state, and time. Unauthorized
   users must remain denied. Exit maintenance mode after verification.
4. Include the new table in backups and decide how long operator names and IPs
   should be retained. Pruning is manual and dry-run by default; see the README.

If the log table is missing or log writes fail, audited admin data changes fail
and roll back instead of silently succeeding without history. Bulk actions
commit each successful record independently. Direct model/job/CLI writes are
outside this admin audit coverage.

The new table uses stable resource aliases and nullable string subject IDs;
authentication events have no subject. Existing action history is not backfilled.
The migration ships with v3.0.0: do not edit an already deployed copy. If a
previous development version of this table has been deployed, stop and prepare
an additive compatibility migration rather than dropping its history.

Resource pages use `App\Filament\Pages\CreateRecord`, `EditRecord` and
`ListRecords`. Add safe model policies in `config/admin-operation-logs.php`.
The edit base covers both full and partial form saves. Single-record actions
are transactional; bulk callbacks retain per-record transactions and must use
`AdminOperationRecorder::run()`. Builder/pivot/raw SQL custom mutations require
its explicit locked snapshot API. Do not assume model observers cover writes
that bypass events, or that a default-connection transaction covers another DB.
Relationship writes must supply a deterministic persisted snapshot callback as
the fourth `explicit()` argument, and configure its `relationship_fields` whitelist.
Coordinate all relationship writers through the same parent-row lock/connection.

Successful backend login is recorded after the authentication method returns;
audit failure revokes the new session/remember state instead of allowing an
unaudited login. Logout remains possible during audit-storage failure, displays
a warning, and may lack a logout row in that exceptional case. Reads/searches/
exports, rejected attempts and non-backend shared-guard authentication are excluded.

For rollback, restore the previous code first; the additional table can remain
to preserve history. Rolling back this migration drops only the new table and
**permanently removes its audit history**. Back it up before any schema rollback.
No development or production database migration is executed by the tests.

### Filament 3 to 5 upgrade (#136)

This upgrade modernizes the administrative panel by upgrading Filament from `^3.0` to `^5.0` and Livewire from `^3.0` to `^4.0`.

The following stages document how the repository's source code was migrated.
They are for maintainers adapting private customizations on a development
branch, not steps to run while deploying this release. Deploying the committed
v3.0.0 code only requires the locked dependency installation and asset build in
the deployment checklist above. Review private plugins for Filament 5 and
Livewire 4 compatibility before upgrading.

#### Source migration preconditions

- PHP >= 8.4 and Laravel 13.x.
- Clean working directory on a short-lived branch derived from `master`.
- Baseline tests passing (`php artisan test`).
- No custom third-party Filament plugins installed.
- Ensure the development environment has Node.js (v20+) and Composer v2 available.

#### Stage A: Filament 3 → 4

1. Install upgrade tooling:
   ```bash
   composer require filament/upgrade:"^4.0" -W --dev
   ```
2. Run Rector-based upgrade script:
   ```bash
   vendor/bin/filament-v4
   ```
3. Update Composer dependencies to Filament 4:
   ```bash
   composer require filament/filament:"^4.0" -W --no-update
   composer update -W
   composer remove filament/upgrade --dev
   ```
4. Verify code changes:
   - Resource navigation property type declarations: `$navigationGroup` widened to `string|\UnitEnum|null` and `$navigationIcon` to `string|\BackedEnum|null`.
   - Resource form methods updated from `form(Form $form): Form` to `form(Schema $schema): Schema`.
   - Layout components migrated to `Filament\Schemas\Components\*` and actions to `Filament\Actions\*`.
   - Login page namespace updated to `Filament\Auth\Pages\Login`.
   - Custom theme created with `php artisan make:filament-theme admin` with Tailwind CSS v4 support, registered via `->viteTheme(...)` in `AdminPanelProvider.php`, and compiled with `npm run build`.

#### Stage B: Filament 4 → 5

1. Install upgrade tooling:
   ```bash
   composer require filament/upgrade:"^5.0" -W --dev
   ```
2. Run upgrade script:
   ```bash
   vendor/bin/filament-v5
   ```
3. Update Composer dependencies to Filament 5 and Livewire 4:
   ```bash
   composer require filament/filament:"^5.0" -W --no-update
   composer update -W
   composer remove filament/upgrade --dev
   ```
4. Code and UI updates:
   - Resource `$navigationIcon` converted from string to typed enum: `Heroicon::OutlinedUsers`, `Heroicon::OutlinedDocumentText`, `Heroicon::OutlinedNoSymbol`, `Heroicon::OutlinedShieldExclamation`.
   - Widgets verified and compiled with Vite.
   - Run full test suite: `php artisan test`.

#### Deployment note

The admin panel now uses a custom Filament theme
(`resources/css/filament/admin/theme.css`). Deployments must run
`composer install` first (the theme imports CSS from `vendor/filament`) and then
`npm ci && npm run build` so that `public/build` contains the compiled theme.
`public/fonts/` and the published Filament assets under `public/js` and
`public/css` are generated by `php artisan filament:upgrade`, which runs on
`composer install`, and are not tracked in Git.

#### Source migration rollback

For an unshipped source migration on a development branch (production rollback
must follow the release rollback procedure above):
1. Revert the upgrade commits in Git (review `git status` first; do not discard
   unrelated uncommitted work):
   ```bash
   git revert <commit-hash>
   ```
2. Reinstall previous Composer dependencies:
   ```bash
   composer install --no-interaction
   ```
3. Rebuild frontend assets:
   ```bash
   npm ci && npm run build
   ```
4. Clear caches and verify:
   ```bash
   php artisan filament:upgrade
   php artisan test
   ```

#### Known follow-ups

- When creating new Filament resources or pages with `php artisan make:filament-*`, use the new Filament 5 schema syntax.
- Periodically check for Filament minor updates (`composer update filament/filament`).

### Visitor statistics migration (#141)

Before deploying `2026_10_02_000000_make_visitor_statistics_unique`, back up the
database and stop the scheduler and all statistics writers. MySQL DDL commits
implicitly, so maintenance mode must cover deduplication and index creation.
Rehearse against a copy of production data first. Audit duplicate groups with:

```sql
SELECT type, date, COUNT(*) AS snapshots
FROM visitor_statistics
GROUP BY type, date
HAVING COUNT(*) > 1;
```

The migration retains one nonnegative cumulative snapshot per type/date. Active
rows take priority over soft-deleted rows; within that category, the latest
`updated_at` (falling back to `created_at`) wins, then the highest ID breaks ties.
Counts are never summed or selected by maximum value. If all snapshots in a
duplicate group are negative, the migration aborts for manual repair and rolls
back its data changes. Every discarded row, including its ID, timestamps and
soft-delete state, is retained in `visitor_statistics_duplicates`.

After migration, `save:visits-count` atomically upserts yesterday's PV/UV pair.
Repeating it updates the same rows and restores a soft-deleted row when a fresh
snapshot is available. Missing Redis snapshots leave existing statistics alone.
Before updating, the command locks both daily rows (including soft-deleted rows)
and rejects the entire pair if either incoming cumulative count is lower than
the saved value. This prevents a partial Redis recovery or stale concurrent run
from reducing persisted counts. Such a failure needs investigation; a syntactically
valid counter is not proof of a complete snapshot. This guard does not prove the
completeness of a first-ever snapshot or recover visits lost during an outage.
Both newly counted and successfully persisted Redis keys expire at midnight
eight calendar days after their statistics date: seven complete days are
available for retries after that date ends. Deadlines use the application
timezone and do not extend on retries. Redis expiration failures report command
failure after database commit; retrying is safe. Existing historical keys are
not scanned or removed by the migration; audit and expire legacy keys separately
after confirming their database snapshots have been persisted.

The default command processes yesterday. To retry an older retained day, use
`php artisan save:visits-count --date=2026-10-01` with the desired date in the
application timezone. Dates must be strict `YYYY-MM-DD`, earlier than today,
and before their fixed expiration deadline; today, future dates and expired
days are rejected without reading Redis or modifying statistics. Retries never
extend the deadline. Request counting completes both counter operations before
attempting TTL maintenance; failure to expire one key does not skip the other.

Verify daily row uniqueness, the retained archive and Redis TTLs before
restarting writers. Rollback removes the unique constraint but deliberately
keeps the archive and deduplicated data. Restoring the exact original dataset
requires the pre-deployment backup; do not automatically reinsert archived rows
over newer statistics. Keep the archive until your retention policy permits
removal.

## v2.1.0 to v2.2.0

This release modernizes the frontend toolchain. It replaces Laravel Mix with
Vite and upgrades Vue, Bootstrap, Axios, Laravel Echo, Socket.IO Client, and
Sass. It does not add application database migrations.

The source comparison is available at:
https://github.com/haoyuqi/laravel-backend-lab/compare/v2.1.0...v2.2.0

### Supported upgrade path

- Upgrade from a completed v2.1.0 production deployment to v2.2.0.
- If the application is still on v2.0.0, complete the v2.0.0 to v2.1.0
  procedure below first. Do not skip its administrator and database migration.
- Use Node.js 20 to install and build frontend dependencies.

### Breaking changes

| Area | v2.1.0 | v2.2.0 | Required action |
| --- | --- | --- | --- |
| Asset builder | Laravel Mix 6 | Vite 6 | Replace Mix commands and deployment artifacts. |
| Production build | `npm run prod` | `npm run build` | Update deployment scripts and CI integrations. |
| Development server | Mix/Webpack | Vite | Use `npm run dev`; expose the Vite port when developing in Docker. |
| Manifest | `public/mix-manifest.json` | `public/build/manifest.json` | Deploy the complete `public/build` directory. |
| Vue | 2.6 | 3.5 | Update private components and plugins to the Vue 3 application API. |
| Bootstrap | 4.5 with jQuery | 5.3 without jQuery | Review markup, data attributes, utilities, and custom JavaScript. |
| Axios | 0.33 | 1.7 | Review private interceptors and integrations for Axios 1 behavior. |
| Laravel Echo | 1.8 | 2.5 | Re-test private real-time channel integrations. |
| Socket.IO Client | 2.3 | 4.8 | Confirm compatibility with the deployed Socket.IO server. |
| JavaScript modules | CommonJS | ES modules | Replace private `require()` calls with `import` statements. |
| Realtime prefix | `MIX_REDIS_PREFIX` | `VITE_REDIS_PREFIX` | Rename the frontend environment variable. |

The package lock changes substantially because the Webpack/Laravel Mix
dependency tree is removed. Install dependencies from the lock file; do not
manually preserve packages that are no longer declared in `package.json`.

### Production upgrade procedure

1. Rehearse the deployment in staging and verify all pages that load project
   JavaScript or CSS, including the real-time clock and queue example.
2. Preserve the current release artifact and environment configuration as the
   rollback point. Although this release has no application database migration,
   use the normal production backup policy before every deployment.
3. Confirm that the v2.1.0 database migration was completed:

   ~~~bash
   php artisan migrate:status
   ~~~

   The `2026_09_07_000000_cleanup_legacy_admin_tables` migration must already
   be marked as `Ran`. If it is pending, stop: the v2.1.0 production upgrade is
   incomplete and its database procedure must be reviewed before continuing.
4. Update the production environment before building assets because `VITE_*`
   values are compiled into the frontend bundle:

   ~~~dotenv
   APP_VERSION=2.2.0
   VITE_REDIS_PREFIX=
   ~~~

   If `MIX_REDIS_PREFIX` previously had a value, copy that value to
   `VITE_REDIS_PREFIX`. Remove the old variable after the deployment is
   verified.
5. Deploy the immutable `v2.2.0` tag, preserve the existing `APP_KEY`, and
   install locked dependencies:

   ~~~bash
   git fetch --tags origin
   git checkout v2.2.0
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   npm ci
   npm run build
   ~~~

6. Confirm that `public/build/manifest.json` exists in the release artifact.
   Do not reuse `public/mix-manifest.json`, `public/css`, or `public/js` assets
   from v2.1.0.
7. Check for unexpected database changes:

   ~~~bash
   php artisan migrate:status
   ~~~

   v2.2.0 is expected to contain no pending application migrations. Stop and
   investigate instead of running an unexpected migration in production.
8. Clear and rebuild application caches, then restart long-running workers:

   ~~~bash
   php artisan optimize:clear
   php artisan filament:optimize
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan queue:restart
   php artisan horizon:terminate
   ~~~

9. Bring the application online and verify:

   - `/up` reports healthy.
   - `/admin` loads without missing-manifest or asset errors.
   - Public pages render Bootstrap styling correctly.
   - Vue components mount and update successfully.
   - The browser console and network panel contain no asset or JavaScript
     errors.
   - Real-time events work with the deployed Socket.IO server and Redis prefix.
   - Queues, scheduled tasks, Horizon, and Telescope remain healthy.

### Private frontend code

Deployments with private frontend extensions must migrate them before upgrading:

- Replace the global Vue 2 constructor with Vue 3 `createApp()` and register
  components on the application instance.
- Replace private code that relies on the removed `window.Vue`, `window.$`,
  `window.jQuery`, or `window._` globals with explicit imports.
- Replace CommonJS `require()` calls and `process.env.MIX_*` references with ES
  module imports and `import.meta.env.VITE_*` variables.
- Replace Bootstrap 4 `data-toggle` and `data-target` attributes with Bootstrap
  5 `data-bs-toggle` and `data-bs-target`; remove dependencies on Bootstrap's
  jQuery plugins.
- Check renamed or removed Bootstrap utilities and any third-party Vue 2-only
  components.
- Update Blade templates from `mix()` or hard-coded `css/app.css` and
  `js/app.js` paths to the `@vite` directive.

### Rollback

No application database schema is changed by v2.2.0, so rollback consists of
restoring the v2.1.0 code and its matching frontend assets:

~~~bash
git checkout v2.1.0
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run prod
test -f public/mix-manifest.json
test -f public/css/app.css
test -f public/js/app.js
php artisan optimize:clear
php artisan queue:restart
php artisan horizon:terminate
~~~

Restore the v2.1.0 `MIX_REDIS_PREFIX` setting and remove stale Vite assets if
deployment tooling does not replace the release directory atomically. Do not
roll back by mixing v2.1.0 code with a v2.2.0 `public/build` directory.

### Release checklist

- [ ] Staging deployment and frontend smoke tests completed.
- [ ] Private Vue and Bootstrap integrations checked for compatibility.
- [ ] Node.js 20 used for the locked frontend dependency installation.
- [ ] v2.1.0 legacy administration cleanup migration already marked as run.
- [ ] `APP_VERSION=2.2.0` configured.
- [ ] `MIX_REDIS_PREFIX` migrated to `VITE_REDIS_PREFIX` when applicable.
- [ ] `npm ci` and `npm run build` completed successfully.
- [ ] `public/build/manifest.json` included in the deployment artifact.
- [ ] Admin and public pages load without browser or asset errors.
- [ ] Real-time events verified against the production Socket.IO server.
- [ ] Rollback artifact and owner recorded.

---

## v2.0.0 to v2.1.0

This guide covers the complete production upgrade from the v2.0.0 release
(the previous master baseline) to v2.1.0 (the 2.x release line).

> **Important:** v2.1.0 contains breaking framework, runtime, administration,
> authentication, configuration, and database changes. Treat this as a planned
> production migration, not a routine dependency update.

The file-level source comparison is available at:
https://github.com/haoyuqi/laravel-backend-lab/compare/v2.0.0...v2.1.0

### Supported upgrade path

- Start from a working v2.0.0 deployment.
- Upgrade directly to the released v2.1.0 tag in production.
- Test the same application and database snapshot in staging first.
- The legacy admin and Filament admin must use the same default database
  connection. The migration command reads the legacy **admin_users** table from
  that connection.
- A custom legacy admin table or separate legacy admin database is not supported
  by the automated command and requires a separate migration plan.

### Breaking changes at a glance

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

### Complete change inventory

#### Runtime and framework

- PHP is upgraded from 8.0.2+ to 8.3+.
- Laravel is upgraded from 9.x to 13.x.
- Production frontend builds require Node.js 20 and npm.
- The CI database matrix covers SQLite, MySQL 8.0 and 8.4, and PostgreSQL 17
  and 18. Production should use a currently supported database version.
- Redis 7 is recommended for cache, sessions, and queues.
- Laravel's health endpoint is available at **/up**.

#### Application structure

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

#### Administration and database behavior

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

#### Packages

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

#### Routes and user-facing behavior

- Filament administration is served from **/admin**.
- The temporary **/filament** path is no longer available.
- Legacy Laravel Admin routes are removed.
- The public application routes remain, but controller declarations use modern
  class-based route syntax.
- The old Laravel UI package and its generated authentication controllers are
  removed. Any private code that used them must be migrated separately.
- Horizon remains available at **/horizon** and Telescope at **/telescope**,
  subject to their authorization gates.

#### Localization

- Language files move from **resources/lang** to the root **lang** directory.
- Chinese translations are updated through **laravel-lang/common**.
- English validation, pagination, password, and HTTP status translations are
  included.
- Move any deployment-specific translation overrides to the new directory and
  compare them against the new vendor translations.

#### Operational and data-compatibility changes

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

#### Environment and configuration

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

#### Build, CI, and tests

- Code style is checked with Laravel Pint.
- Application tests run against SQLite, MySQL, and PostgreSQL.
- Browser tests run with Node.js 20 and Laravel Dusk.
- Laravel Mix remains the asset builder. A repository script applies the
  compatibility adjustment required by the current webpack dependency graph.
- The production asset command is **npm run prod**, not **npm run build**.

### Production upgrade procedure

#### 1. Rehearse with a production database snapshot

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

#### 2. Back up the current release and database

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

#### 3. Enable maintenance mode

~~~bash
php artisan down --retry=60
~~~

Stop or drain queue workers and prevent the scheduler from starting new work
during the database migration window.

#### 4. Deploy the v2.1.0 release tag

Deploy the immutable release tag rather than a moving branch:

~~~bash
git fetch --tags origin
git checkout v2.1.0
~~~

The historical **2.x** release branch has been retired. For current releases,
validate changes through pull requests to `master`, then deploy the final tag.

#### 5. Update production environment configuration

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

#### 6. Install application dependencies and build assets

~~~bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run prod
php artisan optimize:clear
~~~

Confirm that Composer scripts and the production webpack build finish
successfully before modifying the database.

#### 7. Migrate legacy administrator accounts

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

#### 8. Run database migrations

~~~bash
php artisan migrate --force
~~~

This is the irreversible point that removes the legacy administration tables.
If the migration refuses to drop them, stop and resolve the remaining
**admin_users** rows; do not bypass the guard manually.

#### 9. Rebuild caches and restart workers

~~~bash
php artisan filament:optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan horizon:terminate
~~~

Restart the deployment's process manager so workers return on v2.1.0 code.

#### 10. Bring the application online

~~~bash
php artisan up
~~~

### Post-upgrade verification

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

### Rollback procedure

#### Before running admin:migrate-users

A code rollback is sufficient if no database-changing command has run:

~~~bash
git checkout v2.0.0
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run prod
php artisan optimize:clear
php artisan up
~~~

#### After admin:migrate-users starts

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

### Release checklist

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
