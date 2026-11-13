# Docker Compose deployment

Use [compose.yaml](../compose.yaml) for production and
[compose.dev.yaml](../compose.dev.yaml) for development. These are independent
definitions, not base/override files. Both support bundled official PostgreSQL
and Redis or existing external services. Production uses the [published image](container.md);
development builds a separate target with source mounts, dev dependencies, and Vite.

## Choose how to run

Choose production or development first, then configure dependencies in that
environment's file. You do not need to edit the Compose YAML to use an existing
database or Redis.

| Mode | Compose file | Environment file | Application |
| --- | --- | --- | --- |
| Production | `compose.yaml` | `.env.compose` | Published image, HTTP port 8080 |
| Development | `compose.dev.yaml` | `.env.compose.dev` | Source mounts and Vite, HTTP port 8082 |

The default is a self-contained installation: `COMPOSE_PROFILES=database,redis`.
Production starts four services (`app`, `background`, `database`,
`redis`) using three image types. Development adds `vite`: five services using
three image types, with the application image built locally. Reverb is optional
and adds one service using the same application image. Initialization commands
below create temporary containers that exit afterwards.

Bundled database and Redis ports are not published to the host. They do not
occupy the host's 5432/6379 ports or use an existing database's data directory;
each uses a project-specific named volume. They can coexist with host services.
Connecting the application to an existing database, however, makes migrations
and business operations affect that database: use a dedicated database/account.

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
`3.2.0`, or `3.2.0@sha256:<published-index-digest>` to pin the artifact.

Pass the same `-f` and `--env-file` to every command: they select the environment
and connection configuration. Keep `COMPOSE_PROFILES=database,redis` for the
default installation. Unset any shell-level `COMPOSE_PROFILES`, which takes
precedence over the file. Compose `env_file` passes
variables into containers; it does not mount Laravel's `.env`.

```bash
docker compose -f compose.yaml --env-file .env.compose pull
docker compose -f compose.yaml --env-file .env.compose up -d --wait database redis
docker compose -f compose.yaml --env-file .env.compose run --rm app php artisan migrate --force
docker compose -f compose.yaml --env-file .env.compose run --rm app php artisan make:filament-user
docker compose -f compose.yaml --env-file .env.compose up -d
```

Use an email from `ADMIN_EMAILS`. Prepare the schema before starting workers
and scheduler. Startup never migrates, seeds, creates administrators, or changes keys.

| Service | Role |
| --- | --- |
| `app` | nginx + PHP-FPM, loopback port 8080 by default |
| `background` | Supervisor runs the queue worker, analytics worker, and scheduler; do not scale this service |
| `reverb` | Optional real-time server, profile `realtime` |
| `database` | Official PostgreSQL 18.6, profile `database` |
| `redis` | Official Redis 7 with append-only persistence, profile `redis` |

Dependencies have no published ports. Restrict the private network to trusted
containers; bundled Redis has no password. `HTTP_BIND` defaults to loopback.
Use your existing production TLS proxy and configure trusted proxies explicitly;
`.env.compose.example` keeps `SESSION_SECURE_COOKIE=false` for the loopback HTTP
quick start and must be set to `true` when serving over HTTPS.

## Existing database and/or Redis

| Dependency source | `COMPOSE_PROFILES` in the environment file | Connection configuration |
| --- | --- | --- |
| Both bundled (default) | `database,redis` | `DB_HOST=database`, `REDIS_HOST=redis` |
| Existing database | `redis` | External `DB_*`, `REDIS_HOST=redis` |
| Existing Redis | `database` | `DB_HOST=database`, external `REDIS_*` |
| Both existing | Empty: `COMPOSE_PROFILES=` | External `DB_*` and `REDIS_*` |

The final startup command stays the same in all cases:
`docker compose -f compose.yaml --env-file .env.compose up -d`.
With only one bundled dependency production starts three services using two
image types; with neither it starts two services using one image type.
Development adds Vite to each count. Reverb adds one service when enabled.

Set hostname, port, database, credentials, and TLS settings. `localhost` means
the application container. Existing MySQL works with `DB_CONNECTION=mysql` and
matching settings; remove `database` from `COMPOSE_PROFILES` in that case. Unset any inherited
`COMPOSE_PROFILES` shell variable so the selected environment file takes effect.

