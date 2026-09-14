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

The database advisory lease coordinates cron, artisan, single-row HTTP mutations and queued
batches on `interpresso.db_connection`. Long imports heartbeat between files and
model chunks. Custom long operations should call `refresh()` on the acquired
`ProcessLock` handle before expiry; set the TTL above their longest uninterrupted
step. Use `php artisan interpresso:unlock` to inspect/clear expired leases, or
`--force` to clear a live lease after stopping the old process.

### Supported execution modes

Long-running translation work never runs inside an HTTP request, including after the response is flushed. Before any batch writes or lease acquisition, the UI and peer force-export endpoint inspect `queue.default` and `queue.connections.<connection>.driver`. Connection aliases are supported. `sync`, `null`, missing configuration, and Laravel's `deferred` driver are refused; failover is refused if any fallback is unsafe or cyclic. A configured asynchronous driver does not prove a worker is alive: queued jobs wait until one consumes them.

Choose one of these three supported modes:

1. **Worker:** set `QUEUE_CONNECTION=database` (or `redis`) in the host application and run a supervised worker consuming `languageProcessor`, or your `interpresso.queue_name`. HTTP only enqueues batches. Execution is bounded by the worker's `--timeout`, PHP CLI memory/time settings, and process-manager or hosting limits. For example, use `php -d max_execution_time=0 artisan queue:work --queue=languageProcessor --timeout=900 --tries=1`, set the connection's `retry_after` above that timeout (for example 960 seconds), and set `INTERPRESSO_PROCESS_LOCK_TTL=1800`. For SQS configure the equivalent visibility timeout. Size these values for the longest job and queue wait; a timeout is per job, not per batch. Keep the worker supervised and inspect `failed_jobs` and application logs on failure.
2. **Cron plus commands, without a worker:** set `QUEUE_CONNECTION=sync` and invoke the import, missing-translation, approval and export commands from the CLI. They call their services inline in that CLI process, and sync also delivers their queued notifications there. They are bounded by PHP CLI `max_execution_time`/`memory_limit`, OS resources, and any hosting or scheduler runtime cap, without a PHP-FPM/web-server deadline. `php -d max_execution_time=0 artisan ...` explicitly removes PHP's CLI time cap; it cannot remove a hosting cap. Commands share the process lease; a busy command reports the current owner and does no work. Review cron logs because a busy return is not successful completion of the requested work.
3. **Sync for small installations only:** keep `QUEUE_CONNECTION=sync`, use the UI for browsing, single-row edits and reviews, and run bulk commands manually when needed. Ordinary HTTP interactions remain bounded by PHP-FPM, PHP's web time limit and the web server. Bulk buttons are still refused, regardless of translation count. Small installation size never enables inline HTTP imports, exports, approval batches or automatic translation of other languages. As the workload grows, use a worker or the cron setup above.

After changing queue environment/configuration, rebuild the host's configuration cache if used (`php artisan config:cache`) and restart long-running workers. With `null`, CLI service calls can still run, but queued notifications are discarded; use `sync` for the no-worker modes.

Bulk browser actions refuse unsafe connections with a warning toast containing the matching Artisan command. The authenticated peer force-export API returns HTTP 503 and a JSON `message` naming `php artisan interpresso:export-translations-deployment`. These guards run before data writes, batch creation or lease acquisition. They do not apply to Artisan commands. See the [manual command mapping](APPLICATION_MANUAL.md#background-jobs-and-queues).

### Crontab without a worker

For a concrete no-worker setup on Linux, set `QUEUE_CONNECTION=sync` in the host application's environment/configuration. This daily crontab imports new sources, finds missing counterparts, then exports already approved translations. Replace the application path, PHP binary and times. The crontab owner must be able to write the application's storage and export paths. `flock` prevents overlapping invocations of this sequence on the same host; the package lease also coordinates individual commands and HTTP mutations.

```cron
SHELL=/bin/sh
PATH=/usr/local/bin:/usr/bin:/bin
15 2 * * * /usr/bin/flock -n /srv/app/storage/interpresso-cron.lock /bin/sh -c 'cd /srv/app && /usr/bin/php -d max_execution_time=0 artisan interpresso:import-languages && /usr/bin/php -d max_execution_time=0 artisan interpresso:import-translations && /usr/bin/php -d max_execution_time=0 artisan interpresso:find-missing-translations && /usr/bin/php -d max_execution_time=0 artisan interpresso:export-translations' >> /srv/app/storage/logs/interpresso-cron.log 2>&1
```

Approval is a deliberate review step, so it is not scheduled in this example. After review, run `php artisan interpresso:approve-translations --translator=1` with the actual administrator translator ID, optionally adding `--language=en`. Per-language exports use `php artisan interpresso:export-translations --language=en`; forced rewrites use `php artisan interpresso:export-translations --force=1`. Add `--only-models` for the model-export buttons. DB-loader mode already exports only models. The CLI commands run locally and do not propagate exports to peers; arrange commands on each host in a no-worker installation.

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
