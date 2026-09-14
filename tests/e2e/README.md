# Browser acceptance tests

Run from the package root using the existing dependencies and Chromium:

```bash
npm run production
./tests/e2e/seed.sh
npx playwright test
```

The suite currently declares 120 tests in 18 spec files. The complete [form/control inventory and verification report](../../docs/FORM_CONTROL_AUDIT.md) maps every form, field, dropdown, modal, action form and navigation control to its route and coverage.

## Required health fixture

Every spec must import `test` from `./helpers.js`. Its automatic guard is active before the first page and watches every tab in the test context. Any page error, console error, CSP violation, or same-origin HTTP status of 400 or greater fails the test and attaches diagnostic JSON. Navigation and redirects cannot erase earlier failures. There are no exemptions.

The health-guard spec launches a child runner with one healthy case and four deliberate failures. It checks the guard's diagnostics, not merely the child runner's exit status. The child cases are outside normal spec discovery and never touch application data. The JavaScript import test also rejects a new spec using the unguarded Playwright test fixture.

Deliberate 403 authorization probes use `page.request` with the browser session and assert the response status/content. They are covered in more depth by PHPUnit with database snapshots. Normal UI traffic always stays guarded. Suggestion failure/retry tests use invalid JSON or an invalid suggestion value with a successful HTTP status; actual error statuses remain covered by PHPUnit and JavaScript HTTP tests.

## Isolation and external services

Playwright starts `serve.sh` on `127.0.0.1:8099`; `E2E_PORT` changes the port. A reused server must have been started by the current `serve.sh`. Keep `workers: 1` and do not run separate application suites against the same database concurrently.

The automatic database fixture invokes `seed.sh` before every test. This resets only `tests/e2e/.data`: the SQLite database, language files, file cache and sessions. The E2E-only provider in `TestServiceProvider.php` activates only with `INTERPRESSO_E2E=1`. PHPUnit remains SQLite in memory. Testbench's ordinary language directory is not used by the browser suite.

The base fixture supplies:

- Admin `admin@admin.com` / `aaaaaaaa`.
- Regular translator `translator@example.test` / `translator-password`, assigned English.
- English and German; six English translations with distinct boolean states; deletion enabled.

Use `test.use({ fixtureScenario: '...' })` for actor/type filters, examples, imports, bulk actions, notifications, running jobs, live/expired cron locks, pagination or model exports. `fixtures.php` is also exercised through PHPUnit HTTP tests. Seeding must reach its completion marker; Tinker sometimes exits successfully after printing an exception, so the helper checks the marker explicitly.

Jobs execute synchronously through real controllers/services. Import/export tests read real files; model export tests inspect the actual disposable JSON column after submitting the UI form. Mail uses the in-memory array transport. AI uses a deterministic external-service double, and suggestion timing/error cases intercept that response only. These tests do not send email or contact a paid AI service.

## Browser assertions

Use native roles, visible text, associated labels and stable field/modal IDs. Wait for form navigation before reload assertions. Multi-selects must actually open, check/uncheck their real controls and submit selections. Persisted outcomes are rechecked after reload. File/model exports also verify their real output.

The suite covers all nine settings Save buttons with JavaScript disabled. Theme tests hold the application script and inspect the first paint plus server HTML, so a later JavaScript correction cannot satisfy the saved-theme assertion. Legacy storage-only preferences still migrate at module initialization; persisted cookies are rendered by the server.

The polling test feeds visibility changes deterministically and controls time while exercising the real browser modules. Real active-batch cancellation is covered separately. Small JavaScript regressions also run with:

```bash
node --test tests/js/*.test.mjs
```

## Execution limitations

Chromium must launch and the Testbench server must bind a local port. A sandbox `Operation not permitted` when binding or a Chromium Mach port permission error blocks execution. Test discovery, seeding, PHPUnit and Node tests cannot establish a browser pass. The audit records the latest actual results. There are no skipped or `test.fixme` application scenarios hiding those limitations.

## Real queue completion

The `queued` scenario uses a database queue. `queue-progress.spec.js` submits the
approval form, consumes jobs using `bash tests/e2e/work.sh`, and verifies persisted
rows, notifications, completion, recovery after a fresh page load, stopped polling,
and cancellation. Other scenarios keep synchronous queue execution. The queue marker
is removed on every fixture reset. No queue/progress HTTP response is mocked in
these completion tests.

`process-lock.spec.js` exercises a live cron lease with empty queue tables and an
expired lease followed by a synchronous UI batch. It verifies the owner/start
warning, refusal to write, and clearing of every lock column after completion.
