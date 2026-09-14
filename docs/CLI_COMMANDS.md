# CLI Commands

This reference matches the eleven commands registered from `src/Console/Commands/`. The complete in-app guide, including the same CLI reference, is `docs/APPLICATION_MANUAL.md`, rendered at `interpresso.manual` (normally `/translator/manual`). Run commands from the host Laravel application's directory with `php artisan`.

## Execution and Settings

The working commands check the local advisory lock, package jobs, unfinished uncancelled batches, and configured peers when multi-host is enabled. They acquire the settings lease with one conditional database UPDATE before work starts, including under `QUEUE_CONNECTION=sync` and cron. A busy command reports the owner (host, PID, operation and invocation ID) and start time, then returns without doing work. Exceptions and PHP errors release the command's lease in `finally`.

`interpresso.process_lock_ttl` defaults to 900 seconds and can be set with `INTERPRESSO_PROCESS_LOCK_TTL`. Expired leases and legacy flags without an expiry do not block acquisition. A long import renews its lease between files and model chunks through `ProcessLock::refresh()`; custom long-running operations should call `refresh()` on their acquired handle before the TTL elapses. Set the TTL above the longest uninterrupted unit of work. Separate databases still coordinate through best-effort HTTP checks, not a distributed atomic lock.

The local guard queries `jobs` for `interpresso.queue_name` (default `languageProcessor`) and `job_batches` for `interpresso.batch_name` (default `languageBatch`) on the default database connection. Keep that connection consistent with the application's queue/batch setup. Unreachable or non-success peer responses are ignored during the busy check.

Import, missing-translation, approval and export commands execute their own translation work synchronously in the CLI process, including under `QUEUE_CONNECTION=sync`. The HTTP queue refusal never blocks commands. With sync their notifications also run in the CLI process; with an asynchronous connection, administrator result notifications and pending reminders are queued. With an asynchronous queue connection, run a worker for the package queue:

```bash
php artisan queue:work --queue=languageProcessor
```

On shared hosting without Supervisor, use `QUEUE_CONNECTION=database`, enable `interpresso.schedule.queue_worker`, and invoke Laravel's scheduler through [one cron line](CONFIGURATION.md#cron-without-supervisor). This is the recommended shared-hosting setup. The UI buttons work normally and the scheduled `interpresso:work` drains their queued jobs. The worker itself does not acquire a competing operation lease: its jobs already own the batch's `ProcessLock`.

New installations use `db_loader=true`. Normal export and developer download pass model-only export in this mode, so they do not write PHP/JSON translation files. Model translations still require export to the application's JSON columns. File mode (`db_loader=false`) exports files and models. The deployment command is an exception: it does not pass the model-only flag.

DB-loader web rendering requires the database; the database-error file-loader fallback only applies during console loader registration under `runningInConsole()`, not later lookups. `CACHE_DRIVER` must not be `database` in DB mode; the same applies to `CACHE_STORE` when used by the host application. Use a non-database cache shared appropriately by web processes, workers, and participating hosts.

The signatures below list all command-specific arguments/options. `interpresso:export-translations` has a value-taking `--force=` option, and `interpresso:unlock` has a boolean `--force` switch. Export also accepts `--language=` and `--only-models`. Approval accepts `--translator=` and `--language=`. The other commands have no command-specific options.

## interpresso:import-languages

Signature:

```text
interpresso:import-languages
```

Imports supported locale directory names directly under Laravel's language path, normally `lang/`, and skips codes already in the database. It does not infer languages from `{locale}.json` files alone, import vendor locale directories as top-level languages, or import translation text. The language path must exist.

Reports existing names and newly imported languages, or `Nothing imported.` It queues administrator result notifications. Use it for initial setup or after adding supported locale directories. For JSON-only sources, create the language through the Languages screen or provide its locale directory first.

```bash
php artisan interpresso:import-languages
```

## interpresso:import-translations

Signature:

```text
interpresso:import-translations
```

Imports PHP/JSON sources for existing language records, plus configured Eloquent model translations from `interpresso.translatable_models`. PHP files must return arrays, JSON must decode to arrays, and nested keys are flattened for database storage.

- `import_vendor` enables registered vendor namespace imports, checking published overrides before package sources.
- `import_only_from_root_language` limits the language set to `config('app.locale')`, including vendor and model passes.
- Existing language/shared-identifier pairs are skipped. Changing an existing source value does not replace its saved database value.
- New source imports start approved, not updated, and exported; empty/falsy values are marked as needing translation.

Reports existing and newly inserted translation counts. The CLI's administrator import-notification call is inactive; this differs from the UI import batch. Use it after importing languages and whenever new source keys or model locale values should be added.

```bash
php artisan interpresso:import-translations
```

## interpresso:find-missing-translations

Signature:

