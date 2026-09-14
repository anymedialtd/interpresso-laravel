# Troubleshooting

## Cannot Login to Translator UI

Checks:

- Route prefix and login URL (`config/interpresso.php`)
- Translator user exists in `interpresso_translators`
- Correct guard config (`interpresso_translator`)

Default first admin (if untouched):

- `admin@admin.com / aaaaaaaa`

## DB Tables Missing During Tests

Use package testbench defaults:

- SQLite in-memory
- `interpresso.db_connection = testbench`
- `queue.default = sync`

Reference: `tests/BaseTestCase.php`

## Jobs Not Processing

Symptoms:

- `process_running` stays true
- imports/exports do not complete

Checks:

- A long-running worker or `interpresso.schedule.queue_worker` enabled with the every-minute scheduler cron for an asynchronous queue; bulk HTTP actions refuse sync
- Queue tables exist (`jobs`, `job_batches`, `failed_jobs`)
- queue name matches config (`interpresso.queue_name`)

## A Process Lock Blocks Work

The warning reports the owner (host, PID, operation) and start time. Inspect and
clear an expired lease with `php artisan interpresso:unlock`. A live lease is
refused unless `--force` is supplied. Stop or verify the old process before forcing
release. Unlock does not terminate a process or remove queued jobs/batches.

Expired leases and legacy `process_running=true` rows without expiry never block
on their own. Long imports renew between files and model chunks; use the acquired
`ProcessLock::refresh()` handle in custom long operations. Configure
`INTERPRESSO_PROCESS_LOCK_TTL` above the longest uninterrupted step, default 900
seconds. Use **Delete running Batch (Jobs)** for abandoned queued batches; it
leaves an independent cron lease alone.

## Exports Not Writing Files

Checks:

- File permissions in language directories
- `db_loader` behavior in settings
- export conditions: translation must be approved and not marked updated

PHP exports now expand dotted keys into nested arrays. Existing installations in
file mode can run `php artisan interpresso:export-translations --force=1` to rewrite
approved translations already marked exported. Matching old flat dotted entries
are removed during export. JSON keys remain literal.

If an export job refers to a deleted translation or target model, it raises a
model-not-found exception instead of failing on null. Restore the required record
or remove the obsolete translation before retrying. Null namespaces/groups are
reported as invalid metadata; application translations use `''` for the namespace
and JSON translations use `''` for the group. Model exports require a model class
and an existing column. Copying missing translations rejects null source text with
a descriptive package exception. Translation updates require an acting translator.

Bulk creation failures log the actual source path (`lang/en/messages.php` or
`lang/vendor/package/en/messages.php`, and `lang/en.json` for JSON), including when
the insertion array uses string keys.

## Settings Row Missing

`Setting::getCached()` and cache refreshes throw
`MissingSettingsException` when the settings table has no row. Restore the saved
row on the configured connection/table, then refresh the settings cache. No default
row is silently inserted. Loader registration uses Laravel's file loader while the
row is absent; this does not change database-outage handling in DB mode.

## OpenAI Keeps the Original Text

The response must be a JSON object containing exactly the requested keys and only
string values. Missing/extra keys, nulls, numbers, booleans, nested values and lists
cause the entire original input to be returned. Missing language records or null
text skip the request. Valid responses preserve the caller's key order, including
empty strings and `"0"`.

## API Sync Fails Across Hosts

Checks:

- Same `INTERPRESSO_API_SHARED_SECRET` on all hosts
- Correct `INTERPRESSO_MAIN_SERVER_DOMAIN`
- `domains` list in settings uses full scheme (`http://` or `https://`)
- network/firewall allows requests

## UI Controls or Polling Do Not Work

Build and republish `interpresso-public`, then clear overridden templates that use the
old component UI. Navigation, search and filters use GET page loads; mutations use
POST forms with CSRF tokens. A 419 response requires refreshing the session.
Settings save one field per page reload. Save Domains before enabling multi-host.

Only batch progress, notifications, modal data and AI suggestions use JSON requests.
Polling pauses in hidden tabs; batch polling ends on completion or cancellation.
The modal's AI result is a draft until explicitly saved. When a draft has been edited,
use the separate suggestion preview to replace it.

## NPM Audit / Composer Audit Failures

Run:

```bash
composer update --with-all-dependencies
npm install
npm audit fix
```

Then verify:

```bash
composer audit
npm audit
./vendor/bin/phpunit
```

## Composer Blocks Laravel 9, 10 or 11

Symptom:

- Composer reports `laravel/framework` is blocked by security advisories.

Notes:

- Every released version of Laravel 9, 10 and 11 is affected by unresolved advisories.
- `anymedialtd/interpresso-laravel` therefore requires `^12.0 || ^13.0`. Laravel 9/10/11 are no longer supported.
- `anymedialtd/interpresso-laravel` does not hard-require `openai-php/laravel`; OpenAI is optional.

Resolution:

- Upgrade the host app to Laravel 12 or 13.

## Bulk action says no background queue is configured

The configured queue connection uses `sync`, `null`, `deferred`, missing settings, or an unsafe failover path. Interpresso refuses bulk HTTP work before changing data. Run the exact Artisan command in the toast, or configure a database/Redis connection and a worker on `languageProcessor`. Without Supervisor, the recommended shared-hosting setup is `QUEUE_CONNECTION=database` plus `interpresso.schedule.queue_worker` and [one every-minute scheduler cron line](CONFIGURATION.md#cron-without-supervisor); the UI buttons then work normally. Rebuild cached configuration after changes. A deferring driver alone does not prove a worker or cron is running. The peer force-export API returns HTTP 503 with the same CLI and scheduler guidance. See [supported execution modes and cron](CONFIGURATION.md#supported-execution-modes).
