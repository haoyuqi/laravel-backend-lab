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
- `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, and `REDIS_PORT` configure
  Redis.
- `CACHE_STORE`, `SESSION_DRIVER`, and `QUEUE_CONNECTION` select state and job
  storage.
- `BROADCAST_CONNECTION`, `REVERB_*`, and `REVERB_CLIENT_*` configure real-time
  events.
- `MAIL_MAILER` and `MAIL_*` configure outgoing mail.
- `FILESYSTEM_DRIVER`, `FILESYSTEM_CLOUD`, and `AWS_*` configure file storage.
- `TELESCOPE_ENABLED` and `DEBUGBAR_ENABLED` control optional inspection tools.
- `TRUSTED_PROXIES` lists proxy addresses or CIDR ranges when the application
  runs behind a reverse proxy.
- `TEST_DB_*` configures an isolated database for the test suite.

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
