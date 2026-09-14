# Configuration

Main config file:

- `config/interpresso.php`

## Environment Variables

### Core

- `INTERPRESSO_ENABLED` (default: `true`)
- `INTERPRESSO_SECURITY_HEADERS_ENABLED` (default: `true`)
- `INTERPRESSO_MAIN_SERVER_DOMAIN` (default: `config('app.url')`)
- `INTERPRESSO_DB_CONNECTION` (default: `config('database.default')`)

### Multi-Host / API Sync

Single-project installs need no shared hosts or API secret. The Settings toggle
`enable_multi_host` defaults to `false` on new installs.

- `INTERPRESSO_API_SHARED_SECRET`
- `INTERPRESSO_MULTIPLE_DB_HOSTS` (comma-separated host list)

To coordinate multiple installations, save the shared URLs in **Settings > Domains**,
including `http://` or `https://`, and turn on **Enable multi-host coordination**.
Configure the same `INTERPRESSO_API_SHARED_SECRET` on the participating hosts.
The environment host list remains a fallback when the saved `domains` value is `null`;
it does not enable multi-host coordination by itself.

When the toggle is off, job gating checks only local `jobs` and `job_batches`.
Job checks, exports and job cancellation send no requests to shared hosts, even if
domains remain saved. API endpoints remain registered with their existing contracts.

The upgrade migration enables multi-host for existing settings where `domains` is
neither `NULL` nor empty after `TRIM(domains)`, preserving configured installations.
Rows with null, empty or space-only domains stay disabled. The migration also clears
cached settings so the backfilled value takes effect.

### OpenAI

OpenAI integration is optional.
Install `openai-php/laravel` only if you want automatic translation suggestions.

In `config/openai.php`:

- `OPENAI_API_KEY`
- `OPENAI_ORGANIZATION`
- `OPENAI_REQUEST_TIMEOUT` (default: `30`)

## Important Config Keys

### Routes

- `prefix` (default: `translator`)
- `languages_url`, `translations_url`, `translators_url`, `settings_url`, `login_url`

### Tables

- `table_languages` (default: `interpresso_languages`)
- `table_translations` (default: `interpresso_translations`)
- `table_translators` (default: `interpresso_translators`)
- `table_settings` (default: `interpresso_settings`)
- `table_translator_language` (default: `interpresso_language`)

### Queue

- `queue_name` (default: `languageProcessor`)
- `batch_name` (default: `languageBatch`)
- `prune_batch_hours` (default: `24`)
- `process_lock_ttl` (default: `900` seconds, `INTERPRESSO_PROCESS_LOCK_TTL`)
- `queue_worker.max_time` (default: `50` seconds)
- `queue_worker.max_jobs` (default: `100` jobs)
- `queue_worker.memory` (default: `128` MB)
- `queue_worker.timeout` (default: `60` seconds per job)
- `schedule.queue_worker`, `schedule.prune_batches`, `schedule.pending_notifications` (each default: `false`)

The database advisory lease coordinates cron, artisan, single-row HTTP mutations and queued
batches on `interpresso.db_connection`. Long imports heartbeat between files and
model chunks. Custom long operations should call `refresh()` on the acquired
`ProcessLock` handle before expiry; set the TTL above their longest uninterrupted
step. Use `php artisan interpresso:unlock` to inspect/clear expired leases, or
`--force` to clear a live lease after stopping the old process.

### Supported execution modes

Long-running translation work never runs inside an HTTP request, including after the response is flushed. Before any batch writes or lease acquisition, the UI and peer force-export endpoint inspect `queue.default` and `queue.connections.<connection>.driver`. Connection aliases are supported. `sync`, `null`, missing configuration, and Laravel's `deferred` driver are refused; failover is refused if any fallback is unsafe or cyclic. A configured asynchronous driver does not prove a worker is alive: queued jobs wait until one consumes them.

Choose one of these three supported modes:

