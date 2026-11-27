# Operations

For Compose installation, service commands, persistent volumes, and safe
upgrades, see [Compose deployment](compose.md). Use the same environment file
and project name for every operation on an installation.

## Background services

Run each role as a separate process. The commands are identical for source and
container deployments.

```bash
# Queue worker. The push-time queue feeds the /time page.
php artisan queue:work --queue=default,queue-test,black-list-log,push-time

# Visitor analytics worker. Kept separate so GeoIP and visitor writes cannot
# delay blacklist logging or scheduled broadcasts.
php artisan queue:work --queue=record-visitor

# Horizon, when using a Redis queue.
php artisan horizon

# Scheduler: either a long-running process or cron running schedule:run every minute.
php artisan schedule:work

# Reverb, when real-time updates are enabled.
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Keep workers under a process supervisor. Compose combines the queue worker,
analytics worker, and scheduler in one `background` container managed by
Supervisor, with a healthcheck that checks every process. Keep one replica to avoid duplicate schedulers;
see [Compose deployment](compose.md#background-services-and-status) for commands.
Standalone roles can still use the same image with a command override. Disable the inherited HTTP
healthcheck for queue, scheduler, and Horizon containers; a Reverb container can
keep it only when Reverb listens on port 8080.

## Health checks

| Endpoint | Behavior |
| --- | --- |
| `/up` | Liveness only; does not query the database or Redis |
| `/health/ready` | Queries the database and Redis; returns HTTP 503 only when the database fails |

`/health/ready` returns HTTP 200 with `status: degraded` when Redis is
unreachable but the database is healthy, because the application degrades
visitor statistics instead of failing requests.

## Scheduled tasks

The scheduler runs these entries from `routes/console.php`:

| Schedule | Task |
| --- | --- |
| 00:05 | `save:visits-count` |
| 00:10 | `clear:files` |
| 00:15 | `visitor-data:prune --force` |
| 00:20 | Delete yesterday's blacklist cache key |
| 00:25 | `queue:prune-failed --hours=168` |
| 00:30 | `telescope:prune --hours=72` |
| 05:00 | `download:bing-wallpaper` |
| 07:00 and 13:00 | Delete today's blacklist cache key |
| Every minute | Broadcast the time event (`push-time` queue) |
| Every six hours | `geoip:clear` |

Schedule locks prevent overlapping instances of the same command. Multi-server
deployments must use a shared lock store and run the scheduler on one designated
server.

## Logs

Laravel writes to `storage/logs` with the `stack` channel, or to stderr when
`LOG_CHANNEL=stderr`. `clear:files` deletes log files older than seven days.
`visitor-data:prune` permanently deletes raw `visitors` and `visitor_logs` rows
older than `VISITOR_RETENTION_DAYS` (30 by default), and `queue:prune-failed`
drops failed job payloads older than seven days.
Container deployments should use `LOG_CHANNEL=stderr` and let the container
runtime handle rotation.

## Cached configuration

The container image does not cache configuration at build time. After the
environment is final, run explicitly:

```bash
php artisan optimize
```

Run it in every container that needs the cache. `bootstrap/cache` is
container-local and should not be shared between containers.
