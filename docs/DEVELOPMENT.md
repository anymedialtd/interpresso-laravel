# Development

## Requirements

- PHP 8.2+ (8.3+ to test against Laravel 13)
- Composer
- Node.js ^20.19 || >=22.12 (required by Vite 8) + npm

## Install

```bash
composer install
npm install
```

## Local Checks

```bash
./vendor/bin/phpunit
./vendor/bin/phpstan analyse --no-progress
composer audit
npm audit
npm run production
node --test tests/js/*.test.mjs
```

## Notes

- Browser acceptance tests use the existing standalone Testbench harness. Run
  `./tests/e2e/seed.sh` followed by `npx playwright test`. See the
  [E2E guide](../tests/e2e/README.md) for fixture isolation, UI selector rules,
  coverage, and the active regression cases. The [form/control audit](FORM_CONTROL_AUDIT.md)
  maps every view control to its route and tests, and records execution limitations.
  Every spec must import the automatic health fixture from `tests/e2e/helpers.js`.
  No browser scenarios are skipped.

- Testbench is configured in `tests/BaseTestCase.php`. Its harness excludes the removed UI packages from discovery, so stale local vendor files do not provide a runtime during tests.
- Tests force SQLite in-memory and `queue.default=sync` to make setup deterministic.
- Front-end assets are built with Vite (`vite.config.mjs`).
- Tailwind 4 configuration lives in `resources/css/app.css`: DaisyUI 5 supplies CSS components and light/dark themes, and `@tailwindcss/typography` styles the manual. There is no Tailwind JavaScript config or component JavaScript runtime.
- After the DaisyUI migration, run `npm install` to install the new dependency and regenerate `package-lock.json`, then `npm run production` before running browser checks or publishing assets. The migration edits do not refresh the lockfile or generated assets without the dependency installed.
- Dropdowns retain the existing `data-toggle` handlers and `hidden` state. Their `dropdown-open` class lets those handlers control visibility without depending on focus. The translation editor uses a native dialog with a `modal-box`; outside-click detection measures the box. The theme composer renders the preference cookie before paint; `theme.js` syncs toggles and legacy `localStorage["color-theme"]`. CSS supplies the initial system-theme fallback.
- Generated assets are in `public/css/app.css` and `public/js/app.js`.
- Strict CSP is enabled for package web routes by default. Keep scripts and styles
  in external assets, including theme setup; use escaped data attributes for
  server-provided browser data. Table containers use the two existing widths,
  `max-w-[1400px]` and `max-w-[1920px]`. The global browser guard records JavaScript
  errors, console errors, CSP violations and same-origin HTTP errors before
  navigation and retains failures across redirects and secondary tabs.

## Release verification

The CI workflow in `.github/workflows/ci.yml` runs on pushes and pull requests with
read-only repository permissions and action commit pins. It resolves Testbench 10
for Laravel 12 on PHP 8.2/8.3/8.4 and Testbench 11 for Laravel 13 on PHP 8.3/8.4,
overriding the repository's Composer platform floor for each job.

Run the ordinary SQLite in-memory suite with `./vendor/bin/phpunit`.
`HttpControlCoverageTest` checks real UI-dispatched batches, database/file/model
changes, batch completion and persisted administrator notifications.
`QueueLifecycleTest` consumes real database jobs to check progress, cancellation,
failures and every gated action. `ConsoleCommandsTest` exercises the seven portable
commands, force variants, guards and actual database/mail notification delivery.

The eighth command, `interpresso:developer-download`, uses MySQL-specific foreign-key
statements. Its success, pagination, file/DB modes and failure rollback are tested
in the separate MySQL suite, together with migration up/rollback/reapply and
identifier inspection in `information_schema`:

```bash
DB_DATABASE=interpresso_ci DB_HOST=127.0.0.1 DB_PORT=3306 \
DB_USERNAME=languages DB_PASSWORD=languages \
./vendor/bin/phpunit -c phpunit.mysql.xml
```

This suite rebuilds the disposable `interpresso_ci` database. CI creates it in a
MySQL 8.4 service container. The suite explicitly removes the redundant foreign-key
index before rolling back the composites, reproducing the failure SQLite cannot detect.

Build and browser checks use the existing installed dependencies:

```bash
composer validate --strict
composer audit
./vendor/bin/phpstan analyse --no-progress
npm audit
npm run production
node --test tests/js/*.test.mjs
./tests/e2e/seed.sh
npx playwright test
```

`tests/e2e/queue-progress.spec.js` submits real controls with a database queue and
uses `bash tests/e2e/work.sh --once` / `bash tests/e2e/work.sh` to advance it. It checks
0/50/100 progress, stopped polling, discovery on a new page, completed rows and
notifications, and cancellation followed by an allowed mutation. The `queued`
fixture selects database queueing through an E2E-only marker file; ordinary fixtures
retain synchronous jobs. Browser tests also require permission to bind/connect to
the local Testbench server and launch Chromium.