1. **Supervisor / a long-running worker: best for real-time processing.** Set `QUEUE_CONNECTION=database` (or `redis`) and supervise a worker consuming `languageProcessor`, or your `interpresso.queue_name`. For example, run `php -d max_execution_time=0 artisan queue:work --queue=languageProcessor --timeout=900 --tries=1`, set the connection's `retry_after` above the job timeout (for example 960 seconds), and set `INTERPRESSO_PROCESS_LOCK_TTL=1800`. For SQS configure the equivalent visibility timeout. Size limits for the longest job and queue wait; inspect `failed_jobs` and application logs on failure.
2. **No Supervisor: recommended for shared hosting.** Set `QUEUE_CONNECTION=database`, enable `interpresso.schedule.queue_worker`, and add the single every-minute cron line below. The UI buttons work normally. Cron starts a bounded worker and drains ready jobs within a minute; long jobs and backlogs may continue over later ticks. No Supervisor installation or permanently running worker is needed.
3. **Sync: small installations only.** With `QUEUE_CONNECTION=sync`, use the UI for browsing and individual edits/reviews. The UI refuses long operations and names the matching Artisan command; run that command manually in the CLI. PHP CLI memory/time limits and hosting limits still apply. Small size never enables inline HTTP bulk work.

After changing queue configuration, rebuild the host's configuration cache if used (`php artisan config:cache`) and restart any long-running workers. With `null`, queued notifications are discarded.

A refused bulk action names its exact CLI replacement, for example `php artisan interpresso:import-translations`, and explains how a database queue plus the scheduler makes the button work. No data is written, no batch starts, and no operation lease is acquired. Update-and-auto-translate shows the same queue/scheduler setup guidance before saving the root draft. Single-row editing and approval remain available.

