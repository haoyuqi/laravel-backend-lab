# Container images

The application is published as a single production image that serves HTTP by
default and can run the queue worker, scheduler, and Reverb from the same image.
Compose deployment is tracked separately in issue #190 and is not part of this
image.

## Published images

```text
ghcr.io/haoyuqi/laravel-backend-lab:3.0.9   # immutable release
ghcr.io/haoyuqi/laravel-backend-lab:3.0     # moving alias
ghcr.io/haoyuqi/laravel-backend-lab:3       # moving alias
ghcr.io/haoyuqi/laravel-backend-lab:latest  # newest stable release
```

- Full `X.Y.Z` tags are immutable and are never rebuilt or overwritten.
- `X.Y`, `X`, and `latest` move forward only; a re-run of an older release
  cannot roll them back.
- Images are published for `linux/amd64` and `linux/arm64`. Release notes list
  the multi-architecture index digest and both platform digests.
- The image contains no credentials. `APP_KEY`, database, Redis, mail, and
  Reverb credentials are supplied at runtime.
- The image defaults to `TELESCOPE_ENABLED=false` and `DEBUGBAR_ENABLED=false`.

## Requirements

- A container runtime (Docker, Podman with Docker compatibility, or Kubernetes).
- A supported database: SQLite, MySQL 8.0/8.4, or PostgreSQL 17/18.
- Redis. The application queries Redis directly for visitor statistics,
  dashboard widgets, and scheduled maintenance.
- Persistent storage for `storage/` (see below).

The image contains PHP 8.4, nginx, php-fpm, and the database clients needed by
scheduled backups (`mysqldump`, `pg_dump`, `sqlite3`). A host PHP or Node.js
installation is not required.

## Quick start

The image never generates an application key, runs migrations, or creates an
administrator on startup. Perform those steps explicitly.

```bash
docker run -d --name backend-lab \
  -p 8080:8080 \
  -v backend-lab-storage:/var/www/html/storage \
  -e APP_KEY="base64:$(openssl rand -base64 32)" \
  -e APP_URL=http://localhost:8080 \
  -e ADMIN_EMAILS=admin@example.com \
  -e DB_CONNECTION=pgsql \
  -e DB_HOST=postgres \
  -e DB_DATABASE=laravel \
  -e DB_USERNAME=laravel \
  -e DB_PASSWORD=secret \
  -e REDIS_HOST=redis \
  ghcr.io/haoyuqi/laravel-backend-lab:latest

docker exec backend-lab php artisan migrate --force
docker exec backend-lab php artisan make:filament-user \
  --name=Admin --email=admin@example.com
```

Open `http://localhost:8080/admin` and sign in. In production, `ADMIN_EMAILS`
must contain the administrator's email address; an empty allowlist denies panel
access.

Update an existing installation with a new image tag and an explicit
`php artisan migrate --force`. Keep the same `APP_KEY` and storage volume.

## Configuration

| Variable | Required | Notes |
| --- | --- | --- |
| `APP_KEY` | Yes | Generate once and keep it stable; never bake it into an image |
| `APP_URL` | Yes | Public URL used for generated links and assets |
| `ADMIN_EMAILS` | Production | Comma-separated allowlist for the panel, Horizon, and Telescope |
| `DB_CONNECTION`, `DB_*` | Yes | `sqlite`, `mysql`, or `pgsql` |
| `REDIS_*` | Yes | Redis host, port, credentials, and client |
| `CACHE_STORE` | No | Defaults to `file`; `redis` is common alongside a worker |
| `SESSION_DRIVER` | No | `file` or `redis` |
| `QUEUE_CONNECTION` | No | `sync` by default; use `redis` with a worker or Horizon |
| `BROADCAST_CONNECTION` | No | `log` disables real-time; `reverb` enables it |
| `REVERB_*` | No | Server-side Reverb settings; see below |
| `REVERB_CLIENT_*` | No | Browser-facing Reverb host, port, and scheme |
| `MAIL_*`, `AWS_*`, `BACKUP_*` | No | Outgoing mail, object storage, and backup settings |
| `TRUSTED_PROXIES` | Behind a proxy | Comma-separated proxy addresses or CIDR ranges |
| `LOG_CHANNEL` | No | The image defaults to `stderr` |

