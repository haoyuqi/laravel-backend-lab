# v3.0.0 — Filament 5, Reverb and Admin Audit Logs

This major release upgrades the administration stack, replaces the real-time
transport, and adds transactional admin audit history and safer visitor
statistics persistence. Laravel remains on 13.x.

## Breaking changes

- PHP 8.4+ is required for the web application, CLI and background workers.
- Filament 3 is upgraded to 5 and Livewire 3 to 4. Private admin components and
  plugins need compatibility review. Build the new Tailwind 4 admin theme.
- Laravel Reverb and the Pusher protocol replace Socket.IO and Redis
  broadcasting. Existing Socket.IO services and frontend settings must be
  replaced; Redis is still required by statistics and blacklist features.

## Features and fixes

- Admin operation logs record successful backend login/logout and blacklist
  changes. Business changes and audit records share a database transaction;
  an audit write failure rolls back the affected change. Login is denied if
  its audit write fails; logout remains possible with a warning during audit
  storage failure. Logs are read-only in the admin and retained until manually
  pruned. This is not a tamper-proof audit system.
- Daily visitor statistics use atomic upserts and unique type/date pairs.
  Duplicate snapshots are archived before deduplication. Dated retries,
  fixed Redis retention deadlines and guards against decreasing persisted
  snapshots make retries safer; this does not recover visits lost during an
  outage.
- CI covers PHP 8.4 with SQLite, MySQL and PostgreSQL, frontend builds and
  browser tests, including actual Reverb delivery and reconnection.
- Repository automation has scoped permissions and restricted Dependabot
  auto-merging. A vulnerable source-map-js dependency is patched.

## Upgrade

Read the [v2.2.1 → v3.0.0 upgrade guide](https://github.com/haoyuqi/laravel-backend-lab/blob/v3.0.0/UPGRADE.md#v221-to-v300)
before deployment. Back up the database and rehearse with production-like data.
Pause background writers as well as web traffic while migrating.

Two database migrations are included:

1. `2026_10_02_000000_make_visitor_statistics_unique.php` archives duplicate
   statistics and creates the daily unique constraint. Counts are not summed;
   consult the guide for the exact snapshot selection and recovery rules.
2. `2026_10_11_000000_create_admin_operation_logs_table.php` creates audit
   storage. Run it before allowing backend login or audited admin writes.

Install the committed dependency locks, configure Reverb or disable live
updates explicitly, rebuild frontend assets, restart background processes and
set `APP_VERSION=3.0.0`. Do not regenerate the existing `APP_KEY` or run seeds.
Rollback must preserve audit history and account for deduplicated statistics;
removing the unique constraint alone does not restore the original data.

## Related changes

- [Visitor statistics reliability (#150)](https://github.com/haoyuqi/laravel-backend-lab/pull/150)
- [PHP 8.4 (#152)](https://github.com/haoyuqi/laravel-backend-lab/pull/152)
- [Filament 5 and Livewire 4 (#151)](https://github.com/haoyuqi/laravel-backend-lab/pull/151)
- [Repository security (#153)](https://github.com/haoyuqi/laravel-backend-lab/pull/153)
- [CI and dependency improvements (#154)](https://github.com/haoyuqi/laravel-backend-lab/pull/154)
- [Admin operation and authentication logs (#155)](https://github.com/haoyuqi/laravel-backend-lab/pull/155)
- [Laravel Reverb (#156)](https://github.com/haoyuqi/laravel-backend-lab/pull/156)

[Full comparison](https://github.com/haoyuqi/laravel-backend-lab/compare/v2.2.1...v3.0.0)
