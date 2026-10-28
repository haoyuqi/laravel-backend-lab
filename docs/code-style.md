# Code style

PHP 8.4.27+ is the CI baseline (Composer also permits PHP 8.5.12+ within PHP 8.x).
Run Laravel Pint (`./vendor/bin/pint --test`) with the default Laravel preset
both locally and in CI. `declare(strict_types=1)` declarations are optional and
are not enforced by Pint.

## Naming

- Classes, traits, interfaces, enums, and matching filenames use `PascalCase`.
- Variables, parameters, properties, and methods use `camelCase` (preserve
  existing snake_case variables only where required by legacy view/Blade contracts
  such as `$init_data`).
- Constants use `UPPER_SNAKE_CASE`.
- Database tables, columns, configuration keys, route parameters, and structured
  log context keys use `snake_case`.
- Preserve names and signatures required by Laravel, Filament, Livewire, and
  vendor contracts.

## Types and readability

Declare parameter, property, and return types when their contracts are known.
Check parent signatures before adding types to framework or Filament overrides.
Use PHPDoc for collection element types and array shapes that PHP cannot express
natively.

Prefer early returns, `match` expressions for simple mappings, and focused helper
methods over deep nesting. Keep business mutations and locking inside services
rather than Filament resource schemas or controllers. Do not extract single-use
wrappers that obscure transaction or lock boundaries.

In frontend assets (`resources/js/`, `resources/sass/`), keep Vue 3 components
focused on presentation and Echo channel lifecycle cleanup (`leaveChannel` on
unmount). Never place secrets such as `REVERB_APP_SECRET` in `VITE_*` variables.

## Tests

- Feature and unit tests live under `tests/Feature` and `tests/Unit`; Playwright
  browser specs and configurations live under `tests/e2e/`.
- Test methods use `test_descriptive_snake_case`; helpers and data providers use
  `camelCase`.
- Every executable line under [app/](../app/) is included in the CI 100%
  line-coverage gate (`.github/scripts/ci-summary.py`). Exercise behavior through
  meaningful tests rather than coverage annotations, and ensure driver-specific
  branches (`pgsql` vs. `sqlite`/`mysql`) are covered so the suite achieves 100%
  line coverage regardless of the active test database driver.
- Tests must never make live external network requests. Mock HTTP clients
  (`GuzzleHttp\Client`) or service contracts when testing GeoIP or Bing wallpaper
  integrations.
- When a test mocks the `Redis`, `Cache`, `DB`, or `Queue` facade or mutates
  global state (`Telescope`, `TrustProxies`, `config`), restore the original
  state in `tearDown()` so later tests remain isolated.
