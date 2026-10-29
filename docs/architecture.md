# Architecture and feature map

Laravel Backend Lab is a Laravel 13 reference application combining a Filament 5
administration panel, visitor analytics, IP blacklist enforcement, queued jobs,
real-time Reverb broadcasting, scheduled maintenance, and operational health probes.
This reference describes the behavior implemented in the current source.

## Boundaries

Keep HTTP controllers, middleware, console commands, and Filament pages at the
boundary: validate and authorize inputs there, then delegate stateful business
mutations to services or queued jobs. Persistence uses Eloquent models directly;
there is no repository layer.

Public pages render Blade views with Vite-bundled Vue 3 and Bootstrap 5 assets.
Administrative interfaces live under `/admin` using Filament 5 and Livewire 4.

## Find implemented behavior

| Concern | Source of truth | Automated tests |
| --- | --- | --- |
| Routing, middleware order, and bootstrap | [bootstrap/app.php](../bootstrap/app.php), [routes/web.php](../routes/web.php), [routes/console.php](../routes/console.php), [routes/channels.php](../routes/channels.php) | [VisitorMiddlewareOrderTest](../tests/Feature/Http/VisitorMiddlewareOrderTest.php), [CorsTest](../tests/Feature/CorsTest.php) |
| Liveness (`/up`) and readiness (`/health/ready`) probes | [bootstrap/app.php](../bootstrap/app.php), [routes/health.php](../routes/health.php), [config/app.php](../config/app.php) | [LivenessReadinessTest](../tests/Feature/Http/LivenessReadinessTest.php), [HealthCheckIsolationTest](../tests/Feature/Http/HealthCheckIsolationTest.php) |
| Public pages and rate-limited demo endpoints | [IndexController](../app/Http/Controllers/IndexController.php), [SortController](../app/Http/Controllers/SortController.php), [QueueController](../app/Http/Controllers/QueueController.php), [CheckCountRequest](../app/Http/Requests/CheckCountRequest.php), [EnsureDemoEnabled](../app/Http/Middleware/EnsureDemoEnabled.php), [config/demo.php](../config/demo.php) | [IndexControllerTest](../tests/Feature/IndexControllerTest.php), [SortControllerTest](../tests/Feature/SortControllerTest.php), [QueueControllerTest](../tests/Feature/QueueControllerTest.php), [tests/e2e/public](../tests/e2e/public) |
| Real-time time broadcast (Reverb + Echo) | [PushTimeEvent](../app/Events/PushTimeEvent.php), [routes/console.php](../routes/console.php), [resources/js/echo.js](../resources/js/echo.js), [TimeComponent.vue](../resources/js/components/TimeComponent.vue) | [ReverbBroadcastingTest](../tests/Feature/ReverbBroadcastingTest.php), [tests/e2e/reverb/time.spec.ts](../tests/e2e/reverb/time.spec.ts) |
| Visitor tracking and request URL sanitization | [RecordVisitors middleware](../app/Http/Middleware/RecordVisitors.php), [RecordVisitors job](../app/Jobs/RecordVisitors.php), [SafeRequestUrl](../app/Support/SafeRequestUrl.php), [Visitor](../app/Models/Visitor.php), [VisitorLog](../app/Models/VisitorLog.php) | [RecordVisitorsMiddlewareQaTest](../tests/Feature/Service/RecordVisitorsMiddlewareQaTest.php), [RecordVisitorsTest](../tests/Feature/Jobs/RecordVisitorsTest.php), [SafeRequestUrlTest](../tests/Unit/Support/SafeRequestUrlTest.php) |
| GeoIP lookup and cache safety | [GetCityByIpAbstract](../app/Libraries/GetCityByIp/GetCityByIpAbstract.php), [GeoIP](../app/Libraries/GetCityByIp/GeoIP.php), [FreeAPI](../app/Libraries/GetCityByIp/FreeAPI.php), [BoundedIPApi](../app/Libraries/GetCityByIp/BoundedIPApi.php), [config/geoip.php](../config/geoip.php) | [BoundedIPApiTest](../tests/Unit/Support/BoundedIPApiTest.php), [GeoIpProvidersTest](../tests/Unit/Support/GeoIpProvidersTest.php), [GeoIPCacheSafetyTest](../tests/Feature/GeoIPCacheSafetyTest.php) |
| Daily PV/UV counting and idempotent persistence | [CountPvAndUv](../app/Http/Middleware/CountPvAndUv.php), [SaveVisitsCountCommand](../app/Console/Commands/SaveVisitsCountCommand.php), [VisitorStatisticsRetention](../app/Support/VisitorStatisticsRetention.php), [VisitorStatistics](../app/Models/VisitorStatistics.php) | [CountPvAndUvRedisFailureTest](../tests/Feature/Http/Middleware/CountPvAndUvRedisFailureTest.php), [SaveVisitsCountCommandTest](../tests/Feature/Console/SaveVisitsCountCommandTest.php), [VisitorStatisticsRetentionTest](../tests/Unit/Support/VisitorStatisticsRetentionTest.php), [VisitorStatisticsMigrationTest](../tests/Feature/VisitorStatisticsMigrationTest.php) |
| IP blacklist enforcement, normalization, and cache invalidation | [BlackListService](../app/Service/BlackListService.php), [AdminBlackListOperations](../app/Service/AdminBlackListOperations.php), [BlackListObserver](../app/Observers/BlackListObserver.php), [IpAddress](../app/Support/IpAddress.php), [BlackListLog job](../app/Jobs/BlackListLog.php), [BlackList](../app/Models/BlackList.php), [BlackListLog](../app/Models/BlackListLog.php) | [AdminBlackListOperationsTest](../tests/Feature/Service/AdminBlackListOperationsTest.php), [BlackListCacheInvalidationTest](../tests/Feature/Service/BlackListCacheInvalidationTest.php), [BlackListServiceKeyTest](../tests/Unit/Service/BlackListServiceKeyTest.php), [BlackListLogTest](../tests/Feature/Jobs/BlackListLogTest.php), [IpAddressTest](../tests/Unit/Support/IpAddressTest.php) |
| Rate-limited Redis failure logging | [RedisFailureLogger](../app/Support/RedisFailureLogger.php), [config/cache.php](../config/cache.php) | [RedisFailureLoggerTest](../tests/Unit/Support/RedisFailureLoggerTest.php), [RedisRecoveryAndPublicPagesTest](../tests/Feature/Degradation/RedisRecoveryAndPublicPagesTest.php) |
| Filament admin panel, access gates, and widgets | [AdminPanelProvider](../app/Providers/Filament/AdminPanelProvider.php), [User](../app/Models/User.php), [HorizonServiceProvider](../app/Providers/HorizonServiceProvider.php), [TelescopeServiceProvider](../app/Providers/TelescopeServiceProvider.php), [StatsOverview](../app/Filament/Widgets/StatsOverview.php), [HealthStatusWidget](../app/Filament/Widgets/HealthStatusWidget.php), [ProjectInfoWidget](../app/Filament/Widgets/ProjectInfoWidget.php) | [FilamentPanelTest](../tests/Feature/FilamentPanelTest.php), [AdminAccessGatesTest](../tests/Feature/AdminAccessGatesTest.php), [StatsOverviewWidgetTest](../tests/Feature/Filament/StatsOverviewWidgetTest.php), [TelescopeRedactionTest](../tests/Feature/TelescopeRedactionTest.php), [tests/e2e/filament](../tests/e2e/filament) |
| Filament resources (visitors, blacklists, logs) | [VisitorResource](../app/Filament/Resources/VisitorResource.php), [VisitorLogResource](../app/Filament/Resources/VisitorLogResource.php), [BlackListResource](../app/Filament/Resources/BlackListResource.php), [BlackListLogResource](../app/Filament/Resources/BlackListLogResource.php), [AdminOperationLogResource](../app/Filament/Resources/AdminOperationLogResource.php) | [VisitorResourceTest](../tests/Feature/Filament/VisitorResourceTest.php), [VisitorLogResourceTest](../tests/Feature/Filament/VisitorLogResourceTest.php), [BlackListResourceTest](../tests/Feature/Filament/BlackListResourceTest.php), [BlackListLogResourceTest](../tests/Feature/Filament/BlackListLogResourceTest.php), [AdminOperationLogTest](../tests/Feature/Filament/AdminOperationLogTest.php) |
| Transactional admin operation and auth auditing | [AdminOperationRecorder](../app/Service/AdminOperationRecorder.php), [AdminOperationObserver](../app/Observers/AdminOperationObserver.php), [AdminOperationContext](../app/Support/AdminOperationContext.php), [AdminAuthenticationContext](../app/Support/AdminAuthenticationContext.php), [RecordAdminAuthentication](../app/Listeners/RecordAdminAuthentication.php), [RecordAdminLogout](../app/Http/Middleware/RecordAdminLogout.php), [Login](../app/Filament/Auth/Login.php), [RecordsAdminActions](../app/Filament/Concerns/RecordsAdminActions.php), [CreateRecord](../app/Filament/Pages/CreateRecord.php), [EditRecord](../app/Filament/Pages/EditRecord.php), [ListRecords](../app/Filament/Pages/ListRecords.php), [config/admin-operation-logs.php](../config/admin-operation-logs.php), [AdminOperationLog](../app/Models/AdminOperationLog.php) | [AdminOperationRecorderTest](../tests/Feature/Service/AdminOperationRecorderTest.php), [AdminOperationCommitTest](../tests/Feature/Service/AdminOperationCommitTest.php), [GenericAdminOperationTest](../tests/Feature/Filament/GenericAdminOperationTest.php), [AdminAuthenticationLogTest](../tests/Feature/Filament/AdminAuthenticationLogTest.php) |
| Scheduled maintenance and CLI commands | [PruneAdminOperationLogsCommand](../app/Console/Commands/PruneAdminOperationLogsCommand.php), [ClearFilesCommand](../app/Console/Commands/ClearFilesCommand.php), [DeleteRedisCacheCommand](../app/Console/Commands/DeleteRedisCacheCommand.php), [DownloadBingWallpaperCommand](../app/Console/Commands/DownloadBingWallpaperCommand.php), [BingWallpaper](../app/Libraries/BingWallpaper/BingWallpaper.php), [MigrateAdminUsersCommand](../app/Console/Commands/MigrateAdminUsersCommand.php) | [PruneAdminOperationLogsCommandTest](../tests/Feature/Console/PruneAdminOperationLogsCommandTest.php), [ClearFilesCommandTest](../tests/Feature/Console/ClearFilesCommandTest.php), [DeleteRedisCacheCommandTest](../tests/Feature/Console/DeleteRedisCacheCommandTest.php), [DownloadBingWallpaperCommandTest](../tests/Feature/Console/DownloadBingWallpaperCommandTest.php), [BingWallpaperTest](../tests/Feature/Libraries/BingWallpaperTest.php), [MigrateAdminUsersCommandTest](../tests/Feature/Console/MigrateAdminUsersCommandTest.php) |
| Test database isolation, CI coverage gate, and release automation | [tests/TestCase.php](../tests/TestCase.php), [tests/CreatesApplication.php](../tests/CreatesApplication.php), [tests/bootstrap.php](../tests/bootstrap.php), [.github/workflows/tests.yml](../.github/workflows/tests.yml), [.github/workflows/release.yml](../.github/workflows/release.yml), [.github/scripts/ci-summary.py](../.github/scripts/ci-summary.py), [.github/scripts/release-notes.py](../.github/scripts/release-notes.py) | [TestDatabaseConnectionTest](../tests/Feature/TestDatabaseConnectionTest.php), [ConfigurationTest](../tests/Feature/ConfigurationTest.php), [.github/scripts/test_ci_summary.py](../.github/scripts/test_ci_summary.py), [.github/scripts/test_release_notes.py](../.github/scripts/test_release_notes.py) |

