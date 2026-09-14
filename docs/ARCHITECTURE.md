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