Do not mount a `.env` file into the container. Pass configuration as environment
variables so secrets never end up in an image layer or a backup archive.

## Ports and health

| Endpoint | Purpose |
| --- | --- |
| `8080` | nginx (map 80/443 at your proxy) |
| `/up` | Liveness; does not query the database |
| `/health/ready` | Readiness; requires the database and reports Redis as `degraded` with HTTP 200 |

The image healthcheck requests `/up` every 30 seconds. Queue, scheduler, and
Horizon containers are not HTTP servers, so disable the inherited healthcheck
for them (`docker run --no-healthcheck` or `healthcheck: disable: true`). A
Reverb container can keep the healthcheck only when it listens on port 8080.

## Persistent files

Mount a volume at `/var/www/html/storage` and keep `bootstrap/cache` inside the
container.

| Path | Contents |
| --- | --- |
| `storage/app` | Application files and uploads |
| `storage/app/public` | Public files served through `public/storage` |
| `storage/backup` | Spatie backup archives |
| `storage/bing-wallpaper` | Downloaded wallpapers |
| `storage/logs` | Log files when `LOG_CHANNEL` is not `stderr` |
| `storage/framework` | Cache, sessions, and compiled views |

The container runs as `www-data` with uid/gid `82`. New named volumes inherit
that ownership from the image. Bind mounts must be writable by uid 82, for
example:

```bash
chown -R 82:82 ./storage
```

## Background services

Run each role with the same image and a command override:

```bash
# Queue worker (includes the push-time queue used by the /time page)
php artisan queue:work --queue=queue-test,record-visitor,black-list-log,push-time

# Scheduler (runs backups, cleanup, statistics, and browser wallpaper jobs)
php artisan schedule:work

# Horizon (requires Redis and an asynchronous queue)
php artisan horizon

# Reverb
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Scheduled backups require the database clients included in the image. If you use
a database server newer than the client major version, upgrade the corresponding
client package and rebuild the image; `pg_dump` and `mysqldump` refuse to dump a
newer server.

## Real-time updates

The `/time` page still renders its initial value without Reverb. To enable live
updates:

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-public-key
REVERB_APP_SECRET=your-secret
REVERB_HOST=reverb                 # internal host used by Laravel
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_CLIENT_HOST=localhost       # host the browser connects to
REVERB_CLIENT_PORT=8080
REVERB_CLIENT_SCHEME=http
```

`REVERB_*` is used by the application to publish events. `REVERB_CLIENT_*` is
rendered into `/time` at request time and must be reachable from the browser;
it defaults to `REVERB_*` when omitted. The application secret is never
rendered. If Reverb is behind TLS, terminate it at the proxy and include the
WebSocket upgrade headers.

The Reverb application currently allows all origins. Restrict
`allowed_origins` in `config/reverb.php` for public deployments.

## Logs and cached configuration

The image logs nginx and php-fpm to stdout/stderr. Laravel logs to `stderr` by
default (`LOG_CHANNEL=stderr`); set `LOG_CHANNEL=stack` to keep files under
`storage/logs`.

The image does not cache configuration at build time. If you want Laravel's
runtime caches, run them explicitly after configuration is final:

```bash
docker exec backend-lab php artisan optimize
```

`bootstrap/cache` is container-local, so run the command in every container that
needs the cache. Do not share that directory between containers.

## Backups

Backups run daily at 01:00, cleanup at 01:10, and monitoring at 01:20 in the
scheduler container. Archives are written to `storage/backup`, so that directory
must live on the persistent volume. Backups stay local unless you change the
backup disk in `config/backup.php`.

Set `BACKUP_NOTIFICATION_EMAIL` and a working mail transport to receive failure
notifications. Successful backups are not emailed.

Before relying on backups, restore one into an isolated database and verify it.

## Building locally

```bash
docker build --build-arg APP_VERSION=0.0.0 \
  --build-arg VCS_REF="$(git rev-parse HEAD)" \
  -t laravel-backend-lab:local .
```

The build needs network access for Composer, npm, and Alpine packages. It runs
`composer install --no-dev` from `composer.lock`, builds Vite assets with Node
24, and publishes Filament assets. Composer is not part of the runtime image.