```text
interpresso:find-missing-translations
```

Compares per-language translation counts, with a separate sentinel for languages having no rows. When counts differ, it invokes missing-counterpart creation using the language matching `app.locale`, or the first language if that record is absent. It copies root-language identifiers into other languages where missing, leaving existing counterparts unchanged. It does not scan application code, union all language key sets, or fill keys absent from the root itself.

With OpenAI enabled and configured, generated values can be translated automatically; otherwise source text is retained. Created rows are unapproved, need translation, are not updated, and are not exported. The command reports inserted counts and queues an administrator notification for the configured root language when that record exists.

Use it after adding languages or importing root-language keys. **Count-check limitation:** equal counts can conceal different identifiers. The command then prints `Everything up to date.` without comparing keys. The UI Find Missing Translations action does not use this count shortcut.

```bash
php artisan interpresso:find-missing-translations
```

## interpresso:approve-translations

Signature:

```text
interpresso:approve-translations {--translator=} {--language=}
```

Synchronously approves all unapproved translations, or only those in `--language=en`. `--translator=ID` is required and must identify an existing administrator translator; approvals are attributed to that ID. Invalid attribution or an unknown language exits with status 1 without writes. The command uses the shared process lease, refreshes it between languages, invalidates translation caches through the existing approval service, and sends administrator result notifications. It works under `QUEUE_CONNECTION=sync` without a worker. Review translations before invoking it.

```bash
php artisan interpresso:approve-translations --translator=1
php artisan interpresso:approve-translations --translator=1 --language=en
```

## interpresso:export-translations

Signature:

```text
interpresso:export-translations {--force=} {--language=} {--only-models}
```

Exports approved, not-updated translations. Ordinarily it only exports rows marked not exported. `--force` is a value-taking option, cast to boolean: use `--force=1` to include already exported rows; omitting it or using `--force=0` retains the ordinary behavior. Force does not bypass approval or the running-job guard.

In file mode it exports PHP/JSON and model content. In DB-loader mode it passes model-only export and skips files. `--only-models` also restricts export to model rows in file mode. `--language=en` restricts both the candidate count and execution to that language; an unknown code fails without exporting anything. It runs synchronously and queues administrator result notifications; it does not trigger peer exports. Counts describe translation rows, not files.

Use it for regular publication or, in file mode, a forced rewrite of files whose database rows are already marked exported.

```bash
php artisan interpresso:export-translations
php artisan interpresso:export-translations --force=1
```

## interpresso:export-translations-deployment

Signature:

```text
interpresso:export-translations-deployment
```

Synchronously force-exports each language's approved, not-updated translations, including models, regardless of the Exported flag. It prints a completion line for every language, even if there are no eligible rows. It uses the shared process guard. It has no `--force` option or inter-host export propagation.

**This command does not honor `db_loader` as a model-only export selection.** It can write PHP/JSON files even when DB loading is enabled. Use it after a file-mode deployment replaces exported translation files. Run it only when overlapping bulk work has been ruled out.

**It is unnecessary for DB-loader delivery.** DB mode serves application translations from the database without routine `lang/` exports, so a deployment cannot clobber those translations by replacing language files. Omit this command from the DB-mode deployment workflow; use the normal export command for model translations.

```bash
php artisan interpresso:export-translations-deployment
```

## interpresso:work

Signature:

```text
interpresso:work
```

Drains `interpresso.queue_name` on the default queue connection by calling `queue:work` with `--stop-when-empty`, `--max-time=50`, `--max-jobs=100`, `--memory=128`, `--timeout=60`, `--sleep=0`, and `--tries=1`. It exits 0 on an empty queue or normal time/job budget stop. Remaining work continues on the next cron tick. Non-deferring connections and invalid limits return 1; other exit codes come from the worker. Check failed-job records and logs even after exit 0.

