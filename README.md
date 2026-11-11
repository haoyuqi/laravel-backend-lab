# Laravel Backend Lab

<p align="center">
  A practical Laravel application for exploring modern backend architecture,<br>
  administration workflows, queues, observability, and automated operations.
</p>

<p align="center">
  <a href="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml"><img alt="Tests" src="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml/badge.svg?branch=master"></a>
  <a href="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml?query=branch%3Amaster"><img alt="Coverage" src="https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com/haoyuqi%2Flaravel-backend-lab%2Fcoverage-badge%2Fcoverage.json"></a>
  <a href="https://www.php.net/"><img alt="PHP 8.4.27+ or 8.5.12+" src="https://img.shields.io/badge/PHP-8.4.27%2B%20%7C%208.5.12%2B-777BB4?logo=php&logoColor=white"></a>
  <a href="https://laravel.com/"><img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white"></a>
  <a href="https://filamentphp.com/"><img alt="Filament 5" src="https://img.shields.io/badge/Filament-5.x-FDAE4B"></a>
  <a href="LICENSE"><img alt="MIT License" src="https://img.shields.io/github/license/haoyuqi/laravel-backend-lab"></a>
</p>

<p align="center">
  English | <a href="README.zh-CN.md">Simplified Chinese</a>
</p>

## About

Laravel Backend Lab is a reference application built around a production-style
Laravel backend: an administration panel, visitor analytics, queues, scheduled
maintenance, backups, and observability in one codebase. It is an application,
not a reusable package or a generic starter kit.

## Requirements

- PHP 8.4.27 or later, or PHP 8.5.12 or later, with the extensions required by
  `composer.lock`;
- Composer 2;
- SQLite, MySQL, or PostgreSQL;
- Redis, used directly by visitor statistics, dashboard widgets, and scheduled
  maintenance;
- Node.js 24 and npm only when building frontend assets from source;
- a web server supported by Laravel in production.

## Installation

### From source

```bash
git clone git@github.com:haoyuqi/laravel-backend-lab.git
cd laravel-backend-lab
cp .env.example .env
composer install --prefer-dist --no-interaction
npm ci
npm run build
```

Choose a database in `.env` before running migrations. For example:

```dotenv
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```

Laravel's cache, sessions, queues, and broadcasts do not have to use Redis, but
the application still calls Redis directly for visitor statistics. See
[configuration](docs/configuration.md) for the available stores and services.

Complete the setup:

```bash
php artisan key:generate
php artisan storage:link
php artisan migrate
php artisan make:filament-user
```

Point the web server at the project's `public` directory, or use
`php artisan serve` for local evaluation, then sign in at `/admin`. In
production, `ADMIN_EMAILS` must list the administrator email addresses; an empty
allowlist denies panel access.

### With Docker

Use the independent [production and development Compose setups](docs/compose.md)
to run all application roles with bundled or external PostgreSQL/Redis services.

A single image for `linux/amd64` and `linux/arm64` is published at
[`ghcr.io/haoyuqi/laravel-backend-lab`](https://github.com/haoyuqi/laravel-backend-lab/pkgs/container/laravel-backend-lab). It serves HTTP by default and can run the
queue worker, scheduler, and Reverb through a command override. Startup never
generates `APP_KEY`, migrates the database, or creates an administrator.

See [container installation](docs/container.md) for the quick start, required
environment variables, and persistent volumes.

## Documentation

| Document | Contents |
| --- | --- |
| [Configuration](docs/configuration.md) | Environment variables, services, and runtime options |
| [Operations](docs/operations.md) | Queue workers, scheduler, logs, and backups |
| [Real-time updates](docs/realtime.md) | Reverb and Echo setup |
| [Testing](docs/testing.md) | PHPUnit, coverage, and browser tests |
| [Architecture](docs/architecture.md) | Feature map and runtime contracts |
| [Code style](docs/code-style.md) | PHP, Filament, and frontend conventions |
| [Upgrades](UPGRADE.md) | Action-required changes between releases |
| [Contributing](CONTRIBUTING.md) | Local build, verification, and pull requests |

## License

Laravel Backend Lab is open-source software licensed under the
[MIT license](LICENSE).

## Acknowledgements

Thanks to [JetBrains](https://www.jetbrains.com/) for supporting the project.
