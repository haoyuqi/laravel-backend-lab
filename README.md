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
observability in one codebase that can be developed consistently with Laradock.

This repository is an application, not a reusable Laravel package or a generic
project starter.

## Highlights

- Filament admin panel at `/admin`, with visitor and blacklist management.
- Dashboard widgets for traffic statistics, application metadata, and service
  health.
- Visitor recording, IP geolocation, and blacklist enforcement.
- Redis-backed queues and monitoring through Laravel Horizon.
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

The supported development environment is a
[Laradock](https://github.com/laradock/laradock) installation with:

- the `workspace`, `php-fpm`, and `nginx` containers;
- PHP 8.3 and Composer 2 in `workspace`;
- Node.js 20 and npm in `workspace`;
- MySQL and Redis services.

The examples below assume the repositories are located at:

```text
~/Developer/laradock
~/Developer/www/laravel-backend-lab
```

Laradock mounts `~/Developer/www` at `/var/www`, so the project is available
inside `workspace` as `/var/www/laravel-backend-lab`.

## Installation

Clone the application on the host:

```bash
cd ~/Developer/www
git clone git@github.com:haoyuqi/laravel-backend-lab.git
cd laravel-backend-lab
cp .env.example .env
```

Start the required Laradock services:

```bash
cd ~/Developer/laradock
docker compose up -d nginx mysql redis workspace
```

Install the PHP and JavaScript dependencies from the `workspace` container:

```bash
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace composer install --prefer-dist --no-interaction
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace npm ci
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace npm run build
```

Update `.env` for the Laradock network before running migrations. At minimum,
review these values:

```dotenv
APP_URL=http://laravel-backend-lab.test

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=root

REDIS_HOST=redis
```

The database name and credentials must match your Laradock configuration.
Complete the application setup in `workspace`:

```bash
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace php artisan key:generate
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace php artisan storage:link
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace php artisan migrate
docker compose exec -it -u laradock -w /var/www/laravel-backend-lab workspace php artisan make:filament-user
```

Add the local domain to Laradock's nginx sites and your hosts file, then open
the application URL. Sign in to the admin panel at `/admin` with the Filament
user created above.

In production, `ADMIN_EMAILS` must contain a comma-separated allowlist of
administrator email addresses. An empty allowlist denies all panel access in
production.

## Development

Run the Vite development server from `workspace`:

```bash
cd ~/Developer/laradock
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace npm run dev
```

Create an optimized frontend build with `npm run build`. Do not use the removed
Laravel Mix commands from earlier releases.

For an existing installation, follow [UPGRADE.md](UPGRADE.md) before deploying a
new minor release.

## Testing

The default test suite uses a dedicated `testing` connection backed by an
in-memory SQLite database. It does not use the primary development database.

```bash
cd ~/Developer/laradock
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace php artisan test
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace ./vendor/bin/pint --test
```

To exercise the suite against MySQL or PostgreSQL, provide the dedicated
`TEST_DB_*` variables. The configured account must be allowed to create the
named test database if it does not already exist:

```bash
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab \
  -e TEST_DB_CONNECTION=mysql \
  -e TEST_DB_HOST=mysql \
  -e TEST_DB_PORT=3306 \
  -e TEST_DB_DATABASE=laravel_test \
  -e TEST_DB_USERNAME=root \
  -e TEST_DB_PASSWORD=root \
  workspace php artisan test
```

## Operations

Production deployments should run the Laravel scheduler every minute and keep
the queue worker or Horizon under a process supervisor. The scheduler performs
backups, cleanup, visit aggregation, Telescope pruning, GeoIP maintenance, and
Bing wallpaper downloads. Configure the Laradock `php-worker`,
`laravel-horizon`, and `laravel-echo-server` services when those features are
enabled.

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening an issue or pull
request. It defines the branch naming, commit message, testing, and target branch
conventions used by this repository.

## License

Laravel Backend Lab is open-source software licensed under the
[MIT license](LICENSE).

## Acknowledgements

Thanks to [JetBrains](https://www.jetbrains.com/) for supporting the project.