Configure positive integer values in `interpresso.queue_worker.max_time`, `max_jobs`, `memory`, and `timeout`. Time and memory limits are checked between jobs; interrupting a stuck job at its per-job timeout requires PHP CLI PCNTL or a hosting process limit. See [limits and scheduler configuration](CONFIGURATION.md#cron-without-supervisor). There are no command-specific options.

Enable `interpresso.schedule.queue_worker` to run every minute in the background. Laravel's cache mutex prevents overlapping scheduled workers and is separate from the operation's database lease. A direct invocation does not acquire the scheduler mutex. All package schedules are omitted for connections that cannot defer work.

```bash
php artisan interpresso:work
```

## interpresso:prune-batches

Signature:

```text
interpresso:prune-batches
```

Deletes rows from `job_batches` on `interpresso.db_connection` whose name equals `interpresso.batch_name` and whose `finished_at` or `cancelled_at` timestamp is older than `interpresso.prune_batch_hours`, default 24 hours.

It does not cancel active batches, delete queue jobs, prune failed-job records, or print a completion summary. It refuses to start while the shared process guard is busy. It has no retention-hours option; change the config value to alter retention. Enable `interpresso.schedule.prune_batches` for recurring batch-record housekeeping every minute, or arrange your own schedule.

```bash
php artisan interpresso:prune-batches
```

## interpresso:send-automatic-pending-translations-notification

Signature:

```text
interpresso:send-automatic-pending-translations-notification
```

This is the real signature of `SendAutomaticPendingNotifications`. There is no `interpresso:send-automatic-pending-notifications` alias.

When `enable_automatic_pending_notifications=true`, iterates every translator, including administrators, and each explicitly assigned language. A language with `needs_translation=true` rows produces queued database and mail notifications for that translator. Languages with zero requested rows produce no delivery. Administrators are not automatically notified for unassigned languages merely because their UI access covers all languages.

When the toggle is false, the command does nothing. It uses the shared process guard and does not consult `enable_pending_notifications` or print a result summary. Unapproved rows whose Needs Translation flag is false do not count as pending reminders.

Use it for periodic reminders with the application's mail transport and a long-running or cron worker. Enable `interpresso.schedule.pending_notifications` for the daily midnight schedule, or arrange your own. The saved automatic-notification setting is still checked when the command runs. Repeated runs can send repeated reminders for the same work.

```bash
php artisan interpresso:send-automatic-pending-translations-notification
```

## interpresso:developer-download

Signature:

```text
interpresso:developer-download
```

Downloads from `interpresso.main_server_domain`, normally set with `INTERPRESSO_MAIN_SERVER_DOMAIN`. Requests send `INTERPRESSO_API_SHARED_SECRET` as `api_key`; the source host must have the same secret. The source API fails closed with HTTP 503 when no secret is configured, after request validation. A wrong secret receives 401; a missing/non-string request key can receive 422. Developer download is independent of `enable_multi_host`.

The command performs these steps:

1. Disables foreign-key checks and starts a transaction on `interpresso.db_connection`.
2. Fetches language records, deletes the local language rows, and inserts the downloaded records.
3. Fetches paginated translations, deletes local translation rows, and inserts all downloaded pages, invalidating translation caches after commit.
4. Commits the database replacement and restores foreign-key checks.
5. Force-exports approved, not-updated translations for every downloaded language. Local `db_loader=true` selects model-only export; file mode exports files and models.

**Destructive local replacement:** use this only when intentionally replacing a development translation database. There is no confirmation prompt, merge mode, dry run, local-environment restriction or host argument. The shared process guard applies. Back up local work that must be retained. Settings, translator accounts, and language assignments are not downloaded; those local records are not synchronized to the source installation.

The database phase uses MySQL/MariaDB-specific `SET FOREIGN_KEY_CHECKS` statements and is not portable to SQLite or PostgreSQL as written. Its transaction does not cover file/model exports: export runs after commit, so export failure does not undo downloaded records. Model export also requires corresponding local model records and columns.

```bash
php artisan interpresso:developer-download
```

## interpresso:unlock

Signature:

```text
interpresso:unlock {--force}
```

Prints the recorded owner and start time. Without `--force`, it clears expired or legacy leases and refuses a live lease with exit code 1. `--force` clears a live lease too. Successful release exits 0 and clears `process_running`, `process_owner`, `process_started_at` and `process_expires_at`. Clearing an already unlocked settings row is harmless. Expired-only cleanup uses a conditional UPDATE so a concurrent acquisition or heartbeat cannot be cleared.

```bash
php artisan interpresso:unlock
php artisan interpresso:unlock --force
```

Unlock does not stop a PHP process, cancel queue records, or roll back changes. Stop or verify the old process before forcing a live lock. Existing queue/batch records can still block work; use **Delete running Batch (Jobs)** to cancel those. Cancellation releases only the cancelled batch's lease and does not unlock a separate cron run.

## Initial Load and Scheduled Operations

An initial source import typically runs in this order:

```bash
php artisan interpresso:import-languages
php artisan interpresso:import-translations
php artisan interpresso:find-missing-translations
```

Complete review and approval in the panel. File mode then needs `interpresso:export-translations`; DB mode needs export only for application model columns.

For ongoing housekeeping, enable `interpresso.schedule.prune_batches` and, if desired, `interpresso.schedule.pending_notifications`, or arrange your own schedule. Both default to false and are independent of `interpresso.schedule.queue_worker`; all require a deferring connection for package scheduling. Pending reminders also require the automatic notification toggle and a functioning queue/mail configuration.

For cancelled or stuck work, use **Languages > Delete running Batch (Jobs)** rather than expecting pruning to stop it. That action cancels matching unfinished batches and deletes database queue rows. It does not terminate executing worker processes or roll back completed changes.