Follow the actual source and tests when locating a feature. Update this map when
module responsibilities or runtime paths change.

## Runtime paths and contracts

### Health probes and middleware isolation

- `GET /up` is Laravel's liveness probe. It verifies process responsiveness
  without querying the database or Redis.
- `GET /health/ready` ([routes/health.php](../routes/health.php)) is registered
  outside the `web` middleware group and returns a `JsonResponse` directly without
  resolving the session or redirector. It returns HTTP `200` (`status: "ready"`
  when both DB and Redis succeed, or `status: "degraded"` when only Redis is
  unreachable) and HTTP `503` (`status: "unhealthy"`) when the database fails.
- Both `/up` (configurable via `config('app.health_path')`) and `health/*`
  bypass `RecordVisitors` and `CountPvAndUv` middleware so probes never inflate
  traffic metrics or fail due to analytics dependencies.

### Visitor tracking, IP normalization, and blacklist enforcement

1. `RecordVisitors` is appended to the global middleware stack after `TrustProxies`
   and `PreventRequestsDuringMaintenance`, ensuring client IPs come only from
   configured `TRUSTED_PROXIES` and maintenance mode rejects traffic before
   tracking.
2. In non-local environments, the client IP is normalized via `IpAddress::normalize()`
   (canonical IPv4/IPv6 representation) and the request URI is stripped of query
   strings, fragments, and control characters via `SafeRequestUrl::sanitize()`
   (bounded to 2,048 bytes).
