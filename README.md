# Laravel Backend Lab

<p align="center">
  A practical Laravel application for exploring modern backend architecture,<br>
  administration workflows, queues, observability, and automated operations.
</p>

<p align="center">
  <a href="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml"><img alt="Tests" src="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml/badge.svg?branch=master"></a>
  <a href="https://www.php.net/"><img alt="PHP 8.3 or later" src="https://img.shields.io/badge/PHP-%5E8.3-777BB4?logo=php&logoColor=white"></a>
  <a href="https://laravel.com/"><img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white"></a>
  <a href="https://filamentphp.com/"><img alt="Filament 3" src="https://img.shields.io/badge/Filament-3.x-FDAE4B"></a>
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
  browser coverage with Laravel Dusk.

The application health endpoint is available at `/up`.

## Technology Stack

| Component | Version | Purpose |
| --- | --- | --- |
| [PHP](https://www.php.net/) | 8.3+ | Application runtime |
| [Laravel](https://laravel.com/) | 13.x | Web application framework |
| [Filament](https://filamentphp.com/) | 3.x | Administration panel |
| [Laravel Horizon](https://laravel.com/docs/horizon) | 5.x | Redis queue monitoring |
| [Laravel Telescope](https://laravel.com/docs/telescope) | 5.x | Local application inspection |
| [Laravel Lang](https://laravel-lang.com/) | 6.x | Application translations |
| [Laravel Backup](https://github.com/spatie/laravel-backup) | 10.x | Database and file backups |
| [Vite](https://vite.dev/) | 6.x | Frontend development and builds |
| [Vue](https://vuejs.org/) | 3.x | Interactive frontend components |
| [Bootstrap](https://getbootstrap.com/) | 5.x | Frontend styling |

## Requirements

The application requires:

- PHP 8.3 or later, with the extensions required by `composer.lock`;
- Composer 2;
- Node.js 20 and npm;
- SQLite, MySQL, or PostgreSQL for persistent application data;
- Redis for visitor statistics, blacklist caching, and related scheduled jobs;
- a web server supported by Laravel in production.

Laravel-managed cache, sessions, queues, and broadcasting do not have to use
Redis. However, Redis itself is required by the current application because
visitor counting, blacklist checks, dashboard statistics, and maintenance
commands access it directly. Making Redis entirely optional requires an
application code change; changing only `.env` is not sufficient.

## Services and Configuration

| Service | Required | Configuration and alternatives |
| --- | --- | --- |
| Database | Yes | Set `DB_CONNECTION` and the matching `DB_*` values. SQLite, MySQL, and PostgreSQL are supported. |
| Redis | Yes | Used directly by visitor statistics, blacklist checks, dashboard widgets, and scheduled cleanup. The host, port, credentials, client, and database indexes are configurable through `REDIS_*`. |
| Queue worker | No | Required only with an asynchronous queue connection. The `sync` driver handles jobs in the request process. |
| Horizon | No | Requires both Redis and a Redis queue connection. It is not needed with `sync` or a non-Redis queue. |
| Scheduler | Feature-dependent | Run `php artisan schedule:run` every minute to enable scheduled backups, cleanup, statistics, and maintenance tasks. |
| Mail server | No | Use `MAIL_MAILER=log` during development, or configure SMTP for outgoing mail. |
| Object storage | No | The default local filesystem works without S3. Configure `FILESYSTEM_DRIVER`, `FILESYSTEM_CLOUD`, and `AWS_*` only when external storage is needed. |
| Real-time server | No | Keep `BROADCAST_CONNECTION=log` when real-time events are disabled. The included Echo integration needs Redis broadcasting, a compatible Socket.IO server, and the matching `VITE_REDIS_PREFIX`. |
| Telescope and Debugbar | No | Set `TELESCOPE_ENABLED=false` and `DEBUGBAR_ENABLED=false` when these inspection tools should not collect data or expose their interfaces. |

Important environment options include:

- `APP_URL` for the public application URL;
- `ADMIN_EMAILS` for the comma-separated production administrator allowlist;
- `DB_*` for the selected database;
- `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, and `REDIS_PORT` for Redis;
- `CACHE_STORE`, `SESSION_DRIVER`, and `QUEUE_CONNECTION` for state and job
  storage;
- `BROADCAST_CONNECTION` and `VITE_REDIS_PREFIX` for real-time events;
- `MAIL_MAILER` and `MAIL_*` for outgoing mail;
- `FILESYSTEM_DRIVER`, `FILESYSTEM_CLOUD`, and `AWS_*` for file storage;
- `TELESCOPE_ENABLED` and `DEBUGBAR_ENABLED` for optional inspection tools;
- `TEST_DB_*` for an isolated non-default test database.

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
made by visitor statistics and blacklist features.

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

For an existing installation, follow [UPGRADE.md](UPGRADE.md) before deploying a
new minor release.

## Testing

The default test suite uses a dedicated `testing` connection backed by an
in-memory SQLite database. It does not use the primary development database.

```bash
php artisan test
./vendor/bin/pint --test
```

To exercise the suite against MySQL or PostgreSQL, provide the dedicated
`TEST_DB_*` variables. The configured account must be allowed to create the
named test database if it does not already exist:

```bash
TEST_DB_CONNECTION=mysql \
TEST_DB_HOST=127.0.0.1 \
TEST_DB_PORT=3306 \
TEST_DB_DATABASE=laravel_test \
TEST_DB_USERNAME=root \
TEST_DB_PASSWORD='' \
php artisan test
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
