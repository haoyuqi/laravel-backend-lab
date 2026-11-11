# Contributing

## Branches and commits

`master` is the only long-lived branch. Create short-lived branches from
`master` and open pull requests against it. GitHub deletes merged branches.

Branch names use `<type>/<issue-number>-<kebab-case-description>`, or
`<type>/<kebab-case-description>` when there is no issue. Supported types:
`feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `chore`.

Commit messages and pull request titles use Conventional Commits:
`<type>(<scope>): <imperative summary>`, lowercase English, no trailing period,
within 72 characters.

## Local development

Install dependencies and start Vite:

```bash
composer install
npm ci
npm run dev
```

Create an optimized build with `npm run build`. Laravel Mix commands from older
releases no longer exist.

## Verification

Run the applicable checks before opening a pull request:

```bash
./vendor/bin/pint --test
env TEST_DB_CONNECTION=sqlite TEST_DB_DATABASE=:memory: php artisan test
PYTHONDONTWRITEBYTECODE=1 python3 -m unittest discover -s .github/scripts -p 'test_*.py'
npm run build
```

Coverage must remain at 100% for `app/`; see [testing](docs/testing.md). When
changing frontend components, Filament interactions, or Reverb broadcasting,
also run `npm run test:e2e` and `npm run test:reverb`.

Run PHP, Composer, Artisan, PHPUnit, Pint, Node, npm, and Playwright inside the
Laradock `workspace` container as the `laradock` user. Git inspection and staging
may run on the host.

## Container build

```bash
docker build --build-arg APP_VERSION=0.0.0 \
  --build-arg VCS_REF="$(git rev-parse HEAD)" \
  -t laravel-backend-lab:local .
```

The build uses `composer.lock` and `package-lock.json`, needs network access for
Composer, npm, and Alpine packages, and does not include development
dependencies. See [container installation](docs/container.md) for runtime
behavior.

## Pull requests

Use the pull request template. Describe the final behavior, list runtime
requirements, and record the verification you actually ran. Under **Upgrade
impact**, state whether the change needs an [UPGRADE.md](UPGRADE.md) entry:
entries are required only for action-required or breaking changes such as
migrations, removed environment variables, or manual deployment steps. Routine
changes belong in the generated release notes.

Never commit credentials, `.env` files, or application keys.