3. `BlackListService::checkIp()` queries the database directly via
   `IpAddress::findBlacklistRecord()` (using native `::inet` comparison on
   PostgreSQL and canonical/equivalent form matching on SQLite/MySQL) so stale
   Redis data can never allow a blocked IP or block an allowed IP. When matched,
   it dispatches `App\Jobs\BlackListLog` and aborts with HTTP `403`.
4. Allowed requests dispatch `App\Jobs\RecordVisitors` on the `record-visitor`
   queue. Both jobs deduplicate retries using a unique `event_id` UUID (falling
   back to the queue job UUID for legacy payloads) and re-sanitize URLs before
   inserting into `visitor_logs` or `black_list_logs`.
5. `RecordVisitors` only calls the GeoIP provider when creating a new `Visitor`
   row. `BoundedIPApi` caps upstream timeouts to at most 5 seconds and strips
   provider transport details from thrown exceptions. In `config/geoip.php`,
   cache tagging is enabled only when the active cache store supports tags,
   preventing `geoip:clear` from flushing `file` or `database` caches.

### Redis degradation and daily PV/UV statistics

- `CountPvAndUv` runs in the `web` middleware group, recording daily unique IPs
  in `uv_set_<YYYY-MM-DD>` and incrementing `pv_count_<YYYY-MM-DD>`. Both keys
  receive a fixed expiration timestamp via `VisitorStatisticsRetention::expiresAt()`
  (midnight 8 calendar days after the statistics date in the application timezone,
  leaving 7 complete days for retries).
