# Operations

## Background services

Run each role as a separate process. The commands are identical for source and
container deployments.

```bash
# Queue worker. The push-time queue feeds the /time page.
php artisan queue:work --queue=queue-test,record-visitor,black-list-log,push-time

# Horizon, when using a Redis queue.
php artisan horizon

# Scheduler: either a long-running process or cron running schedule:run every minute.
php artisan schedule:work

# Reverb, when real-time updates are enabled.
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Keep workers under a process supervisor. In containers, run each role as its own
container with the same image and a command override. Disable the inherited HTTP
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
| Every minute | Broadcast the time event (`push-time` queue) |
| 01:00 / 01:10 / 01:20 | `backup:run`, `backup:clean`, `backup:monitor` |
| 00:05 | `save:visits-count` |
| 00:10 | `clear:files` |
| 00:20 | Delete yesterday's blacklist cache key |
| 00:30 | `telescope:prune --hours=72` |
| 05:00 | `download:bing-wallpaper` |
| 07:00 and 13:00 | Delete today's blacklist cache key |
| Every six hours | `geoip:clear` |

Schedule locks prevent overlapping instances of the same command. Multi-server
deployments must use a shared lock store and run the scheduler on one designated
server.

## Logs

Laravel writes to `storage/logs` with the `stack` channel, or to stderr when
`LOG_CHANNEL=stderr`. `clear:files` deletes log files older than seven days.
Container deployments should use `LOG_CHANNEL=stderr` and let the container
runtime handle rotation.

## Backups

Backups use `spatie/laravel-backup` and run from the scheduler:

- `backup:run` archives the configured files and dumps the database selected by
  `DB_CONNECTION`.
- `backup:clean` applies the retention policy.
- `backup:monitor` checks that the newest backup is under one day old and total
  storage stays under 5,000 MB.

Archives are written to the local `backup` disk at `storage/backup` and stay
local unless the backup disk is changed. Configure `BACKUP_NOTIFICATION_EMAIL`
with a valid recipient and a working mail transport to receive failure and
monitor notifications. Successful backups are not emailed.

The database dump shells out to `mysqldump`, `pg_dump`, or `sqlite3`. In
containers, the client major version must not be older than the database server:
`pg_dump` and `mysqldump` refuse to dump a newer server. Upgrade the client
package and rebuild the image when the database server moves to a newer major
version.

Periodically restore an archive into an isolated database and verify it. Backups
contain personal data and are not encrypted unless `BACKUP_ARCHIVE_PASSWORD` is
configured.

Do not mount `.env` into an application directory that is included in the
archive; pass secrets as runtime environment variables instead.

## Cached configuration

The container image does not cache configuration at build time. After the
environment is final, run explicitly:

```bash
php artisan optimize
```

Run it in every container that needs the cache. `bootstrap/cache` is
container-local and should not be shared between containers.