For example, to use an existing PostgreSQL server and Redis, edit the production
environment file before initialization. Use a database/account dedicated to this
application; do not point migrations at another application's business tables:

```dotenv
COMPOSE_PROFILES=
DB_CONNECTION=pgsql
DB_HOST=db.example.internal
DB_PORT=5432
DB_DATABASE=backend_lab
DB_USERNAME=backend_lab
DB_PASSWORD=<existing database password>
REDIS_HOST=redis.example.internal
REDIS_PORT=6379
REDIS_PASSWORD=<existing Redis password, or null if authentication is disabled>
```

The addresses must be reachable from containers. The provider must create the
database and grant this account the permissions needed for application migrations.
Preserve the existing credentials when running the key-initialization helper.
After preparing the application key and administrator allowlist:

```bash
# No database or Redis container will be started.
docker compose -f compose.yaml --env-file .env.compose pull
docker compose -f compose.yaml --env-file .env.compose run --rm app php artisan migrate --force
docker compose -f compose.yaml --env-file .env.compose run --rm app php artisan make:filament-user
docker compose -f compose.yaml --env-file .env.compose up -d
```

If Redis is not already available, set `COMPOSE_PROFILES=redis` and keep
`REDIS_HOST=redis` from the template. Before migration, run
`docker compose -f compose.yaml --env-file .env.compose up -d --wait redis`.
If only the database is bundled, use `COMPOSE_PROFILES=database` and prepare it
with `up -d --wait database`. Do not run `up ... database` for an external-only
installation: explicitly naming a service starts it even if its profile is off.
The same choices apply to the development file.

For a database running directly on Docker Desktop's host, use
`DB_HOST=host.docker.internal`, not `localhost`. On Linux, configure a reachable
host address or a host-gateway mapping. A database in another Compose project
needs a reachable endpoint or an explicitly shared Docker network; its service
name alone will not resolve across isolated project networks. Check database
listening addresses, account permissions, and firewall rules as needed.

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
OPcache is disabled in development so HTTP requests pick up source edits.
Restart `background` after PHP edits to reload its long-running processes. Update mounted
dependencies explicitly with `exec app composer install` / `exec app npm ci`
after lockfile changes; rebuilding an image does not refresh an existing volume.
Vite ignores `vendor`, `storage`, and `bootstrap/cache` when watching files to
avoid exhausting the host's file watcher limit. Application source still reloads;
restart Vite after replacing vendor dependencies.

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

For live updates on `/time`, edit the selected environment file. For the default
production installation:

```dotenv
COMPOSE_PROFILES=database,redis,realtime
BROADCAST_CONNECTION=reverb
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_CLIENT_HOST=localhost
REVERB_CLIENT_PORT=8081
REVERB_CLIENT_SCHEME=http
```

Keep the Reverb keys created by the initialization helper. If dependencies are
external, retain your chosen profiles and add only `realtime` (for example,
`COMPOSE_PROFILES=realtime` when both are external). In development the browser
port is 8083 instead of 8081. For remote browsers replace `localhost` with a
browser-reachable hostname; the default loopback binding is for local access or
a proxy on the same host.

Apply the configuration to **all** application roles, not just Reverb:

```bash
# Production
docker compose -f compose.yaml --env-file .env.compose up -d
docker compose -f compose.yaml --env-file .env.compose ps
docker compose -f compose.yaml --env-file .env.compose logs --tail=50 reverb background

# Development (use this instead of the production commands)
docker compose -f compose.dev.yaml --env-file .env.compose.dev up -d
docker compose -f compose.dev.yaml --env-file .env.compose.dev ps
docker compose -f compose.dev.yaml --env-file .env.compose.dev logs --tail=50 reverb background
```

Open `http://localhost:8080/time` in production or
`http://localhost:8082/time` in development and wait for the next minute.
The scheduler dispatches the event, the worker consumes the `push-time` queue,
and Reverb delivers it to the browser. The worker and scheduler both run in
`background`, while `reverb` is a separate service. Compose
already starts both background processes, so do not start duplicate Artisan
processes manually. No frontend rebuild is required.
HTTPS proxies must forward WebSocket upgrade headers and publishing routes.
See [real-time updates](realtime.md), including origin restrictions.

To disable live updates, set `BROADCAST_CONNECTION=log`, remove `realtime` from
`COMPOSE_PROFILES`, explicitly stop `reverb`, then run `up -d` again. Removing
the profile alone does not stop an already-running server.