- All Redis operations in request middleware, dashboard widgets, and cache
  invalidation degrade gracefully on failure and report rate-limited warnings
  through `RedisFailureLogger` (backed by the dedicated `redis_failure_logs` file
  cache store so Redis outages cannot break log throttling).
- `save:visits-count` (scheduled daily at `00:05`, with optional `--date=YYYY-MM-DD`
  within the 7-day retention window) reads the Redis snapshot, locks both `pv`
  and `uv` rows in `visitor_statistics` (`withTrashed()->lockForUpdate()`),
  rejects the pair if either incoming count is lower than the persisted value,
  and atomically upserts both rows in one database transaction before re-applying
  the fixed Redis expiration deadline.

### Transactional admin operation and authentication logs

- All Filament resource pages must extend `App\Filament\Pages\CreateRecord`,
  `App\Filament\Pages\EditRecord`, or `App\Filament\Pages\ListRecords` (enforced
  by `GenericAdminOperationTest`).
- Writable business models must declare an explicit safe snapshot policy in
  `config/admin-operation-logs.php` (`type`, `label`, `fields`, and optional
  `relationship_fields`). Infrastructure models (`AdminOperationLog`, `Visitor`,
  `VisitorLog`, `VisitorStatistics`, `BlackListLog`) are listed in `excluded`.
  Any Eloquent write to an unconfigured `App\Models\*` class while an admin
  audit context is active throws a `LogicException` before persistence.
- Snapshots are bounded to 16 KiB (`JSON_THROW_ON_ERROR`), max nesting depth 4,
  scalar/array values only, and recursively strip fields matching
  `password|token|secret|credential|cookie|authorization|email`.
- Single-record actions run inside a database transaction on the model's
  connection (`AdminOperationRecorder::run()`). Bulk actions commit per record
  so earlier records remain committed with their audit rows if a later record
  fails.
- Query-builder, pivot, or raw SQL mutations that bypass Eloquent model events
  must use `AdminOperationRecorder::explicit($subject, $action, $mutation, $relationshipSnapshot)`,
  which locks the parent row (`lockForUpdate()`), captures before/after state,
  and suppresses duplicate observer logs for the locked instance.
- `AdminBlackListOperations` serializes blacklist create/update/delete/restore
  operations, normalizes IPv6 addresses, checks equivalent forms under row locks,
  converts unique-index races into `ValidationException`, and schedules
  `afterCommit` Redis invalidation.
- Backend login (`App\Filament\Auth\Login`) records a `login` audit row only
  after authentication succeeds; if the audit insert fails, `clearNewAuthentication()`
  revokes the session and queued remember-me cookie and returns a validation
  error. Backend logout (`RecordAdminLogout`) always invalidates the local
  session and shows a warning notification if audit persistence fails.
