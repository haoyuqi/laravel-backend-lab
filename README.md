# Laravel Backend Lab

<p align="center">
  A practical Laravel application for exploring modern backend architecture,<br>
  administration workflows, queues, observability, and automated operations.
</p>

<p align="center">
  <a href="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml"><img alt="Tests" src="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml/badge.svg?branch=master"></a>
  <a href="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml?query=branch%3Amaster"><img alt="Coverage" src="https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fhaoyuqi%2Flaravel-backend-lab%2Fcoverage-badge%2Fcoverage.json"></a>
  <a href="https://www.php.net/"><img alt="PHP 8.4 or later" src="https://img.shields.io/badge/PHP-%5E8.4-777BB4?logo=php&logoColor=white"></a>
  <a href="https://laravel.com/"><img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white"></a>
  <a href="https://filamentphp.com/"><img alt="Filament 5" src="https://img.shields.io/badge/Filament-5.x-FDAE4B"></a>
  <a href="LICENSE"><img alt="MIT License" src="https://img.shields.io/github/license/haoyuqi/laravel-backend-lab"></a>
</p>

<p align="center">
  English | <a href="README.zh-CN.md">Simplified Chinese</a>
</p>

## About

Laravel Backend Lab is a reference application and learning environment built
around a production-style Laravel backend. It brings together an administrative
panel, visitor analytics, queues, scheduled maintenance, backups, and
observability in one codebase.

This repository is an application, not a reusable Laravel package or a generic
project starter.

> This project is developed with AI assistance and maintained under human review.

## Highlights

- Filament admin panel at `/admin`, with visitor and blacklist management.
- Dashboard widgets for traffic statistics, application metadata, and service
  health.
- Visitor recording, IP geolocation, and blacklist enforcement.
- Configurable queue processing with optional Redis monitoring through Laravel
  Horizon.
- Application inspection through Laravel Telescope and Debugbar.
- Scheduled backups, retention cleanup, visit aggregation, and Bing wallpaper
  downloads.
- Vite-powered Vue 3 and Bootstrap 5 frontend assets.
- PHPUnit feature and unit tests with an isolated testing connection, plus
  end-to-end browser coverage with Playwright.

The application health endpoint is available at `/up`.

### Admin operation logs

The admin panel's **系统 → 操作日志** page records backend data changes and
successful backend login/logout. All current write entry points are covered:
blacklist creation, editing, deletion (including bulk deletion), and visitor
bulk additions/restores. The recorder supports different resource types and
string/UUID record IDs; logs contain the operator's ID/name snapshot, action,
entry point, operation ID, safe before/after state, and time.
Reads, searches, exports, failed/denied logins, unchanged actions and direct
model writes from jobs or CLI are not recorded. Request bodies, cookies,
credentials, and user emails are not captured.

New resource pages must extend the common `App\Filament\Pages` create/edit/list
pages. Model fields and stable resource aliases are explicitly configured in
`config/admin-operation-logs.php`; unconfigured business model writes in an
audited operation fail before persistence. Safe snapshots are bounded and
recursively exclude sensitive fields. Infrastructure models are excluded;
making one writable in the admin requires removing its exclusion and adding
its safe policy. Custom write methods and bulk callbacks must use
`AdminOperationRecorder::run()` with the business connection. Query-builder,
pivot and raw SQL mutations bypass model events and must use the recorder's
explicit locked before/write/after interface; arbitrary SQL cannot be
automatically audited by Eloquent. Test-only User CRUD demonstrates reuse
without adding production user-management permissions.

For relationship writes, call `explicit($subject, 'update', $mutation, $snapshot)`:
the snapshot callback reads deterministic persisted relationship state while the
parent row is locked. Allow its top-level fields in the model policy's
`relationship_fields`; nested secrets and oversized snapshots are rejected or
filtered. Attach, detach and pivot updates then compare that state, including
no-op detection. All writers must use the same connection and parent-row lock.
Use the callback's supplied model for Eloquent writes to avoid duplicate logs.

