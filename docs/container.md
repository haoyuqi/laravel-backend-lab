# Container installation

A single production image is published at
`ghcr.io/haoyuqi/laravel-backend-lab` for `linux/amd64` and `linux/arm64`. It
serves HTTP by default and can run the queue worker, scheduler, and Reverb
through a command override.

For complete service orchestration, see [Compose deployment](compose.md):
independent development/production definitions, optional bundled dependencies,
explicit initialization, persistence, upgrade, and rollback.

## Published images

```text
ghcr.io/haoyuqi/laravel-backend-lab:3.1.0   # example immutable release
ghcr.io/haoyuqi/laravel-backend-lab:3.1     # moving alias
ghcr.io/haoyuqi/laravel-backend-lab:3       # moving alias
ghcr.io/haoyuqi/laravel-backend-lab:latest  # newest stable release
```

Full `X.Y.Z` tags are never rebuilt or overwritten. `X.Y`, `X`, and `latest`
move forward only. Release notes list the multi-architecture index digest and
both platform digests.

Package page:
<https://github.com/haoyuqi/laravel-backend-lab/pkgs/container/laravel-backend-lab>

The package must be public for anonymous pulls. Make it public once after the
first publish in the repository's package settings.

## Requirements

- A container runtime.
- A supported database: SQLite, MySQL 8.0/8.4, or PostgreSQL 17/18.
- Redis. The application queries it directly for visitor statistics, dashboard
  widgets, and scheduled maintenance.
- Network access to the database and Redis from the container.

The image contains PHP 8.4, nginx, php-fpm, and the database clients used by
scheduled backups. A host PHP or Node.js installation is not required.

## Quick start

The image never generates `APP_KEY`, runs migrations, or creates an administrator
on startup. Run those steps explicitly. Replace `you@example.com` with the
operator address you will pass to `make:filament-user`. The example below
publishes the port on loopback only and serves plain HTTP; set
`SESSION_SECURE_COOKIE=true` once a TLS proxy terminates HTTPS for the public
hostname.

```bash
docker run -d --name backend-lab \
  -p 127.0.0.1:8080:8080 \
  -v backend-lab-storage:/var/www/html/storage \
  -e APP_KEY="base64:$(openssl rand -base64 32)" \
  -e APP_URL=http://localhost:8080 \
  -e ADMIN_EMAILS=you@example.com \
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
must contain the administrator's email; an empty allowlist denies panel access.

## Required environment

| Variable | Notes |
| --- | --- |
| `APP_KEY` | Generate once and keep it stable |
| `APP_URL` | Public URL used for links and assets |
| `ADMIN_EMAILS` | Required in production for panel, Horizon, and Telescope access |
| `DB_CONNECTION`, `DB_*` | SQLite, MySQL, or PostgreSQL |
| `REDIS_*` | Redis host, port, credentials, and client |

Optional settings, including cache, session, queue, mail, storage, and real-time
values, are listed in [configuration](configuration.md).

Never mount a `.env` file into the application tree. Pass configuration as environment variables so
secrets are not stored in an image layer or a backup archive.

## Ports and health

| Endpoint | Use |
| --- | --- |
| `8080` | nginx; map 80/443 at your proxy |
| `/up` | Liveness; no database access |
| `/health/ready` | Readiness; requires the database and reports Redis as `degraded` |

The image healthcheck requests `/up` every 30 seconds. Queue, scheduler, and
Horizon containers are not HTTP servers: disable the inherited healthcheck for
them (`docker run --no-healthcheck` or `healthcheck: disable: true`). A Reverb
container can keep it when Reverb listens on port 8080.

## Persistent storage

Mount a volume at `/var/www/html/storage` and keep `bootstrap/cache` inside the
container.

| Path | Contents |
| --- | --- |
| `storage/app` | Application files and uploads |
| `storage/app/public` | Public files served through `public/storage` |
| `storage/backup` | Backup archives |
| `storage/logs` | Log files when not logging to stderr |
| `storage/framework` | Cache, sessions, and compiled views |

The container runs as `www-data` with uid/gid `82`. New named volumes inherit
that ownership from the image. Bind mounts must be writable by uid 82:

```bash
chown -R 82:82 ./storage
```

## Upgrading

Pull the new tag, keep the existing `APP_KEY` and storage volume, and run
migrations explicitly:

```bash
docker exec backend-lab php artisan migrate --force
```

Check [UPGRADE.md](../UPGRADE.md) before upgrading when a release lists
action-required changes.

## Next steps

- [Operations](operations.md): queue, scheduler, Horizon, logs, and backups.
- [Real-time updates](realtime.md): Reverb server and browser endpoints.
- [Configuration](configuration.md): every supported environment variable.
- [Testing](testing.md): local and CI verification.
