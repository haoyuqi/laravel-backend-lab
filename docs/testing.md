# Testing

## Default suite

The default test suite uses a dedicated `testing` connection backed by an
in-memory SQLite database. It never touches the primary development database.

```bash
php artisan test
./vendor/bin/pint --test
```

CI requires every executable line under `app/` to be covered. The gate compares
raw covered and total line counts, so a rounded percentage cannot satisfy it; a
missing coverage report also fails CI. Run the coverage command used in CI:

```bash
env TEST_DB_CONNECTION=sqlite TEST_DB_DATABASE=:memory: \
  php -d pcov.enabled=1 -d pcov.directory=app \
  ./vendor/bin/phpunit --coverage-text
```

## Databases

To run the suite against MySQL or PostgreSQL, provide the dedicated `TEST_DB_*`
variables. The test database name must contain only letters, numbers, and
underscores, end in `_test`, and differ from the application database. Laravel
creates it on the first run; the server must already be running and the account
must be allowed to create databases.

```bash
TEST_DB_CONNECTION=mysql \
TEST_DB_HOST=127.0.0.1 \
TEST_DB_PORT=3306 \
TEST_DB_DATABASE=laravel_test \
TEST_DB_USERNAME=root \
TEST_DB_PASSWORD='' \
php artisan test
```

Use `TEST_DB_CONNECTION=pgsql`, port `5432`, and the PostgreSQL service
credentials for PostgreSQL. Inside Laradock's `workspace` container, set
`TEST_DB_HOST=postgres`. A fresh clone defaults to in-memory SQLite and needs no
database server; dependencies, an application key, built assets, and Redis for
the tests that use it still apply.

## Browser tests

Playwright covers the public pages, the Filament panel, and Reverb. Configure a
persistent `testing` connection (for example a dedicated `*_test` database),
then migrate and seed it:

```bash
php artisan migrate --database=testing
php artisan db:seed --class=E2EDataSeeder --database=testing
npm run test:e2e
```

The end-to-end seeder refuses to run unless `APP_ENV` is `local` or `testing`, so
the committed test administrator cannot be created in staging or production.

The default run covers five desktop and mobile configurations. For a quicker
local check:

```bash
npm run test:e2e -- --project=chromium --project="Mobile Chrome"
```

Reverb has its own real-WebSocket configuration:

```bash
npm run test:reverb
```

See [real-time updates](realtime.md#verification) for its port and credential
requirements.

## CI

`.github/workflows/tests.yml` runs:

- release-script and container-image unit tests;
- Pint;
- a production installation smoke test;
- SQLite tests with the exact coverage gate;
- a MySQL 8.0/8.4 and PostgreSQL 17/18 matrix;
- security and dependency audits;
- Chromium, Firefox, and WebKit browser jobs;
- a container build that verifies startup safety, admin access, background
  entry points, backups, and volume persistence.

Every job must pass before the aggregate `CI result` succeeds. See
[test isolation and coverage](architecture.md#test-isolation-and-coverage-gate)
for service and cache boundaries.