Each change and its log use one database transaction: if logging fails, that
record's change rolls back. Bulk actions remain per-record; earlier successful
records remain committed if a later record fails. The page is read-only and
uses the existing admin access policy. Ordinary Eloquent instance updates/deletes
are rejected, but writes that bypass model events (including bulk queries and
quiet writes), raw SQL, and database administrators can modify history; this is not a
tamper-proof audit system. Redis cache invalidation is retried after commit;
database and Redis changes are not a distributed transaction.

Backend login is logged only after the authentication method succeeds. If its
log cannot be saved, the new authentication is revoked and login returns an
error. Logout always invalidates the local session; if audit storage fails,
logout still succeeds with a warning, and that logout log may be missing.
Session/cookie state and the database are not one atomic transaction.

Logs contain personal data (operator names and IPs). They are retained until
explicitly pruned; choose a retention policy appropriate to your deployment
and include this table in backups. Preview pruning first:

```bash
php artisan admin-operation-logs:prune --days=365
```

After reviewing the count and backing up history, add `--force` to delete old
logs in batches of 1,000. The minimum retention is 30 days; no automatic prune
schedule is configured. See [UPGRADE.md](UPGRADE.md) before deploying.

## Technology Stack

| Component | Version | Purpose |
| --- | --- | --- |
| [PHP](https://www.php.net/) | 8.4+ | Application runtime |
| [Laravel](https://laravel.com/) | 13.x | Web application framework |
| [Filament](https://filamentphp.com/) | 5.x | Administration panel |
| [Laravel Horizon](https://laravel.com/docs/horizon) | 5.x | Redis queue monitoring |
| [Laravel Telescope](https://laravel.com/docs/telescope) | 5.x | Local application inspection |
| [Laravel Lang](https://laravel-lang.com/) | 6.x | Application translations |
| [Laravel Backup](https://github.com/spatie/laravel-backup) | 10.x | Database and file backups |
| [Vite](https://vite.dev/) | 6.x | Frontend development and builds |
| [Vue](https://vuejs.org/) | 3.x | Interactive frontend components |
| [Bootstrap](https://getbootstrap.com/) | 5.x | Frontend styling |

## Requirements

The application requires:

- PHP 8.4 or later, with the extensions required by `composer.lock`;
- Composer 2;
- Node.js 24 LTS and npm;
- SQLite, MySQL, or PostgreSQL for persistent application data;
- Redis for visitor statistics and related scheduled jobs;
- a web server supported by Laravel in production.

Laravel-managed cache, sessions, queues, and broadcasting do not have to use
Redis. However, Redis itself is required by the current application because
visitor counting, dashboard statistics, and maintenance
commands access it directly. Making Redis entirely optional requires an
application code change; changing only `.env` is not sufficient.

## Services and Configuration

| Service | Required | Configuration and alternatives |
| --- | --- | --- |
| Database | Yes | Set `DB_CONNECTION` and the matching `DB_*` values. SQLite, MySQL, and PostgreSQL are supported. |
| Redis | Yes | Used directly by visitor statistics, dashboard widgets, and scheduled cleanup. Blacklist enforcement reads the database so stale cache data cannot change a decision. The host, port, credentials, client, and database indexes are configurable through `REDIS_*`. |
| Queue worker | No | Required only with an asynchronous queue connection. The `sync` driver handles jobs in the request process. |
| Horizon | No | Requires both Redis and a Redis queue connection. It is not needed with `sync` or a non-Redis queue. |
| Scheduler | Feature-dependent | Run `php artisan schedule:run` every minute to enable scheduled backups, cleanup, statistics, and maintenance tasks. |
| Mail server | No | Use `MAIL_MAILER=log` during development, or configure SMTP for outgoing mail. |
| Object storage | No | The default local filesystem works without S3. Configure `FILESYSTEM_DRIVER`, `FILESYSTEM_CLOUD`, and `AWS_*` only when external storage is needed. |
| Real-time server | No | Keep `BROADCAST_CONNECTION=log` and `REVERB_APP_KEY` empty when real-time events are disabled. Enable Laravel Reverb for live time updates; no Redis broadcasting server is needed. |
| Telescope and Debugbar | No | Set `TELESCOPE_ENABLED=false` and `DEBUGBAR_ENABLED=false` when these inspection tools should not collect data or expose their interfaces. |

Important environment options include:

- `APP_URL` for the public application URL;
- `ADMIN_EMAILS` for the comma-separated production administrator allowlist;
- `DB_*` for the selected database;
- `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, and `REDIS_PORT` for Redis;
- `CACHE_STORE`, `SESSION_DRIVER`, and `QUEUE_CONNECTION` for state and job
  storage;
- `BROADCAST_CONNECTION`, `REVERB_*`, and `VITE_REVERB_*` for real-time events;
- `MAIL_MAILER` and `MAIL_*` for outgoing mail;
- `FILESYSTEM_DRIVER`, `FILESYSTEM_CLOUD`, and `AWS_*` for file storage;
- `TELESCOPE_ENABLED` and `DEBUGBAR_ENABLED` for optional inspection tools;
- `TEST_DB_*` for an isolated non-default test database.

The queue and sorting demos are disabled by default. Set
`DEMO_ROUTES_ENABLED=true` only when exposing them intentionally. They share a
limit of 10 requests per minute per IP and accept at most 1,000 items. Creating
a demo job uses `POST /queue/create` and requires the normal web CSRF token.

Behind a reverse proxy, set `TRUSTED_PROXIES` to a comma-separated list of its
addresses or CIDR ranges. Only list proxies controlled by your deployment;
forwarded client headers from other addresses are ignored.

Backups use the connection selected by `DB_CONNECTION`. Configure
`BACKUP_NOTIFICATION_EMAIL` with a single valid recipient and configure the mail
transport to enable backup failure notifications. Backups remain local unless
you change the backup disk; periodically restore a backup into an isolated
database to verify recovery. Schedule locks prevent overlapping instances of
the same command; multi-server deployments must use a shared lock store and
run the scheduler on one designated server.

New visitor and blocked-request logs retain the request path without query
strings or fragments, with a maximum of 2,048 bytes. Historical logs are not
rewritten automatically. Telescope redacts Livewire component request and
response payloads, including in local environments.

## Installation

Clone the application and install its locked dependencies:

```bash
git clone git@github.com:haoyuqi/laravel-backend-lab.git
cd laravel-backend-lab
cp .env.example .env
composer install --prefer-dist --no-interaction
npm ci
npm run build
```

Choose a database and update `.env` before running migrations. For example,
MySQL can be configured with:

```dotenv
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```

Redis does not need to store Laravel's cache, sessions, queues, or broadcasts.
To use it only for the application features that currently require it, set:

```dotenv
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=log
```

These settings reduce Redis usage but do not eliminate the direct Redis calls
made by visitor statistics features.

Complete the application setup:

```bash
php artisan key:generate
php artisan storage:link
php artisan migrate
php artisan make:filament-user
```

Configure the web server to use the project's `public` directory as its
document root. For local evaluation, `php artisan serve` can start Laravel's
development server. Sign in to `/admin` with the Filament user created above.

In production, `ADMIN_EMAILS` must contain a comma-separated allowlist of
administrator email addresses. An empty allowlist denies all panel access in
production.

## Development

Run the Vite development server:

```bash
npm run dev
```

Create an optimized frontend build with `npm run build`. Do not use the removed
Laravel Mix commands from earlier releases.

For an existing installation, follow [UPGRADE.md](UPGRADE.md) before deploying
v3.0.2. This patch removes unused Laravel Admin assets and adds no database
migration. Copy-based deployments must also remove the old
`public/vendor/laravel-admin/` directory and purge cached copies. Set
`APP_VERSION=3.0.2` in existing environments after upgrading. Earlier
installations must also complete the applicable v3.0.0 and v3.0.1 upgrade
procedures, including their required database migrations.

### Local real-time updates

The `/time` page uses [Laravel Reverb](https://laravel.com/docs/13.x/reverb)
and Laravel Echo. The application still works without a WebSocket server.
To enable live updates, generate local credentials:

```bash
php artisan reverb:install
```

Set `BROADCAST_CONNECTION=reverb`, `REVERB_HOST=localhost`,
`REVERB_PORT=8080`, and `REVERB_SCHEME=http`. Keep
`REVERB_SCALING_ENABLED=false` for a single server. The `VITE_REVERB_*`
variables in `.env.example` mirror these values. Never put
`REVERB_APP_SECRET` in a `VITE_*` variable. Rebuild with `npm run build`
after changing frontend connection settings.

Run these commands in separate terminals:

```bash
php artisan reverb:start
php artisan schedule:work
# Only needed with an asynchronous QUEUE_CONNECTION:
php artisan queue:work --queue=push-time
```

Open `/time` over HTTP and wait for the next minute: one new row should arrive
without refreshing. `REVERB_SERVER_HOST` and `REVERB_SERVER_PORT` control the
listening socket; `REVERB_HOST` and `REVERB_PORT` tell Laravel where to send
events. `VITE_REVERB_HOST` must be reachable from the browser; when using
containers it may differ from the server-side host.

`npm run test:reverb` starts isolated application and Reverb processes on
ports 8000 and 8080, builds test assets, and verifies real WebSocket delivery
and resubscription in Chromium. Stop other processes using those ports first.
It uses an in-memory database and ephemeral Reverb credentials. Rebuild normal
assets afterwards with `npm run build`.

## Testing

The default test suite uses a dedicated `testing` connection backed by an
in-memory SQLite database. It does not use the primary development database.

The coverage badge above updates after each successful default-branch CI run.
It reports SQLite PHPUnit line coverage for `app/` (required minimum: 85%).

GitHub Actions runs the test suite for pull requests and pushes to `master`.
The daily run is scheduled for 03:46 Beijing time on the default branch
(`master`). Each run's summary shows measured test and coverage results,
installed versions, and the databases actually tested.

```bash
php artisan test
./vendor/bin/pint --test
```

To exercise the suite against MySQL or PostgreSQL, provide the dedicated
`TEST_DB_*` variables. The test database name must contain only letters,
numbers, and underscores, end in `_test`, and differ from the application's
database. Laravel creates it on the first test run if it does not exist; the
database server must already be running and the configured account must have
permission to create databases. The `testing` connection reads `TEST_DB_*`
only; it does not reuse the application's `DB_*` credentials. Use a dedicated
test account with database-creation permission when possible. CI uses its
short-lived database service account to exercise this path:

```bash
TEST_DB_CONNECTION=mysql \
TEST_DB_HOST=127.0.0.1 \
TEST_DB_PORT=3306 \
TEST_DB_DATABASE=laravel_test \
TEST_DB_USERNAME=root \
TEST_DB_PASSWORD='' \
php artisan test
```

Use `TEST_DB_CONNECTION=pgsql`, port `5432`, and the PostgreSQL service's
credentials to run the same tests against PostgreSQL. Inside Laradock's
`workspace` container, set `TEST_DB_HOST=postgres`. A fresh clone defaults to
in-memory SQLite and needs no database-server provisioning; normal application
setup (dependencies, an application key, built assets, and Redis for tests that
use it) still applies.

To run the Playwright end-to-end browser tests across desktop and mobile
browsers against an isolated test database:

```bash
php artisan migrate --database=testing
php artisan db:seed --class=E2EDataSeeder --database=testing
npm run test:e2e
```

## Operations

Production deployments should run the Laravel scheduler every minute when its
scheduled features are enabled. Keep queue workers under a process supervisor
when using an asynchronous queue connection. Horizon is appropriate only for a
Redis-backed queue. The scheduler performs backups, cleanup, visit aggregation,
Telescope pruning, GeoIP maintenance, and Bing wallpaper downloads.

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening an issue or pull
request. It defines the branch naming, commit message, testing, and target branch
conventions used by this repository.

## License

Laravel Backend Lab is open-source software licensed under the
[MIT license](LICENSE).

## Acknowledgements

Thanks to [JetBrains](https://www.jetbrains.com/) for supporting the project.
