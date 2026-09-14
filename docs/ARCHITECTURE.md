# Architecture

## UI and authorization

Controllers in `src/Controllers` render Blade pages in `resources/views`. The package
URL prefix and screen segments still come from `config('interpresso.*_url')`.
Navigation, search, filters and pagination are full GET requests. Other mutations
are CSRF-protected POST forms followed by redirects, including independent settings
forms. Form requests validate language/account creation, profile/password changes,
login and supported settings fields.

`EnsureTranslator` resolves the package guard's translator. `EnsureAdmin` protects
privileged routes. `BaseController::scopeLanguages()` restricts listings, and every
per-row translation endpoint uses `resolveTranslation()` to verify both assignment
and row ownership. Notification queries use the authenticated notifiable relation.

`SecurityHeaders` wraps only the package web route group, including login and
redirect/error responses. It sends a strict CSP and companion security headers;
configuration can append validated CDN origins without replacing the base policy.
Host routes and the inter-host API group do not receive these headers.

The layout's view composer validates the `interpresso-color-theme` cookie and renders
`data-theme` and `.dark` before paint. Package-scoped `EncryptCookies` exempts only
that display cookie, without changing the host application's cookie exceptions.
Without a valid cookie, DaisyUI's CSS selects the system theme before JavaScript.

## Browser modules and JSON endpoints

Vite builds vanilla ES modules. Flowbite utility classes and its CSS plugin remain;
its JavaScript runtime is not loaded. Native dialogs provide modal focus handling.

- `modal.js`: fetches a row's modal data and optional OpenAI suggestion. Suggestions
  never save automatically and edited drafts require explicit replacement.
- `batch-progress.js`: recovers the running batch rendered with the page or tracks
  a new batch ID flashed by a POST action. Stops on completion/cancellation.
- `notifications.js`: polls unread notifications and marks owned messages read.
- `polling.js`: cancels in-flight polls and timers while the tab is hidden.
- `filters.js` and `search.js`: submit ordinary GET forms and per-field settings POSTs.
- `theme.js`: handles theme toggles, syncs the cookie and legacy browser storage,
  and follows system changes when no explicit choice exists.
- `toast.js`: accepts message, type (`SUCCESS`, `DELETED`, `INFO`, `WARNING`) and
  duration from an escaped `data-toast` attribute or browser events; displays
  escaped, dismissible text. Templates have no inline scripts or styles.

JSON is limited to `batch/progress`, notifications, `{language}/{id}/modal` and
`{language}/{id}/suggest`, beneath the configured prefix and translations segment.
Row updates, approvals, requests and restores use forms. The modal saves its
`translatedValue` textarea by POST and keeps table filters in the action URL.

## Services, jobs and data

Import/export, missing translations and OpenAI services remain independent of the UI.
Controllers dispatch Laravel batches using configured queue and batch names. The
shared `Services/Traits/ChecksForRunningJobs` supports both controllers (session
flash) and console commands (terminal messages). All translation mutations are
subject to this guard; modal reads and draft suggestions do not write data.
`Services/ProcessLock` grants entry with a single conditional UPDATE of the settings
row, on the package connection. Reads bypass cached settings. Each invocation has
an owner (host, PID, operation, nonce), start time and expiring lease. Release and
heartbeat match the owning invocation, so delayed callbacks cannot clear a
successor. `BatchProcessor::dispatch()` transfers the handle to serialized batch
callbacks; entry points release their remaining ownership in `finally`. Deferred
API exports transfer ownership before the response and release on dispatch errors.
Job middleware heartbeats and refuses obsolete leases. Import service heartbeats
run between files/model chunks. Batch cancellation releases captured batch handles
only, while `interpresso:unlock` handles expired/forced operational cleanup. The old
`Setting::setJobsRunning()` boolean writer is replaced by this one mechanism.

Translations are imported from PHP/JSON files or configured models into DB records.
Approved translations are served by the default database loader, or exported in file
mode. Model exports remain available in either mode. Multi-host propagation is opt-in
and the inter-host busy API includes owner, start, expiry, and separate queue status.

Settings cache refreshes update loader selection. Translation caches use a shared
version and targeted invalidation. Bulk writes bypassing model events call
`Translation::invalidateCacheAfterWrite()` so invalidation happens after commit.

Bulk HTTP entry points validate the configured connection driver with `QueueConfiguration` before writes or lease acquisition. Unsafe connections refuse with a localized CLI instruction (toast for the UI, HTTP 503 JSON for peer force export). Batches dispatch to the queue during the request; worker execution is separate. CLI commands use the same services and lease under sync without this HTTP-only guard.

### Resumable queue operations

Export and approval jobs select a bounded, ordered `id > afterId` slice independently of mutable approval/export flags. They update eligible rows and add their own cursor successor to the same batch. Missing-translation jobs use root-language IDs and one target language per chain. Import jobs advance through an ordered source manifest, with primary-key cursors for model columns and fingerprint-checked entry ordinals for files. `interpresso.chunk_size` defaults to 100. A full slice queues another job, including a terminal empty slice when necessary.

Retries assign or merge keys and skip existing imports. Bulk inserts recheck identifiers inside a transaction while locking the target language; file merges use a stable filesystem lock and atomic replacement, and model JSON updates lock their source row. A replay also advances the translation cache version in case the previous process died between commit and invalidation. External AI responses cannot be committed atomically with the database, so a crash before insertion can repeat an API request. Input/output files still require one-file parsing; the cursor bounds database mutations, not arbitrary PHP evaluation or external-call latency.

The batch stores `estimated_total_jobs` separately from Laravel's actual pending-job accounting. The UI divides completed jobs by the greater of this estimate and the discovered job count. File estimates use byte sizes so HTTP dispatch does not parse every translation. `ChunkedJob` refreshes the batch's `ProcessLock` at both ends of a slice and checks cancellation before working and before chaining. The default lease is 1800 seconds, allowing a 15-minute cron interval plus runtime; longer queue waits need a larger TTL. Lost reservations may retry after worker death; a processing exception fails immediately.

Scheduled workers use a named Artisan callback when background execution is disabled or `proc_open` is unavailable, checking both function presence and `disable_functions`. This bypasses Symfony Process entirely, including for maintenance on restricted hosts. The worker defaults to a 96 MB between-job memory bound and accepts `--memory` and `--max-time` overrides.

## Translator password links

`PasswordResetController` handles guest forms under the package prefix and session middleware. `TranslatorPasswords` always selects the `interpresso_translators` broker, whose token repository uses the package connection and dedicated configurable table. Translator-row locks serialize token issuance, redemption, profile email changes and deletion; successful resets hash the new password, rotate the remember token and consume the token in the same transaction.

Public requests enqueue `SendTranslatorPasswordReset` for every validated address without retrieving an account. The worker alone checks existence and the per-account email cooldown, creates the hashed token, and sends `TranslatorPasswordLink`. Persistent asynchronous queue connections are required, preventing sync or deferred SMTP timing from becoming an enumeration oracle. Responses have identical bodies, status, redirects and generic messages. A shared IP rate limit protects requests, redemptions and invitation resends.

Creation stores a null password and sends an invitation with distinct wording using the same token repository and mail notification. Invitations are sent immediately; failed delivery keeps the account available for the admin-only resend action. Reset/invitation URLs use the configured primary origin, and reset pages use no-referrer and no-store headers. All new form, validation and email text is present in en/de/fr/es/it, including the email template rather than untranslated framework mail boilerplate.

The external `locale.js` module attaches a select change listener using native `requestSubmit()` and then hides the fallback button. With JavaScript disabled, the original navbar button submits the form normally.