## Background services and status

The normal `up -d` starts the queue worker and scheduler automatically. Use the
matching Compose and environment files for every operation; production examples:

```bash
docker compose -f compose.yaml --env-file .env.compose ps
docker compose -f compose.yaml --env-file .env.compose logs --tail=50 app background
docker compose -f compose.yaml --env-file .env.compose exec background supervisorctl -c /etc/supervisor/background.conf status
# After editing PHP source in development, restart the long-running worker
# using compose.dev.yaml and .env.compose.dev instead.
docker compose -f compose.yaml --env-file .env.compose restart background
# Stop this installation without deleting its persistent volumes.
docker compose -f compose.yaml --env-file .env.compose down
```

`restart` does not apply changed environment settings or image versions; use
`up -d` for those changes. Do not run `down --volumes` unless deliberately
deleting this installation's stored data. After switching to external services,
explicitly stop previously bundled containers; keep their volumes for recovery.

The main background worker consumes `default`, `queue-test`, `black-list-log`,
and `push-time`; the analytics worker consumes `record-visitor` so visitor
GeoIP and database work cannot delay blacklist logging or scheduled broadcasts.
Supervisor restarts any background process when it exits, and the container
healthcheck requires all of them to be running.
Keep one `background` replica so scheduled tasks are not duplicated. If queue
capacity later requires independent scaling, separate the worker from the
scheduler again.

When upgrading an earlier Compose installation that had separate `worker` and
`scheduler` services, stop and remove **those two containers** using the old
Compose file before starting `background`. Do not leave the old scheduler
running, and retain the storage and dependency volumes. Use an application image
built from this revision or a later release: earlier images do not contain
`/etc/supervisor/background.conf`.

## Operation, upgrade, and rollback

- Use `ps` and `logs` for process status. Do not disclose personal data from logs.
  `/up` checks liveness; `/health/ready` reports dependencies. Redis failure is
  degraded with HTTP 200: inspect the response, not just the status code.
- The combined background role replaces the HTTP healthcheck with a Supervisor
  process check. Also monitor queue progress and actual scheduled executions. Production roles
  restart unless stopped; workers get 150 seconds to stop (job timeout: 120).
- Shared application storage retains uploads, cache/sessions, and backups;
  database and Redis have separate volumes. Never share `bootstrap/cache`.
  If using configuration caches, rebuild them explicitly in each app role.
- Read [UPGRADE.md](../UPGRADE.md), back up database/storage, retain the old
  digest/configuration, and pull the new `VERSION` before maintenance.
- Stop `background` and optional Reverb before migration. Maintenance
  mode alone does not stop writers. Execute `run --rm app php artisan migrate
  --force` using the new version; recreate app roles, verify behavior, then reopen
  traffic. Preserve keys and volumes; do not recreate dependencies unnecessarily.
- Roll back application images only when code/schema are compatible. Stop
  writers and follow migration-specific recovery instructions. Do not
  automatically run `migrate:rollback` or `migrate:fresh`.
- `down` retains named volumes. **Never use `down --volumes` for upgrades.**
  Database major upgrades are separate backup/restore operations.

See [operations](operations.md) for current backup limitations and off-host
restore drills. Local archives alone are not disaster recovery. PostgreSQL 18.6
is the Compose baseline; verify `pg_dump`/`mysqldump` compatibility and rehearse a
dump/restore before using newer external database majors. Backup redesign is #198.

PostgreSQL 18 mounts its volume at `/var/lib/postgresql` (the image's default
data directory is version-specific underneath it). An existing PostgreSQL 17
volume cannot be upgraded by changing the image tag or mount path. Keep the old
volume, dump with a compatible client, restore into a new PostgreSQL 18 volume,
and verify the restored data before switching the application connection.

## Verification

CI uses unique disposable projects and source copies to verify both definitions,
profile combinations, separately owned external dependencies, queue processing,
admin login, actual Reverb delivery, Vite/source edits, and recreation persistence.
Existing quality and 100% application-coverage gates remain required for releases.

Use `bash .github/scripts/smoke-compose.sh production` with `APP_IMAGE`/`VERSION`
pointing to a local image, or argument `development` to build its target.
`COMPOSE_BROWSER_CHECK=true` runs Playwright after preparing Node dependencies
and Chromium. Cleanup targets only test-owned projects and temporary files.