- `AdminOperationLog` rejects Eloquent `updating` and `deleting` events. Old
  records are pruned only via `php artisan admin-operation-logs:prune --days=<30..36500> --force`
  (dry-run by default, deleting in batches of 1,000 via the query builder).

### Authorization and Telescope privacy

- In production (`app()->isProduction()`), `User::canAccessPanel()` as well as
  the `viewHorizon` and `viewTelescope` gates require a non-empty `ADMIN_EMAILS`
  allowlist (`config('app.admin_emails')`, normalized to lowercase). In
  non-production environments, an empty allowlist permits any authenticated
  `User` to access the Filament panel while `viewHorizon` and `viewTelescope`
  still check `admin_emails`.
- `TelescopeServiceProvider` redacts `_token` and Livewire `components` payloads
  in both request and response parameters across all environments, including
  `local`, so credentials inside Livewire component snapshots are never stored.

### Test isolation and coverage gate

- `tests/bootstrap.php` unlinks any cached `bootstrap/cache/{config,events,routes*}.php`
  files before PHPUnit boots the application.
- `tests/TestCase.php` enforces that `database.default` is `testing`, requiring
  either `:memory:` on `sqlite` or a dedicated database name matching
  `/^[a-zA-Z][a-zA-Z0-9_]*_test$/` on `mysql`/`mariadb`/`pgsql` that does not
  collide with any other configured connection.
- `tests/CreatesApplication.php` isolates the `redis_failure_logs` file cache
  path per test process.
- CI (`sqlite-tests` + `.github/scripts/ci-summary.py`) enforces 100% executable
  line coverage across `app/` by comparing raw `coveredstatements` and
  `statements` counts in `coverage.xml`.
- Browser CI runs three independent jobs for Chromium (desktop + Pixel 7),
  Firefox (desktop), and WebKit (desktop + iPhone 15). Each job provisions its
  own PostgreSQL/Redis services and authentication state; tests within a job
  retain one worker because they share the seeded database and admin session.
  Jobs install system dependencies only for their engine and cache browser
  binaries by OS, architecture, engine, and npm lockfile. System dependencies
  are installed even on cache hits. Reverb runs once in the Chromium job.
  `PLAYWRIGHT_SETUP_BROWSER` selects the same engine for the login dependency,
  so Firefox and WebKit jobs do not require an extra Chromium installation.
  The `browser-tests` matrix must all pass before `CI result` succeeds, and
  failure reports use engine-specific artifact names.
- Playwright starts Laravel with `serve --no-reload` so the PHP server retains
  the supplied test database and ephemeral Reverb environment overrides instead
  of reloading development values from `.env`.
- Tagging a commit reachable from `master` with a canonical `vX.Y.Z` tag runs
  [.github/workflows/release.yml](../.github/workflows/release.yml): it validates
  tag ancestry, runs the reusable `tests.yml` workflow, generates bilingual
  release notes via `.github/scripts/release-notes.py`, and publishes the GitHub
  release once all checks pass. Generation starts after CI succeeds. Sources are
  the complete titles and descriptions of merged PRs associated with commits in
  the actual tag range, plus full messages of unmatched commits, including merges.
  PRs are deduplicated; sources are neither compressed nor truncated. One model
  request produces structured English and Chinese entries. Local checks require
  valid structure, nonempty bilingual text and coverage of every source ID; they
  do not establish semantic correctness. The program renders source links and
  exact added, changed or removed Composer runtime/platform constraints.
  DNS/connect failures, HTTP 429 rate limits and HTTP 500/502/503/504 responses
  get at most three attempts with 2- and 4-second backoff; exhausted quotas,
  authentication errors, timeouts, incomplete responses and invalid generated
  content are not retried. Missing configuration or any generation/validation
  failure stops publication; there is no fallback or second AI review call.
  Requests exceeding the existing 1,000,000-byte transport limit fail without
  shortening sources. Public failure logs contain generic notices only.
  Manual `workflow_dispatch` runs skip CI and generate the `release-notes`
  artifact for preview without publishing.

## Documentation maintenance

Keep each fact in one owning reference and link to it elsewhere. Update this
document in the same PR when responsibilities, interfaces, runtime paths, or
failure behavior change. [Code style](code-style.md) owns naming, typing, and
test conventions; [README.md](../README.md) owns installation, configuration,
and operational usage.
