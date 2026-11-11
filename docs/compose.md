# Docker Compose deployment

Use [compose.yaml](../compose.yaml) for production and
[compose.dev.yaml](../compose.dev.yaml) for development. These are independent
definitions, not base/override files. Both support bundled official PostgreSQL
and Redis or existing external services. Production uses the [published image](container.md);
development builds a separate target with source mounts, dev dependencies, and Vite.

## First installation: production

From a checkout of the desired release:

```bash
cp .env.compose.example .env.compose
chmod 600 .env.compose
docker run --rm -i --user "$(id -u):$(id -g)" --entrypoint php \
  -v "$PWD/.env.compose:/deployment.env" \
  ghcr.io/haoyuqi/laravel-backend-lab:latest < docker/compose-init.php
```

This explicit helper fills only empty application, database, and Reverb keys.
It prints no secrets and preserves existing values on repeat runs. Its temporary
config mount is outside the application tree and backups. Do not rotate `APP_KEY`
on upgrade. Set `APP_URL` and `ADMIN_EMAILS` in `.env.compose`, and protect/back up
this ignored file separately. Choose `VERSION=latest`, a full release such as
`3.1.0`, or `3.1.0@sha256:<published-index-digest>` to pin the artifact.

Pass the same `--env-file` to **every** command: it controls both interpolation
and runtime variables. Compose `env_file` does not mount Laravel's `.env`.

```bash
docker compose --env-file .env.compose pull
docker compose --env-file .env.compose up -d --wait database redis
docker compose --env-file .env.compose run --rm app php artisan migrate --force
docker compose --env-file .env.compose run --rm app php artisan make:filament-user
docker compose --env-file .env.compose up -d
```

Use an email from `ADMIN_EMAILS`. Prepare the schema before starting workers
and scheduler. Startup never migrates, seeds, creates administrators, or changes keys.

| Service | Role |
| --- | --- |
| `app` | nginx + PHP-FPM, loopback port 8080 by default |
| `worker` | `default`, `queue-test`, `record-visitor`, `black-list-log`, `push-time` queues |
| `scheduler` | One scheduler; do not scale this service |
| `reverb` | Optional real-time server, profile `realtime` |
| `database` | Official PostgreSQL 17, profile `database` |
| `redis` | Official Redis 7 with append-only persistence, profile `redis` |

Dependencies have no published ports. Restrict the private network to trusted
containers; bundled Redis has no password. `HTTP_BIND` defaults to loopback.
Use your existing production TLS proxy and configure trusted proxies explicitly.

## Existing database and/or Redis

| Dependency source | `COMPOSE_PROFILES` | Connection configuration |
| --- | --- | --- |
| Both bundled | `database,redis` | `DB_HOST=database`, `REDIS_HOST=redis` |
| Existing database | `redis` | External `DB_*`, `REDIS_HOST=redis` |
| Existing Redis | `database` | `DB_HOST=database`, external `REDIS_*` |
| Both existing | empty | External `DB_*` and `REDIS_*` |

Set hostname, port, database, credentials, and TLS settings. `localhost` means
the application container. Existing MySQL works with `DB_CONNECTION=mysql` and
matching settings; disable the PostgreSQL profile. Shell variables override the
file, so clear any inherited `COMPOSE_PROFILES` when changing modes.

On an existing Redis instance, allocate dedicated database indexes for this
installation and use a unique `REDIS_PREFIX`/`CACHE_PREFIX`. Prefixes alone
cannot isolate operations such as clearing an entire cache database. Production
and development templates have different prefixes, but must also use distinct
database indexes when sharing the same Redis instance.

There are no mandatory `depends_on` links to optional dependencies. Start bundled
services with `--wait`, or check external availability before starting app roles.
Readiness reports database and Redis health. Disabling a profile does not stop
a container already running: stop old local services explicitly when switching,
retain their volumes/backups, and recreate app roles with the new configuration.
Changing initialization credentials does not update an existing PostgreSQL role.

## Development

```bash
cp .env.compose.dev.example .env.compose.dev
chmod 600 .env.compose.dev
docker run --rm -i --user "$(id -u):$(id -g)" --entrypoint php \
  -v "$PWD/.env.compose.dev:/deployment.env" \
  ghcr.io/haoyuqi/laravel-backend-lab:latest < docker/compose-init.php
```

Set `DEV_UID`/`DEV_GID` to the source owner's ids (`id -u` / `id -g`, default 1000).
Choose bundled/external dependencies as above. For bundled services:

