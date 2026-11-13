# Configuration

The application reads configuration from environment variables. Start from
`.env.example`; never commit real credentials.

## Services

| Service | Required | Notes |
| --- | --- | --- |
| Database | Yes | Set `DB_CONNECTION` and the matching `DB_*` values. SQLite, MySQL, and PostgreSQL are supported. |
| Redis | Yes | Visitor statistics, dashboard widgets, and scheduled cleanup call Redis directly. `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, and `BROADCAST_CONNECTION` may use other drivers, but Redis itself cannot be removed through `.env` alone. |
| Queue worker | No | Required with an asynchronous queue connection. The `sync` driver handles jobs in the request process. |
| Horizon | No | Requires Redis and a Redis queue connection. Not needed with `sync` or a non-Redis queue. |
| Scheduler | Feature-dependent | Run `php artisan schedule:run` every minute, or run `php artisan schedule:work` as a long-running process. |
| Mail server | No | Use `MAIL_MAILER=log` locally, or configure SMTP for outgoing mail. |
| Object storage | No | The local filesystem works without S3. Configure `FILESYSTEM_DRIVER`, `FILESYSTEM_CLOUD`, and `AWS_*` only for external storage. |
| Real-time server | No | Keep `BROADCAST_CONNECTION=log` and `REVERB_APP_KEY` empty to disable real-time. See [real-time updates](realtime.md). |
| Telescope and Debugbar | No | Set `TELESCOPE_ENABLED=false` and `DEBUGBAR_ENABLED=false` when these tools should not collect data. The container image disables both by default. |

## Important variables

- `APP_KEY` encrypts cookies and other encrypted values. Generate it once and
  keep it stable.
- `APP_URL` is the public application URL.
- `APP_VERSION` is displayed in the admin dashboard. The container image sets it
  from the release tag; source deployments should set the released version.
- `ADMIN_EMAILS` is the comma-separated production allowlist for the panel,
  Horizon, and Telescope. An empty list denies panel access in production.
- `DB_*` selects the database.
- `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_USERNAME`, `REDIS_PASSWORD`, and
  `REDIS_PORT` configure Redis, including ACL authentication.
- `CACHE_STORE`, `SESSION_DRIVER`, and `QUEUE_CONNECTION` select state and job
  storage.
- `BROADCAST_CONNECTION`, `REVERB_*`, and `REVERB_CLIENT_*` configure real-time
  events.
- `MAIL_MAILER` and `MAIL_*` configure outgoing mail.
- `FILESYSTEM_DRIVER`, `FILESYSTEM_CLOUD`, and `AWS_*` configure file storage.
- `TELESCOPE_ENABLED` and `DEBUGBAR_ENABLED` control optional inspection tools.
- `TRUSTED_PROXIES` lists proxy addresses or CIDR ranges when the application
  runs behind a reverse proxy.
- `SESSION_SECURE_COOKIE` should be `true` whenever a TLS proxy terminates
  HTTPS for a public hostname.
- `REVERB_ALLOWED_ORIGINS` lists the browser hostnames allowed to open the
  WebSocket (comma-separated, no scheme or port). Set it to the real site hosts
  in production.
- `REVERB_APP_RATE_LIMITING_ENABLED` limits Reverb connection attempts; it
  defaults to `true`.
- `VISITOR_RETENTION_DAYS` controls how long raw visitor IP and request-path
  rows are kept before the scheduled `visitor-data:prune` command deletes them
  (30 by default).
- `TEST_DB_*` configures an isolated database for the test suite.

## Shared database and Redis services

Keep Laravel's normal `DB_*` variables or `DB_URL`; no deployment-specific
connection format is required. PostgreSQL also accepts `DB_SSLMODE` (default
`prefer`). TLS verification additionally requires the appropriate trust and
certificate configuration for the server.

Redis keeps Laravel's named `default` and `cache` connections and the optional
`queue` connection already provided by this application:

| Connection | Logical database | Connection selection |
| --- | --- | --- |
| `default` | `REDIS_DB` (default `0`) | Direct `Redis` calls and queues by default |
| `cache` | `REDIS_CACHE_DB` (default `1`) | `REDIS_CACHE_CONNECTION=cache` by default |
| `queue` | `REDIS_QUEUE_DB` (default `3`) | Opt in with `REDIS_QUEUE_CONNECTION=queue` |

`REDIS_QUEUE_CONNECTION` selects a Redis connection; `QUEUE_CONNECTION=redis`
selects Laravel's queue driver, and `REDIS_QUEUE` is the queue name. These are
separate settings. Existing installations keep the `default` connection until
explicitly changed; changing a DB or connection does not move pending jobs.

All three inherit `REDIS_HOST`, `REDIS_PORT`, `REDIS_USERNAME`,
`REDIS_PASSWORD`, and `REDIS_URL`. Cache and queue connections can override
these independently using `REDIS_CACHE_HOST/PORT/USERNAME/PASSWORD/URL` or
`REDIS_QUEUE_HOST/PORT/USERNAME/PASSWORD/URL`. Omit unused overrides; an empty
value is an explicit value, not a request to inherit. Set `REDIS_USERNAME=null`
when the server uses password-only authentication rather than a named ACL user.

URLs use Laravel's native parser: URL fields override matching individual
settings, and URL query options override URL path components. A shared
`REDIS_URL` without a DB path preserves each connection's `REDIS_*_DB`. A URL
ending in `/0` overrides those DB assignments for every connection inheriting
it. Prefer individual fields for shared-instance DB allocation, or use explicit
per-connection URLs. Percent-encode credentials in URLs; never commit real URLs
containing credentials.

For example, an operator can allocate DB 1 for this application's direct Redis
usage and queue, and DB 2 for its cache:

```dotenv
REDIS_USERNAME=backend_lab
REDIS_DB=1
REDIS_CACHE_DB=2
REDIS_QUEUE_CONNECTION=queue
REDIS_QUEUE_DB=1
REDIS_CACHE_CONNECTION=cache
REDIS_PREFIX=backend_lab:
CACHE_PREFIX=cache:
BACKUP_SCHEDULE_ENABLED=false
```

Supply the host, port and private password separately. These DB assignments are
an example, not reserved global defaults; register assignments on the shared
server and ensure its `databases` setting includes the highest selected index.
DBs share memory and eviction policy. Logical DBs and prefixes are not access
controls: use dedicated ACL users restricted to application key prefixes,
commands and Pub/Sub channels. Account for both Redis and cache prefixes.

Do not grant shared-instance application users `FLUSHDB` or `FLUSHALL`.
Laravel's Redis `cache:clear` / `Cache::flush()` clears a whole logical DB and
does not respect prefixes; it will be rejected by a restricted ACL. Do not use
`optimize:clear` blindly either: it also invokes cache clearing. Use explicit
config/route/view clear commands and targeted application-key invalidation.
The application's production cleanup commands delete individual logical keys;
this change does not replace Laravel's cache driver or its flush semantics.

A `volatile-*` eviction policy can remove any expiring key, including cache
locks, uniqueness markers and application statistics. A different DB does not
protect such keys. Do not add TTLs indiscriminately to pending queue data. If
there are no eligible keys left to evict, memory-growing writes can still fail;
monitor Redis memory, rejected writes and queue age.

## Public rate limit

`/`, `/error`, `/time`, and `/test` share a per-IP limit of 60 requests per minute
(`App\Http\Middleware\ThrottlePublicRequests`). The limiter fails open when its
cache store is unavailable, so a Redis outage never takes the public pages down.
Health probes are exempt. `/queue/create` and `/sort/*` keep the separate
10-per-minute demo limit.

## Demo routes

The queue and sorting demos are disabled by default. Set
`DEMO_ROUTES_ENABLED=true` only when exposing them intentionally. They are
limited to 10 requests per minute per IP and 1,000 items. Creating a demo job
uses `POST /queue/create` and requires the normal web CSRF token.

## Reverse proxies

Set `TRUSTED_PROXIES` to a comma-separated list of the proxy addresses or CIDR
ranges you control. Forwarded client headers from other addresses are ignored.
This keeps visitor IPs and blacklist decisions trustworthy.

## Logging and privacy

New visitor and blocked-request logs keep the request path without query strings
or fragments, up to 2,048 bytes. Historical rows are not rewritten. Telescope
redacts `_token` and Livewire `components` payloads in every environment.
`visitor-data:prune` removes raw visitor rows after `VISITOR_RETENTION_DAYS`;
aggregate PV/UV snapshots expire from Redis after eight days.

Set `LOG_CHANNEL=stderr` in containers so logs reach the container runtime. See
[operations](operations.md) for log retention and cleanup.

## Containers

Container deployments have additional variables, persistent paths, and health
checks. See [container installation](container.md).

Compose uses `.env.compose` for production and `.env.compose.dev` for development;
both have tracked example templates. Pass the matching `--env-file` to every
command. `VERSION` defaults to `latest`. The templates set
`COMPOSE_PROFILES=database,redis` to start bundled services. Remove either name
to use that service externally; leave the value empty to use both externally.
Add `realtime` to enable Reverb. Set connection addresses and credentials in
the same file. Unset any shell-level `COMPOSE_PROFILES`, which would override
the file. `HTTP_BIND` defaults to
loopback. Development additionally accepts `DEV_UID`, `DEV_GID`, `VITE_PORT`, and
`VITE_HMR_HOST`. See [Compose deployment](compose.md) for initialization and
external database/Redis combinations. Never commit populated configuration files.