The authenticated peer force-export API returns HTTP 503 and a JSON `message` naming `php artisan interpresso:export-translations-deployment`, with the same queue/scheduler setup guidance. Guards run before data writes, batch creation or operation lease acquisition. See the [manual command mapping](APPLICATION_MANUAL.md#background-jobs-and-queues).

### Cron without Supervisor

**This is the recommended setup for shared hosting.** In the host application's environment, enable the package worker schedule and use a persistent cache:

```dotenv
QUEUE_CONNECTION=database
INTERPRESSO_SCHEDULE_QUEUE_WORKER=true
CACHE_STORE=file
```

This enables `interpresso.schedule.queue_worker`; its default is `false`. On upgrades, add any missing options to the published configuration instead of overwriting local settings. Run `php artisan migrate` if queue/batch tables are missing, and rebuild cached configuration with `php artisan config:cache`. Add exactly one cron line, replacing the application path and PHP binary as needed:

```cron
* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

The cron user must be able to run PHP CLI/background processes and write the application's storage and export paths. Confirm the registered schedule and test one drain manually:

```bash
php artisan schedule:list
php artisan interpresso:work
```

`interpresso:work` wraps `queue:work` for `interpresso.queue_name` on the default connection, with `--stop-when-empty`, `--max-time=50`, `--max-jobs=100`, `--memory=128`, `--timeout=60`, `--sleep=0` and `--tries=1`. An empty queue exits 0 immediately. Work left after a budget is reached is picked up on the next tick; delayed/reserved jobs remain for later runs.

Configure `interpresso.queue_worker.max_time` (seconds), `interpresso.queue_worker.max_jobs`, `interpresso.queue_worker.memory` (MB), and `interpresso.queue_worker.timeout` (seconds per job). Their environment variables are `INTERPRESSO_QUEUE_WORKER_MAX_TIME`, `INTERPRESSO_QUEUE_WORKER_MAX_JOBS`, `INTERPRESSO_QUEUE_WORKER_MEMORY`, and `INTERPRESSO_QUEUE_WORKER_TIMEOUT`. All must be positive integers; zero/unlimited and malformed values are rejected. Time and memory budgets are checked between jobs. PHP CLI needs PCNTL for Laravel to interrupt a stuck job at its timeout; otherwise a hosting process limit is needed to bound a stuck job. Configure finite network timeouts too. Keep `retry_after` above the job timeout (or set SQS visibility accordingly) and `interpresso.process_lock_ttl` above the longest uninterrupted job and expected queue wait.

The worker runs every minute in the background with `withoutOverlapping`. Its cache lock expires after `ceil((max_time + timeout) / 60) + 1` minutes, three minutes with defaults, allowing the last job to finish. Normal completion releases it earlier. Use a persistent cache such as file storage on one host, or a shared cache across hosts, never an in-memory array/null store. This scheduler cache lock prevents overlapping cron workers. The separate database `ProcessLock` protects translation operations across HTTP, CLI and batches; both are needed. Manual worker invocations are not protected by the scheduler mutex.

The same configuration block offers independent opt-ins: `interpresso.schedule.prune_batches` runs `interpresso:prune-batches` every minute; `interpresso.schedule.pending_notifications` runs `interpresso:send-automatic-pending-translations-notification` daily at midnight in the scheduler timezone. Enable them with `INTERPRESSO_SCHEDULE_PRUNE_BATCHES=true` and `INTERPRESSO_SCHEDULE_PENDING_NOTIFICATIONS=true`. Both default to `false`, even when the worker is enabled. Maintenance runs before a newly scheduled worker; existing operation locks can still make a maintenance invocation skip. Automatic reminders also require the saved `enable_automatic_pending_notifications` setting and a working mail transport. That setting is checked when the command runs, so schedule registration needs no settings-table read.

All three package schedules are omitted when the configured driver cannot defer work, including sync/null/deferred/missing or unsafe failover connections. They do not schedule imports or approvals themselves: administrators continue to use the UI normally. To disable the cron worker, set `INTERPRESSO_SCHEDULE_QUEUE_WORKER=false` and rebuild cached configuration; an already running invocation finishes within its configured limits.

### Auth

- `translator_guard` (default: `interpresso_translator`)
- `auth_guard` (default: `auth_translator`)

### Browser Security Headers

`security_headers.enabled` defaults to `true`. The package's web route group,
including login, redirects, forms, JSON UI endpoints and authorization errors,
sends this Content-Security-Policy:

```text
default-src 'none'; script-src 'self'; script-src-attr 'none'; style-src 'self'; style-src-attr 'none'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'
```

It also sends `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`,
and `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), fullscreen=()`.
Host application routes and the package's inter-host API routes are unaffected.
Authentication, administrator checks and CSRF protection remain unchanged.

For assets served by a CDN through Laravel's asset URL configuration, append its
explicit origin under each resource directive it serves:

```php
'security_headers' => [
    'enabled' => true,
    'extra_sources' => [
        'script-src' => ['https://cdn.example.com'],
        'style-src' => ['https://cdn.example.com'],
        'font-src' => ['https://fonts.example.com'],
    ],
],
```

Supported extension keys are `script-src`, `style-src`, `img-src`, `font-src`
and `connect-src`. Values must be lists of explicit HTTP(S) origins, with an
optional port and no trailing slash, path, query or fragment. Wildcards,
scheme-only sources, CSP keywords, credentials, unsupported directives and
malformed configuration throw an exception. Empty source lists preserve the base
policy. Extensions cannot replace its defaults or relax framing, forms, base URLs,
objects or inline-content restrictions. Allow only trusted origins; cross-origin
ES modules also require the CDN to return appropriate CORS headers.

Set the boolean `security_headers.enabled` to `false`, or set
`INTERPRESSO_SECURITY_HEADERS_ENABLED=false`, to disable all four package headers.
For rollout, rebuild and republish assets and update any published Blade overrides
to remove inline content. Existing published config files retain the secure defaults
through config merging. Host-supplied additional CSP headers still apply.

The layout reads the unencrypted `interpresso-color-theme` cookie, accepting only
`light` or `dark`. The cookie is scoped to the package URL prefix, lasts one year,
uses `SameSite=Lax`, and uses `Secure` on HTTPS. Only the package's cookie middleware
exempts this display preference from encryption; session and authentication cookies
keep their protections. Shared page caches must vary on this preference or exclude
the package pages so one visitor's rendered theme is not served to another.

### Translation Behavior

- `translatable_models` (array of model classes using JSON translatable fields)
- `open_ai_model`
- `max_open_ai_missing_trans`
- `cache_key` (default: `interpresso_cache`; scopes settings, translation entries and their shared version)

### DB Loader and Cache

`db_loader` defaults to `true` for new installations, including the initial settings
row. The upgrade migration changes the column default only; it never updates existing
rows or overrides a saved `true` or `false`. Its rollback restores the `false` default
without modifying existing rows. Laravel 12/13 support this schema change natively;
no `doctrine/dbal` dependency is added. The migration also clears the cached loader
selection, which console startup may have set to file mode before the settings table
existed on a fresh install.

DB mode loads cached translations from the database and skips `lang/` file exports.
Model translation exports continue. File mode remains available by disabling
`db_loader` in Settings and exporting approved translations.

The `validation` group retains Laravel's built-in messages and the host's
`lang/{locale}/validation.php` overrides before applying reviewed DB entries.
This keeps form errors readable on fresh DB installs. Other application groups
continue loading exclusively from the database.

The database is a hard dependency for rendering web requests in DB mode.
`InterpressoTranslatorServiceProvider::registerLoader()` falls back to Laravel's file
loader on a database exception only during console registration (`runningInConsole()`).
Web requests go through `loadTranslationsArray()` without that exception guard, and
uncached translations query the database directly. Database failures propagate; there
is no automatic web fallback. Failures in translation lookups after console registration
are also outside that guard. This preserves existing behavior because DB-mode language
files may be missing or stale.

Set `CACHE_STORE=redis` or `CACHE_STORE=memcached` (or `CACHE_DRIVER` if that is the
environment variable your application's cache configuration uses). `file` is acceptable
on a single server. Do not use `database`: caching DB rows in the DB to avoid reading DB
rows defeats the purpose. Queue workers and web processes must share the same store and
prefix; hosts sharing translations should use the same Redis or Memcached cache.

Translation entries are cached forever under versioned keys. The shared version lives
at `config('interpresso.cache_key') . ':version'`. Initialization and version bumps use a
cache lock, including with the file store. Package writes advance it after commit;
reads inside transactions bypass the shared cache so uncommitted values cannot leak
into it. Existing targeted invalidation uses the same key builder as cache reads.
Older versions become unreachable and remain stored until cache eviction or clearing.

Custom bulk writes, raw SQL and writes with model events disabled must call
`Translation::invalidateCacheAfterWrite()` on the configured package connection after
the write. This schedules the version bump after commit. Normal `Translation` model
saves/deletes and all package bulk write paths already do this.

## Settings Managed in UI

The **Settings** page updates the `interpresso_settings` row:

- `domains`
- `enable_multi_host` (default: `false`; requires non-empty `domains` when enabled)
- `db_loader` (default: `true` on new installs; existing values are preserved)
- `import_vendor`
- `enable_pending_notifications`
- `enable_automatic_pending_notifications`
- `enable_open_ai_translations`
- `import_only_from_root_language`
- `allow_deleting_languages`

## Recommended Production Setup

1. Set a dedicated `INTERPRESSO_DB_CONNECTION` if needed.
2. Configure queue workers for package jobs.
3. For multi-host sync, configure shared domains and `INTERPRESSO_API_SHARED_SECRET`, then enable multi-host coordination in Settings.
4. Rotate default admin credentials.

Each Settings control submits and validates only its own field using an ordinary
POST and redirect. JavaScript submits on change; a Save button works without it.
Enabling multi-host validates against saved Domains, so save Domains first.
Invalid Domains do not prevent changes to unrelated settings.