```bash
docker compose --env-file .env.compose.dev -f compose.dev.yaml build app
docker compose --env-file .env.compose.dev -f compose.dev.yaml up -d --wait database redis
docker compose --env-file .env.compose.dev -f compose.dev.yaml run --rm app php artisan migrate
docker compose --env-file .env.compose.dev -f compose.dev.yaml run --rm app php artisan make:filament-user
docker compose --env-file .env.compose.dev -f compose.dev.yaml run --rm app php artisan filament:assets
docker compose --env-file .env.compose.dev -f compose.dev.yaml run --rm app php artisan storage:link
docker compose --env-file .env.compose.dev -f compose.dev.yaml up -d
```

Open `http://localhost:8082`. Vite defaults to port 5173; `VITE_PORT` sets the
published/browser port and `VITE_HMR_HOST` sets the browser-reachable host.
PHP checks source timestamps. Restart the worker after PHP edits. Update mounted
dependencies explicitly with `exec app composer install` / `exec app npm ci`
after lockfile changes; rebuilding an image does not refresh an existing volume.

Only source paths are mounted: host `.env`, dependencies, and bootstrap caches
stay out. Development dependency volumes are populated from the development
image. `bootstrap/cache` stays local to each role. Generated public assets are
written to the source public directory. Remove `public/hot` after stopping Vite
before returning to static assets. Source-only installs may need to recreate
the container-path `public/storage` symlink using their own `storage:link`.

Default project names `backend-lab` and `backend-lab-dev` isolate networks and
volumes. Do not use the same `-p` name for both. Select another unique name for
additional installations. Tests must still use their guarded `testing`
connection, never a development business database. Laradock remains supported.

## Optional real-time updates

Set `BROADCAST_CONNECTION=reverb` and append `realtime` to `COMPOSE_PROFILES`.
Keep the initialized Reverb keys. All app roles need the same settings.
Internally use `REVERB_HOST=reverb`, port 8080. Set `REVERB_CLIENT_*` to the
browser-reachable address (loopback 8081 in production or 8083 in development).
Run `up -d` to apply changes to all roles. No frontend rebuild is required.
HTTPS proxies must forward WebSocket upgrade headers and publishing routes.
See [real-time updates](realtime.md), including origin restrictions.

## Operation, upgrade, and rollback

- Use `ps` and `logs` for process status. Do not disclose personal data from logs.
  `/up` checks liveness; `/health/ready` reports dependencies. Redis failure is
  degraded with HTTP 200: inspect the response, not just the status code.
- Non-HTTP worker/scheduler roles have the inherited HTTP healthcheck disabled.
  Monitor queue progress and actual scheduled executions. Production roles
  restart unless stopped; workers get 150 seconds to stop (job timeout: 120).
- Shared application storage retains uploads, cache/sessions, and backups;
  database and Redis have separate volumes. Never share `bootstrap/cache`.
  If using configuration caches, rebuild them explicitly in each app role.
- Read [UPGRADE.md](../UPGRADE.md), back up database/storage, retain the old
  digest/configuration, and pull the new `VERSION` before maintenance.
- Stop worker, scheduler, and optional Reverb before migration. Maintenance
  mode alone does not stop writers. Execute `run --rm app php artisan migrate
  --force` using the new version; recreate app roles, verify behavior, then reopen
  traffic. Preserve keys and volumes; do not recreate dependencies unnecessarily.
- Roll back application images only when code/schema are compatible. Stop
  writers and follow migration-specific recovery instructions. Do not
  automatically run `migrate:rollback` or `migrate:fresh`.
- `down` retains named volumes. **Never use `down --volumes` for upgrades.**
  Database major upgrades are separate backup/restore operations.

See [operations](operations.md) for current backup limitations and off-host
restore drills. Local archives alone are not disaster recovery. PostgreSQL 17
is the tested baseline; verify `pg_dump`/`mysqldump` compatibility and rehearse a
dump/restore before using newer external database majors. Backup redesign is #198.

## Verification

CI uses unique disposable projects and source copies to verify both definitions,
profile combinations, separately owned external dependencies, queue processing,
admin login, actual Reverb delivery, Vite/source edits, and recreation persistence.
Existing quality and 100% application-coverage gates remain required for releases.

Use `bash .github/scripts/smoke-compose.sh production` with `APP_IMAGE`/`VERSION`
pointing to a local image, or argument `development` to build its target.
`COMPOSE_BROWSER_CHECK=true` runs Playwright after preparing Node dependencies
and Chromium. Cleanup targets only test-owned projects and temporary files.
