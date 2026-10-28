# Repository development guide

This guide supplements global agent instructions. Follow the user's requirements
for the runtime environment, safety, and authorization.

## Reading order

- [README.md](README.md): setup, environment configuration, real-time Reverb
  usage, and operational commands.
- [docs/architecture.md](docs/architecture.md): feature map, module
  responsibilities, audit transactions, and Redis degradation rules.
- [docs/code-style.md](docs/code-style.md): PHP, Filament, frontend, and testing
  conventions.
- [UPGRADE.md](UPGRADE.md): release migration and rollback procedures.

Read the relevant reference and verify it against the source before changing
code. When responsibilities, interfaces, execution paths, or failure handling
change, update the owning documentation in the same change. Avoid maintaining
the same facts in multiple places.

## Change boundaries and tripwires

- **Execute in the container.** Run PHP, Composer, Artisan, PHPUnit, Pint, Node,
  npm, and Playwright commands inside the Laradock `workspace` container as user
  `laradock` (`/var/www/laravel-backend-lab`). Git inspection and staging may run
  on the host.
- **Admin audit invariants.** New Filament resource pages must extend
  `App\Filament\Pages\CreateRecord`, `EditRecord`, or `ListRecords`. Writable
  business models require an explicit safe snapshot policy in
  `config/admin-operation-logs.php`; infrastructure models stay in `excluded`.
  Custom write services and bulk callbacks must run inside
  `AdminOperationRecorder::run()` on the model's connection, and query-builder
  or pivot writes must use `AdminOperationRecorder::explicit()`.
- **Redis authority and degradation.** Blacklist checks (`BlackListService::checkIp`)
  query the database directly so stale cache data never alters an access
  decision. PV/UV counting, visitor job dispatch, and dashboard stats must
  degrade gracefully when Redis is unavailable and log rate-limited warnings via
  `RedisFailureLogger`.
- **Health probe isolation.** `/up` and `/health/ready` must bypass session
  middleware, `RecordVisitors`, and `CountPvAndUv`.
- **Privacy and redaction.** Sanitize logged request paths with
  `SafeRequestUrl::sanitize()` (no query strings or fragments). Preserve
  Telescope redaction of `_token` and Livewire `components` envelopes in all
  environments. Never expose secrets in `VITE_*` variables or `.env` files.
- **Test database guard and 100% coverage.** Tests must run on the `testing`
  connection (`:memory:` SQLite or a dedicated `*_test` database). CI enforces
  100% executable line coverage across `app/` (`1377/1377` lines); cover
  driver-specific branches so coverage holds under both SQLite and PostgreSQL.
- **E2E consolidation.** Keep all Playwright specs, configurations
  (`playwright.config.ts`, `playwright.reverb.config.ts`), auth state, and
  reports inside `tests/e2e/`.

## Verification

Run the applicable checks in the `workspace` container before claiming
completion:

```bash
php -d zend.assertions=-1 -d pcre.jit=0 ./vendor/bin/pint --test
env TEST_DB_CONNECTION=sqlite TEST_DB_DATABASE=:memory: php -d pcov.enabled=1 -d pcov.directory=app ./vendor/bin/phpunit --coverage-text
./vendor/bin/phpunit
python3 -m unittest discover -s .github/scripts -p 'test_ci_summary.py'
npm run build
```

When changing frontend components, Filament interactions, or Reverb broadcasting,
also run `npm run test:e2e` and `npm run test:reverb`. Report the checks
actually run, their results, and any unverified scope.

## Git workflow

- `master` is the only long-lived code branch. Create short-lived branches from
  `master` and open pull requests targeting `master`.
- Branch names use `<type>/<issue-number>-<kebab-case-description>` (or
  `<type>/<kebab-case-description>` when no issue exists). Supported types:
  `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `chore`.
- Commit messages and pull request titles follow Conventional Commits:
  `<type>(<scope>): <imperative summary>` in lowercase English, without a
  trailing period, within 72 characters.
- Do not commit, push, or open/merge a pull request without explicit user
  authorization.
