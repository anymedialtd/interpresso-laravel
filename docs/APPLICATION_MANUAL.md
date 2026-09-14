# Application Manual

This is the manual displayed inside the translation panel at the `interpresso.manual` route, normally `/translator/manual`. Use the generated section index to navigate. On wide screens, the index stays beside the article and scrolls within the viewport so every section remains reachable. The four working screens are Languages, Translations, Translators, and Settings.

The interface language can be chosen per translator, independently of the host application's `app.locale`. English (`en`), German (`de`), French (`fr`), Spanish (`es`), and Italian (`it`) are included. The manual loads `docs/APPLICATION_MANUAL.{locale}.md` for the active locale and falls back to `docs/APPLICATION_MANUAL.md` when no translation exists. The English original remains authoritative. Translated headings retain the original section anchors.

## Getting Started

### Install, publish, and migrate

The package requires PHP 8.2 or later within PHP 8 and Laravel 12 or 13, as declared in `composer.json`. Run installation commands from your Laravel application directory:

```bash
composer require anymedialtd/interpresso-laravel
php artisan vendor:publish --tag=interpresso-config
php artisan vendor:publish --tag=interpresso-migrations
php artisan vendor:publish --tag=interpresso-public
php artisan migrate
```

Composer auto-discovery registers the package providers. The config publish includes `config/interpresso.php` and `config/openai.php`. Runtime assets are published as `public/vendor/interpresso/css/app.css` and `public/vendor/interpresso/js/app.js`. Publishing `interpresso-translations` or `interpresso-views` is optional, for overriding the package's interface text or templates. The package also loads its migrations directly.

Existing installations must follow [Adopting the Interpresso identity](INSTALLATION.md#adopting-the-interpresso-identity) before migrating. The default package tables now use `interpresso_`; set the `table_*` options to existing table names to retain their data. Configuration, PHP integrations, schedules, published overrides and all participating API hosts must adopt the new identity together.

Migrations create the language, translation, translator, assignment, and settings tables, plus queue, batch, failed-job, and notification tables when missing. Set `INTERPRESSO_DB_CONNECTION` and any custom table names in `config/interpresso.php` before migrating. A language matching `config('app.fallback_locale')` is created; that locale must exist in the package's supported language list.

New installations enable `db_loader` and disable multi-host coordination. Before serving translations, select a non-database cache store and import your source data as described below. For queued UI operations, configure an asynchronous queue connection and a worker consuming the configured package queue, normally:

```bash
php artisan queue:work --queue=languageProcessor
```

For shared hosting without Supervisor, use `QUEUE_CONNECTION=database` and enable `interpresso.schedule.queue_worker` with [one cron line](#cron-without-supervisor). This is the recommended shared-hosting setup; the UI buttons work normally.

The cache store and queue connection are separate settings. A database-backed queue is compatible with a non-database cache.

### Sign in and change the default password

The default login URL is `/translator/login`. When no translator exists, the migration creates this administrator:

- Email: `admin@admin.com`
- Password: `aaaaaaaa`
- First and last name: `admin`

Sign in, open **Translators**, edit the administrator, and use **Update password** immediately. Set a password of at least eight characters and enter the same confirmation. Profile updates alone do not change passwords. Translator ID `1` cannot be deleted through the application, but its profile and password can be edited.

The login form has email, password, and **Remember me** controls. Invalid credentials remain on the login screen; login is limited to ten attempts per IP address per minute and shows the retry delay after that limit. Accounts use the package's `interpresso_translator` session guard. Use **Logout** in the navigation to end the session.

UI routes are registered only when `config('interpresso.main_server_domain')` exactly matches `config('app.url')`. The main-server value comes from `INTERPRESSO_MAIN_SERVER_DOMAIN`, defaulting to the application URL. `INTERPRESSO_ENABLED=false` disables the package's UI/API registration and custom loader. Route prefixes and screen paths are configurable in `config/interpresso.php`.

### First import

The **root language** is `config('app.locale')`. The **fallback language** is `config('app.fallback_locale')`; it is also the initial example language in the editor. These can differ.

1. Make your source translation files available under Laravel's language path, normally `lang/`. Check the root and fallback locale configuration.
2. Open **Languages** and run **Import Languages**. It recognizes supported locale directory names such as `lang/en/` and `lang/de/`. A JSON file such as `lang/de.json` alone does not create a language; add it using **Add Language** or provide its locale directory.
3. Wait for completion, refresh the language list, then run **Import Translations**. It imports PHP and JSON entries for language records already in the database, and translations from configured models. Set vendor and root-language import options first.
4. Run **Find Missing Translations** to create counterparts of root-language entries in the other languages. Review these rows, translate them, and approve them.
5. In DB-loader mode, approved application translations are served from the database. In file mode, export after approval. Model translations require export to their application model columns in either mode.

The initial CLI equivalent is:

```bash
php artisan interpresso:import-languages
php artisan interpresso:import-translations
php artisan interpresso:find-missing-translations
```

Imports add missing records; they do not overwrite existing database translations when a source file changes. File/model imports initially mark records approved and exported. Rows created by the missing-translation operation are unapproved, need translation, and are not exported, even when OpenAI supplies their text.

## Languages

### Browse, search, add, and delete

The list shows language code, name, and native name, sorted by code, with ten rows per page. **Search** matches those three fields. Use **View** on a row to open its Translations screen. Administrators see every language; other translators see only their assignments. Pagination provides previous, next, and numbered pages when needed.

**Add Language** opens a selector from the package's supported language list. Choose an unused code and press **Add**. Duplicate or unsupported codes are rejected. This creates a language record and, if absent, its locale directory under `lang/`, including when DB loading is enabled. It does not create translated entries. **Close** hides the form.

The row **Delete** action is shown only to administrators when `allow_deleting_languages` is enabled. It detaches translator assignments and deletes the language; its translation records are removed by the database's cascading foreign key. It does not delete language files or the locale directory, so a later filesystem import can recreate the language. There is no confirmation dialog in this action. Keep the fallback language if its values should be available as translation examples.

### Import and fill missing entries

All toolbar operations below are presented to administrators:

- **Import Languages** queues discovery of supported locale directories under the language path. Existing codes are skipped.
- **Import Translations** queues PHP, JSON, optional vendor, and configured model imports. The import covers the configured language set, not just the visible search results.
- **Find Missing Translations** queues creation of missing records by shared translation identifier. It uses the language matching `app.locale`, or the first language if that record is absent, as its source. Only source-language identifiers are copied to other languages; this does not scan application code for translation calls or merge keys from every language into the root. Existing counterparts are left alone. With OpenAI enabled, missing values are offered for automatic translation. Otherwise the source text is copied for later editing.

Vendor import reads published overrides under `lang/vendor/{namespace}/` before the registered package translation directory, so an already imported override is retained. PHP translation files must return arrays; JSON files must decode to arrays. Nested keys are flattened for storage, and PHP subdirectories are retained in the group name.

### Approve, export, and cancel

**Approve (All Languages) Translations** queues approval of every unapproved record in every language, regardless of list search or filters. Approval clears the needs-translation and updated flags, discards the saved old value, and records the acting administrator as approver. Bulk approval does not require a non-empty value, so review missing rows before using it.

With `db_loader` off, **Export All Languages** queues export for languages containing approved, not-updated, not-exported rows. It exports application/vendor files and model translations. If no eligible rows exist, a notification reports that nothing was exported.

With `db_loader` on, **Export All Translated Models** limits both the initial selection and each export job to model rows. PHP/JSON rows are left for DB delivery.

After a UI export batch finishes successfully without cancellation, enabled multi-host coordination requests forced exports on the other configured hosts. The normal export CLI does not perform this propagation.

**Delete running Batch (Jobs)** marks unfinished package batches cancelled and removes jobs from the package's database queue. With multi-host coordination enabled, it also requests cancellation on the configured other hosts. The result reports the local jobs/batches affected. See **Background Jobs and Queues** for cancellation limits.

## Translations

Open a language using **Languages > View**. The default route is `/translator/translations/{language}`, named `interpresso.translations`. A non-admin translator must be assigned to that language to open the screen.

### Table, search, and multi-select filters

The table displays ID, Vendor, Namespace, Group, Needs Translation, Approved, Approved By, Updated, Updated By, Exported, Key, Content, and Old Content. Updater and approver columns show names; filter choices show translator email addresses. Values are displayed as plain text; open **Translate** to edit the full value. The table has twenty rows per page.

**Search** matches ID, namespace, group, key, current value, and old value. A submitted search change resets pagination to page one.

Three multi-select menus refine the results:

- **Type**: PHP, JSON, or Model. Model entries represent configured Eloquent model translation columns.
- **Updated by**: translators recorded as the current updater.
- **Approved by**: translators recorded as the current approver.

Selecting several options in one menu matches any selected option. Different menus, state filters, and search are combined. Clear every selection in a menu to stop filtering that field. There is no separate selection for rows with no updater or approver.

### Five three-state filters

Open **State Filters**. Each button cycles through **gray: no restriction**, **green: true**, **red: false**, then back to gray. Each filters its stored boolean column:

- **Needs Translation** (`needs_translation`): a translation is requested or a missing counterpart was created. False means the request flag is clear, not necessarily that the row is approved.
- **Approved** (`approved`): the row is approved. False includes both requested/missing entries and edited text awaiting approval.
- **Updated** (`updated_translation`): an edited value is awaiting approval. This is a workflow flag, not a date filter or a permanent change history.
- **Is Vendor** (`is_vendor`): the row is marked as vendor content.
- **Exported** (`exported`): the database flag says the row is exported. This does not check the filesystem and is not required for DB-loader delivery.

For requested work, set Needs Translation to green. For review after editing, set Approved to red and Updated to green. For ordinary file export candidates, use Approved green, Updated red, and Exported red. These filters affect the table only; bulk approval/export uses its own full-language query.

Search submits after a short typing pause or on Enter. State filters and multi-select changes submit GET forms and reload the page, resetting pagination. URLs retain `search`, `page`, `needs_translation`, `approved`, `updated_translation`, `is_vendor`, `exported`, `types[]`, `updatedBy[]`, and `approvedBy[]`. A state button cycles the parameter from absent to `true`, then `false`, then absent. Bookmark, reload, and Back/Forward restore those selections. Translator assignment filtering uses `selectedLanguages[]`.

### Example language and translate modal

Select **Example Language** before opening a row's **Translate** action. This chooses the source example, not the language being edited. It defaults to `app.fallback_locale` when that language is permitted, or the current language otherwise. If the selected example is absent or empty, the editor uses the permitted fallback-language value.

The modal shows the translation identifier, example text, a textarea containing the current value, and expandable language-code summaries for available examples sharing its identifier. The selector, examples, and fallback lookup are all restricted to assigned languages for non-admins. Example text is escaped rather than rendered as HTML.

Use **Close modal**, Escape, or a click outside the dialog to dismiss the editor without saving its textarea changes. Focus returns to the Translate button.

### Update and OpenAI actions

**Update Translation** saves only when the textarea differs from the stored value. It saves the prior value and updater/approver information when beginning an edit, sets the acting translator as updater, clears the current approver, and marks the row updated, unapproved, and not exported. It also clears Needs Translation. Further edits before approval retain the same original old value. Saving does not automatically approve or export the text.

**Translate with OPEN AI** appears when OpenAI is enabled and a non-empty permitted example exists. It uses the actual example language, including after fallback, as the source. A suggestion fills an untouched textarea without saving. If you already edited the draft, or edit it while the request is running, the suggestion appears separately with **Use suggestion**. This keeps your draft until you explicitly replace it. Review the text and use **Update Translation** to save. Loading and failure states leave the editor usable; closing the modal cancels its pending request.

An empty or malformed server response, or a suggestion without a text value, displays an error and preserves your draft. You can retry the suggestion or save your own text.

**Update & auto-translate others (OPEN AI)** is restricted to administrators and appears when OpenAI is enabled and the current language is the root language. If the value changed, it saves the root edit once and queues updates for the other existing translations sharing its identifier. It does not create absent counterparts; use Find Missing Translations first. The resulting updates remain unapproved and not exported.

OpenAI support is optional and requires `openai-php/laravel`, valid `config/openai.php` API settings, and the feature toggle. The package sends source text to OpenAI and asks it to preserve Laravel placeholders such as `:name`; review the result. `interpresso.open_ai_model` selects the requested model and `interpresso.max_open_ai_missing_trans` controls missing-translation request chunk size. Failed requests, a missing integration, or responses with missing/extra keys or non-string values preserve the service's original input. In the modal that input is the example text, so an unchanged source sentence is not proof of successful translation. Missing languages or null input return unchanged without a request; array requests retain empty strings and `"0"`.

### Approve, request, remove request, and restore

The following row controls are shown to administrators:

- **Approve** appears for an unapproved row with a non-empty current value. It accepts the current text, records the approver, clears Needs Translation and Updated, removes Old Content and the saved previous updater/approver references, and invalidates translation cache entries. It does not write files or set Exported to true. The string `"0"` is a valid value. The row button is absent for an empty value; bulk approval has no such check.
- **Request translation** appears when Needs Translation is false. It sets Needs Translation true and Approved false. It does not save an old value or send an email by itself.
- **Remove translation request** appears when Needs Translation is true. It sets Needs Translation false and Approved true. It leaves the other flags unchanged; it is not equivalent to the full Approve action.
- **Restore** appears for an unapproved row with a saved Old Content value, including an empty string or `"0"`. It restores that value and the prior updater/approver, clears the saved history and Updated flag, and marks the row approved and exported. It does not write files or explicitly clear Needs Translation. There is one saved old value, not a revision history; after approval clears it, Restore is unavailable.

### Approve and export a whole language

**Approve ({language code}) Translations** queues approval of all unapproved rows for the current language, including rows outside the current filters and rows with empty values.

In file mode, **Export Language** queues export of approved rows with Updated false and Exported false, including eligible model translations. In DB mode, **Export Translated Models** limits both the eligibility check and export job to model rows. Neither button forces re-export of already exported records. Use the CLI `--force=1` option when a re-export is needed. These toolbar controls are shown to administrators.

## Translators

### Browse and filter accounts

This screen, normally `/translator/translators`, is restricted to administrators. It shows ID, first name, last name, email, phone, admin status, and assigned language names, with ten accounts per page.

**Search** matches ID, first name, last name, email, or phone. The language multi-select matches translators assigned to **every selected language**. Clearing it removes that restriction. An admin's automatic access to all languages does not give it assignment records, so an admin may be excluded by an assignment filter.

### Create, edit, assign languages, and delete

Use the create-form button to enter email, phone, first name, last name, password, password confirmation, language assignments, and the Admin switch. Email must be valid and unique; first and last names need at least two characters. Phone is optional; a supplied phone must be unique. Passwords need at least eight characters and matching confirmation.

A non-admin requires at least one valid language assignment. Duplicate or nonexistent assignment IDs are rejected. Admin accounts can be saved without assignments and can access all languages. Explicit assignments still determine which language notifications an account receives.

Use the Languages button to close the assignment dropdown after choosing languages, then submit the form. Validation errors appear beside their fields, including language assignments and password confirmations. Rejected forms keep profile values; passwords must be entered again.

**Create** saves a new account. **Edit** opens an existing account; **Update** saves profile, admin status, and the selected assignment list. Saving replaces the existing assignments with that list. **Close** hides the form. Account creation does not send an invitation or password email.

Use row **Delete** to remove an account. Translator ID `1` has no Delete control and its delete endpoint rejects the action. Other deletions submit directly without a confirmation dialog.

### Password changes and pending notifications

While editing an existing translator, **Update password** opens a separate password form. Enter a new password and matching confirmation, then use its update button. **Close** returns to the profile form. This administrative password change does not ask for the current password. There is no self-service password-change or reset screen for non-admins.

When `enable_pending_notifications` is enabled, the edit form displays the pending-translation notification button. It checks each explicitly assigned language and queues a notification when that language has rows with `needs_translation=true`. Delivery uses both email and the database notification channel. It does not count all unapproved rows, and an assignment with zero requested translations produces no delivery. Configure the application's mail transport and run the package queue worker. The success toast confirms the request, not email delivery.

Automatic reminders use a separate command and toggle, described in the CLI reference. Enabling either notification setting does not send an invitation, change assignments, or approve translations.

## Settings

The Settings screen, normally `/translator/settings`, is restricted to administrators. It displays the configured main-server domain as read-only text, plus eight toggles and the Domains field. Change the main-server value through `INTERPRESSO_MAIN_SERVER_DOMAIN` or application configuration.

Each field has its own POST form and validation. With JavaScript, a toggle change or leaving the Domains input submits that field and reloads Settings. Without JavaScript, use the field's Save button. An invalid field does not block saving an unrelated valid field. The server refreshes cached settings after each successful write. Inline errors keep the attempted value for correction; reloading again shows the stored state. Enabling multi-host requires saved Domains, so save Domains first.

### Settings reference

These are the nine user-editable columns. Defaults below describe a fresh installation, not a saved upgrade configuration.

- **`db_loader`**, default `true`: selects the database translation loader instead of Laravel's ordinary file loader. Use it to publish approved application translations directly from the database without routine file export. Disable it when runtime language files are the required delivery format. The UI and normal CLI exports use model-only export when it is on; see the documented forced-export exceptions. Existing installations retain their saved true or false value when the default changes.
- **`import_vendor`**, default `false`: includes registered vendor namespaces during translation import. With DB loading enabled and this setting off, namespaced vendor translations continue to use the parent file loader. Turning it on makes those namespaces use database translations too, so import them before relying on DB delivery. Use it when translators should manage package/vendor text. It does not delete previously imported vendor records when disabled or filter existing vendor rows out of file exports.
- **`enable_open_ai_translations`**, default `false`: enables OpenAI calls for missing-translation generation and editor actions, and exposes the relevant buttons. Use it after configuring the optional integration. It does not automatically translate every existing row or approve generated text; enabling it without the integration leaves the service returning its input.
- **`enable_pending_notifications`**, default `false`: shows the manual pending-notification action in the translator edit form. Use it when administrators should request reminders for individual accounts. It does not schedule reminders and is not checked by the automatic notification command.
- **`enable_automatic_pending_notifications`**, default `false`: allows the automatic pending-notification command to iterate every translator's explicit assignments. Use your own schedule or enable `interpresso.schedule.pending_notifications`. It works independently of `enable_pending_notifications`; the command checks this saved setting at execution time.
- **`import_only_from_root_language`**, default `false`: limits translation imports, including model/vendor import passes, to the language matching `app.locale`. Use it when root-language sources are authoritative and other languages are maintained in the panel. It does not restrict Import Languages or delete existing non-root rows. Find Missing Translations can subsequently create root-language counterparts in the other languages.
- **`allow_deleting_languages`**, default `false`: exposes the language Delete links to administrators. Enable it when removing language records and their translations is intentional. The delete endpoint checks both administrator status and this toggle.
- **`enable_multi_host`**, default `false`: adds configured-host job checks and allows UI export/cancellation propagation. Leave it off for one project. When off, saved domains do not trigger those requests. When enabling it through Settings, a non-empty Domains field is required. Existing non-blank saved domains are enabled by the upgrade migration; an environment-only host list does not enable the feature.
- **`domains`**, default `null`: comma-separated installation URLs used for multi-host requests. Include `http://` or `https://`, for example `https://one.example,https://two.example`, with no trailing slash. The code trims list entries and appends the API route paths. Use this only for participating installations. When the saved value is null, `INTERPRESSO_MULTIPLE_DB_HOSTS` is the fallback; a saved empty string does not use that fallback. Turn multi-host off before clearing the field. The setting request enforces presence when enabled, but does not test URL syntax or reachability.

The table also contains internal `process_running`, `process_owner`, `process_started_at`, and `process_expires_at` fields, plus its ID and timestamps. They are not Settings controls; the controller rejects updates to fields outside the nine listed above. A lease blocks work only while running and unexpired. The migration adds nullable metadata without marking existing settings as locked.

## How Translations Are Served

### Database mode

`db_loader=true` is the default for new installations. The initial settings row and column default are true. The upgrade migration changes the default and clears cached loader selection without overwriting existing rows; rollback restores the false default without changing saved preferences.

Laravel's translation loader reads cached database entries for the requested locale/group/namespace. For approved rows it uses `value`; for unapproved rows it uses `old_value`. It does not use the draft value until approval. A newly generated unapproved row can have no old value, so the DB loader supplies no approved text for it. Requesting translation on an approved row does not create an old value, which can also leave it without usable text while unapproved. Vendor namespaces use files when `import_vendor` is off.

Validation messages retain Laravel's built-in defaults and the host application's `validation.php` overrides even before translations are imported. Database validation entries override those defaults using the same approved/old-value rules. Other application translation groups retain DB-only delivery.

The package's own interface also keeps its bundled or published text available when vendor import is enabled; reviewed database entries can override it.

DB loading itself writes nothing to `lang/`. With the normal DB workflow, import once, edit, and approve; there are no exported application translation files for a deployment to clobber. **`interpresso:export-translations-deployment` is unnecessary for DB-loader delivery.** Application translations remain in the database across filesystem deployments. Model translations still need export to their JSON columns in the application database.

The setting is not a global filesystem-write prohibition: Add Language creates a directory; the deployment command and inter-host forced-export endpoint do not pass the model-only flag and can write files even with `db_loader` enabled.

**The database becomes a hard dependency for rendering web requests in DB-loader mode.** Database failures propagate during web loader registration and uncached lookups. The database-exception fallback to Laravel's file loader exists only under `runningInConsole()` during loader registration. It does not cover later console lookups or provide an automatic web fallback during an outage. Cached entries can satisfy individual lookups, but are not a complete substitute for a working database.

### File mode

With `db_loader=false`, Laravel uses its file loader for runtime translation lookup. The package database still stores edits and review state. After approval, export PHP/JSON translations to `lang/`, and model translations to their model columns. Ordinary export requires Approved true, Updated false, and Exported false; forced export ignores only Exported.

Application PHP exports go to `lang/{locale}/{group}.php`, JSON to `lang/{locale}.json`, and vendor overrides under `lang/vendor/{namespace}/`. Exports merge eligible keys into existing content. PHP dotted keys are written as nested arrays: `a.b` becomes `['a' => ['b' => 'text']]`. Matching literal dotted keys from older PHP exports are removed when rewritten, while unrelated entries are retained. JSON keeps literal keys. Use a forced export in file mode to rewrite approved entries already marked exported.

File mode gives you runtime translation files that can be included in a deployment artifact. Those files must stay synchronized with the translation database, and a deployment that replaces them can lose the current exported text until a forced export restores it. DB mode avoids that deployment step but requires database/cache availability for serving translations. File mode does not remove the translation panel's own database dependency.

### Cache and model integration

**`CACHE_DRIVER` must not be `database` in DB-loader mode.** If the application's cache config uses `CACHE_STORE`, the same restriction applies there. Use Redis or Memcached, or a file cache for one server. Configure web processes and queue workers with the same cache store and prefix. Hosts sharing translations should share Redis or Memcached so cache version changes reach every host.

Translation cache entries have no TTL. Keys include a shared version advanced after translation writes commit, including package bulk imports, approvals, exports, and deletes. Targeted invalidation is retained. Application code performing bulk writes that bypass model events must call `Translation::invalidateCacheAfterWrite()` after a successful write so invalidation occurs after commit. Changing records with raw SQL without this hook can leave cached values stale.

Configure model classes in `interpresso.translatable_models`. Each model must expose its translatable column names, for example `public array $translatable = ['name'];`, and those columns must hold JSON objects keyed by locale. Imports copy existing locale values into Model rows. Export updates the relevant locale inside the existing model column and marks that translation exported. The target model and column must still exist. Approval in the translation panel alone does not update the application's model data.

## Single Project and Multi-Host

### Single-project operation

A single project is the default. Leave `enable_multi_host` off and Domains empty. No inter-host secret is needed for ordinary UI use. Imports, missing-translation creation, approvals, and normal export check local package jobs/batches. Export and cancellation stay local, even if an old domain list remains saved.

### Configure participating hosts

Use multi-host coordination for installations that need coordinated work and exports, typically using the same translation database through `INTERPRESSO_DB_CONNECTION`. Coordination does not replicate separate translation databases.

1. Point participating installations at the intended translation database and configure their queue/cache access.
2. Set the same `INTERPRESSO_API_SHARED_SECRET` on every participating host. It supplies `interpresso.api_shared_api_key`; outgoing requests send it as `api_key`.
3. Save the installation URLs in **Settings > Domains**, including the URL scheme.
4. Enable **Enable multi-host coordination**.
5. Keep `INTERPRESSO_MAIN_SERVER_DOMAIN` consistent with the main UI installation. Only the installation whose configured `app.url` matches that value registers the panel's web routes; API routes are still registered on enabled installations.

Enabled job gating asks other listed hosts whether work is running. Responses reporting active work block the operation. Unreachable hosts and non-success responses are ignored by this check; it is not a distributed lock or a guarantee that an unavailable host is idle. UI export completion requests forced exports on peers, while cancellation posts to peer cancellation endpoints. Requests skip a URL exactly matching the current request's scheme and host.

### Inter-host API

The package exposes these POST endpoints under `/api`, independently of the panel prefix:

- `/api/interpresso-has-jobs-running`: returns whether local package jobs or unfinished, uncancelled batches exist.
- `/api/cancelJobs`: cancels local package batches and deletes local package database-queue jobs.
- `/api/interpresso-force-export`: requires a deferring queue, acquires a local lease and queues a forced export for every language. Unsafe connections return HTTP 503 with a CLI replacement before any work starts. Returns HTTP 409 with owner/start/expiry metadata if local work is busy. It does not check peer locks because the calling peer is still finishing its own export. This endpoint can write files even in DB-loader mode.
- `/api/interpresso-get-languages`: returns language records for developer download.
- `/api/interpresso-get-paginated-translations`: returns translation records in pages of 500 for developer download.

Each requires a non-empty string `api_key` matching the shared secret. **The API fails closed with HTTP 503 when no shared secret is configured.** Request validation happens first, so a missing or invalidly typed `api_key` receives HTTP 422; a non-matching key with a configured secret receives HTTP 401. The public GET `/api/version` only reports the package version and does not use this authentication middleware.

Turning multi-host off stops automatic outgoing coordination requests; it does not unregister or disable these authenticated API endpoints. The developer-download command also uses the API independently of the toggle.

## Background Jobs and Queues

### What runs in the background

The UI queues language/translation imports, missing-translation generation, bulk approvals, exports, and update-and-auto-translate operations in Laravel batches. Missing-translation batches add further jobs as work is discovered. Jobs use `interpresso.queue_name` (`languageProcessor` by default), and batches use `interpresso.batch_name` (`languageBatch`). Pending reminders and administrative completion notifications also use the package queue.

Long-running translation work never runs inside an HTTP request, including after the response is flushed. Before any batch writes or lease acquisition, the UI and peer force-export endpoint inspect `queue.default` and `queue.connections.<connection>.driver`. Connection aliases are supported. `sync`, `null`, missing configuration, and Laravel's `deferred` driver are refused; failover is refused if any fallback is unsafe or cyclic. A configured asynchronous driver does not prove a worker is alive: queued jobs wait until one consumes them.

Choose one of these three supported modes:

1. **Supervisor / a long-running worker: best for real-time processing.** Set `QUEUE_CONNECTION=database` (or `redis`) and supervise a worker consuming `languageProcessor`, or your `interpresso.queue_name`. For example, run `php -d max_execution_time=0 artisan queue:work --queue=languageProcessor --timeout=900 --tries=1`, set the connection's `retry_after` above the job timeout (for example 960 seconds), and set `INTERPRESSO_PROCESS_LOCK_TTL=1800`. For SQS configure the equivalent visibility timeout. Size limits for the longest job and queue wait; inspect `failed_jobs` and application logs on failure.
2. **No Supervisor: recommended for shared hosting.** Set `QUEUE_CONNECTION=database`, enable `interpresso.schedule.queue_worker`, and add the single every-minute cron line below. The UI buttons work normally. Cron starts a bounded worker and drains ready jobs within a minute; long jobs and backlogs may continue over later ticks. No Supervisor installation or permanently running worker is needed.
3. **Sync: small installations only.** With `QUEUE_CONNECTION=sync`, use the UI for browsing and individual edits/reviews. The UI refuses long operations and names the matching Artisan command; run that command manually in the CLI. PHP CLI memory/time limits and hosting limits still apply. Small size never enables inline HTTP bulk work.

After changing queue configuration, rebuild the host's configuration cache if used (`php artisan config:cache`) and restart any long-running workers. With `null`, queued notifications are discarded.

A refused bulk action names its exact CLI replacement, for example `php artisan interpresso:import-translations`, and explains how a database queue plus the scheduler makes the button work. No data is written, no batch starts, and no operation lease is acquired. Update-and-auto-translate shows the same queue/scheduler setup guidance before saving the root draft. Single-row editing and approval remain available.

| UI/API action | CLI replacement |
| --- | --- |
| Import Languages | `php artisan interpresso:import-languages` |
| Import Translations | `php artisan interpresso:import-translations` |
| Find Missing Translations | `php artisan interpresso:find-missing-translations` |
| Approve all languages | `php artisan interpresso:approve-translations --translator=1` |
| Approve one language | `php artisan interpresso:approve-translations --translator=1 --language=en` |
| Export all languages | `php artisan interpresso:export-translations` |
| Export one language | `php artisan interpresso:export-translations --language=en` |
| Export models | Add `--only-models` to the appropriate export command |
| Peer API force export | `php artisan interpresso:export-translations-deployment` |

The approval toast supplies the signed-in administrator's actual ID, and language-specific toasts supply the selected language code. The authenticated peer force-export API returns HTTP **503** with a JSON `message` containing the force-export command if the connection cannot defer work. `interpresso:export-translations-deployment` matches the peer API by rewriting files and models even in DB-loader mode; the normal `--force=1` command respects DB-loader mode. Each receiving host needs a deferring connection and a worker for peer-triggered export. A supported queue with a live process lock still returns **409**.

### Cron without Supervisor {#cron-without-supervisor}

**This is the recommended setup for shared hosting.** In the host application's environment, enable the package worker schedule and use a persistent cache:

```dotenv
QUEUE_CONNECTION=database
INTERPRESSO_SCHEDULE_QUEUE_WORKER=true
CACHE_STORE=file
```

This enables `interpresso.schedule.queue_worker`; its default is `false`. On upgrades, add any missing options to the published configuration instead of overwriting local settings. Run `php artisan migrate` if queue/batch tables are missing, and rebuild cached configuration with `php artisan config:cache`. Add exactly one cron line, replacing the application path and PHP binary as needed:

```cron
* * * * * cd /path/to/app && /usr/local/bin/php83 artisan schedule:run >> /path/to/app/storage/logs/cron.log 2>&1
```

Use the explicit PHP CLI path supplied by your host, such as `/usr/local/bin/php83`. The default `php` in cron is often older than the PHP version serving the website, so a command that works in the browser can fail before Laravel starts. Check `/usr/local/bin/php83 -v` and the cron log before discarding output. The cron user needs write access to application storage and export paths; spawning background processes is optional. Confirm the registered schedule and test one drain with the same binary:

```bash
/usr/local/bin/php83 artisan schedule:list
/usr/local/bin/php83 artisan interpresso:work
```

`interpresso:work` wraps `queue:work` for `interpresso.queue_name` on the default connection, with `--stop-when-empty`, `--max-time=50`, `--max-jobs=100`, `--memory=96`, `--timeout=60`, `--sleep=0` and `--tries=1`. An empty queue exits 0 immediately. Work left after a budget is reached is picked up on the next tick; delayed/reserved jobs remain for later runs.

Configure `interpresso.queue_worker.max_time` (seconds), `interpresso.queue_worker.max_jobs`, `interpresso.queue_worker.memory` (MB), and `interpresso.queue_worker.timeout` (seconds per job). Their environment variables are `INTERPRESSO_QUEUE_WORKER_MAX_TIME`, `INTERPRESSO_QUEUE_WORKER_MAX_JOBS`, `INTERPRESSO_QUEUE_WORKER_MEMORY`, and `INTERPRESSO_QUEUE_WORKER_TIMEOUT`. All must be positive integers; zero/unlimited and malformed values are rejected. Time and memory budgets are checked between jobs. PHP CLI needs PCNTL for Laravel to interrupt a stuck job at its timeout; otherwise a hosting process limit is needed to bound a stuck job. Configure finite network timeouts too. Keep `retry_after` above the job timeout (or set SQS visibility accordingly) and `interpresso.process_lock_ttl` above the longest uninterrupted job and expected queue wait.

The worker is scheduled every minute with `withoutOverlapping`. `interpresso.schedule.worker_background` defaults to `true` (`INTERPRESSO_SCHEDULE_WORKER_BACKGROUND`). The scheduler checks both `function_exists('proc_open')` and PHP's `disable_functions`. When process spawning is unavailable, or background execution is disabled, it runs `Artisan::call` in a named foreground callback. Simply removing `runInBackground()` would still make Laravel launch a subprocess. Maintenance schedules also use callbacks when `proc_open` is unavailable. Its cache lock expires after `ceil((max_time + timeout) / 60) + 1` minutes, three minutes with defaults, allowing the last job to finish. Normal completion releases it earlier. Use a persistent cache such as file storage on one host, or a shared cache across hosts, never an in-memory array/null store. This scheduler cache lock prevents overlapping cron workers. The separate database `ProcessLock` protects translation operations across HTTP, CLI and batches; both are needed. Manual worker invocations are not protected by the scheduler mutex.

Queued exports (including force/model exports), approvals, missing-translation discovery and imports process at most `interpresso.chunk_size` source rows per job, then enqueue a cursor successor in the same batch. The default is `100`, configurable with `INTERPRESSO_CHUNK_SIZE`; lower it when a host kills short-lived processes. Missing translations with AI also respect `max_open_ai_missing_trans`. Worker restarts resume from the queued cursor; an abruptly killed reservation can replay its current slice after the queue's `retry_after`. Completed slices remain saved. Cursor jobs allow reservation retries, but a real processing exception fails the batch immediately. Batch progress uses an estimated total, with file-import estimates based on source size, and reaches 100% only on completion.

Database sources use ordered primary-key cursors. File imports use entry ordinals and reject source files modified between slices; keep input files stable until the batch finishes. Imports and missing-row creation preserve existing translations on replay. Exports merge keys with atomic file replacement. PHP/JSON inputs and existing export files still need parsing one file at a time, so split exceptionally large files if a single file exceeds the host's memory or process limit. A killed AI request can be billed again if its response was not yet saved.

The worker's `96` MB default leaves headroom on a 128-256 MB host. `INTERPRESSO_QUEUE_WORKER_MEMORY` or `interpresso:work --memory=64` changes the between-job memory bound; PHP's own `memory_limit` still applies within a job. `interpresso:work --max-time=30` overrides the configured time budget for one invocation. Reduce chunk size to shorten individual jobs; `--max-time` is checked between jobs and does not interrupt a running slice.

A host that permits cron only every 5 or 15 minutes still works: change the first cron field to `*/5` or `*/15`. Queued and delayed jobs start proportionally later, and a backlog may need several ticks. Every slice refreshes its database `ProcessLock` before and after work. Keep `INTERPRESSO_PROCESS_LOCK_TTL` above the cron interval plus a worker run and scheduling delay; the `1800` second default provides room for a 15-minute interval. On upgrades with a saved `900` second TTL, raise it for 15-minute cron. Heartbeats cannot run while PHP is stopped. Completion, failure or cancellation releases the batch's own lease.

The same configuration block offers independent opt-ins: `interpresso.schedule.prune_batches` runs `interpresso:prune-batches` every minute; `interpresso.schedule.pending_notifications` runs `interpresso:send-automatic-pending-translations-notification` daily at midnight in the scheduler timezone. Enable them with `INTERPRESSO_SCHEDULE_PRUNE_BATCHES=true` and `INTERPRESSO_SCHEDULE_PENDING_NOTIFICATIONS=true`. Both default to `false`, even when the worker is enabled. Maintenance runs before a newly scheduled worker; existing operation locks can still make a maintenance invocation skip. Automatic reminders also require the saved `enable_automatic_pending_notifications` setting and a working mail transport. That setting is checked when the command runs, so schedule registration needs no settings-table read.

All three package schedules are omitted when the configured driver cannot defer work, including sync/null/deferred/missing or unsafe failover connections. They do not schedule imports or approvals themselves: administrators continue to use the UI normally. To disable the cron worker, set `INTERPRESSO_SCHEDULE_QUEUE_WORKER=false` and rebuild cached configuration; an already running invocation finishes within its configured limits.

### Why an action may be blocked

Imports, find-missing, exports, bulk approval, update-and-auto-translate, and every single-row mutation (update, approve, request, remove request, restore) refuse to start while a live process lease, package job, or unfinished, uncancelled batch exists. Modal reads and AI draft suggestions remain available because they do not write translations. The working CLI commands use the shared guard too. Multi-host adds the peer checks described above; unreachable peers are ignored.

The working commands check the local advisory lock, package jobs, unfinished uncancelled batches, and configured peers when multi-host is enabled. They acquire the settings lease with one conditional database UPDATE before work starts, including under `QUEUE_CONNECTION=sync` and cron. A busy command reports the owner (host, PID, operation and invocation ID) and start time, then returns without doing work. Exceptions and PHP errors release the command's lease in `finally`.

`interpresso.process_lock_ttl` defaults to 1800 seconds and can be set with `INTERPRESSO_PROCESS_LOCK_TTL`. Expired leases and legacy flags without an expiry do not block acquisition. A long import renews its lease between files and model chunks through `ProcessLock::refresh()`; custom long-running operations should call `refresh()` on their acquired handle before the TTL elapses. Set the TTL above the longest uninterrupted unit of work. Separate databases still coordinate through best-effort HTTP checks, not a distributed atomic lock.

Controller mutations acquire the same lease before writing or dispatching. Queued batches retain ownership after the HTTP request returns. Their callbacks release it after completion or failure, and reserved jobs check cancellation and lease ownership before work. Old callbacks cannot clear a replacement owner. Blocked UI messages include the owner and start time. Cancellation, language/account management, and settings changes remain available.

The local guard and cancellation service query the `jobs` and `job_batches` tables on the default database connection. `interpresso.db_connection` configures package models and migrations but does not redirect every queue-table query. Keep the queue/batch setup consistent with those queries. A non-database queue is not cleared by deleting database `jobs` rows.

Administrative success notifications are delivered only for successful, uncancelled batches. Failures send error notifications instead. All four lock fields and the settings cache are cleared on completion, cancellation, dispatch failure and job failure; the CLI also clears them when a service throws a PHP error.

### Cancel and maintain batches

Use **Languages > Delete running Batch (Jobs)** to mark unfinished package batches cancelled and delete database `jobs` rows for the package queue. This can also remove queued package notifications. Cancellation does not roll back completed imports/edits/exports or terminate a worker already executing a job. Jobs check batch cancellation before entering their handlers, including jobs a worker has already reserved. Cursor jobs also check cancellation before adding their successor; an already executing slice may finish its writes. Inspect the result before starting replacement work.

With multi-host enabled, the same button requests cancellation on other configured hosts. It does not provide a per-job picker or a retry-failed-jobs screen.

Run `interpresso:prune-batches` to remove old finished/cancelled batch records. It does not cancel active work or delete queued/failed jobs. Enable `interpresso.schedule.prune_batches` for minute-by-minute cleanup and `interpresso.schedule.pending_notifications` for daily reminders. Both are optional and require a deferring queue connection.

## Permissions and Shared Controls

### Administrator and translator access

Administrators can access all language records, translations, translator management, and Settings. Their UI includes language creation/deletion, imports, missing-translation generation, bulk approval/export, cancellation, and the translation approval/request/restore controls.

A non-admin translator's normal UI allows them to:

- List and search assigned languages and open their translations.
- Search, paginate, and use every translation filter on those languages.
- Choose an example language, open Translate, read the available examples, and update text for review.
- Use OpenAI translation when its toggle and source/example conditions are met.
- Read this manual, use their notification controls, switch theme, and log out.

The UI does not show non-admins the bulk language toolbar, approval/request/restore controls, export controls, translator management, or Settings. It has no non-admin account-management screen.

All privileged endpoints enforce administrator authorization on the server. Every per-row translation action resolves its ID through the requested language and checks the translator's language assignments; a foreign row ID returns 403 without changing data. Non-admins can edit permitted translations and request draft suggestions, but cannot approve, request, restore, bulk-update, export, manage accounts, change settings, or run language maintenance. Notification reads, unread changes, and mark-all operations are scoped to the authenticated translator and notifiable type.

### Navigation, theme, and notifications

Navigation includes Languages and Manual for signed-in translators, with Translators and Settings added for admins. Open the compact navigation menu on small screens. The theme button switches light/dark mode and saves the preference in a cookie and browser storage. The server renders a saved cookie preference before the page paints. Without a saved choice, the stylesheet follows the system preference before JavaScript runs, and the page continues following system changes until you choose a theme.

The interface uses DaisyUI components with light and dark themes across forms, tables, menus, notifications, and the translation editor. Settings use visible checkbox switches, and the editor retains its close button, Escape key, and outside-click dismissal.

The notification button shows the unread count. Open it to read messages in the panel above the button, close the panel without marking them read, dismiss one message to mark it read, or use **Mark all as read**. The panel scrolls when needed. Notifications refresh every five seconds while the tab is visible and pause in hidden tabs. Unchanged refreshes preserve the existing message controls and focus. Immediate toasts are separate: success, deleted, info, or warning, with a dismissal button and timer.

An existing `localStorage["color-theme"]` preference migrates into the `interpresso-color-theme` cookie when the external theme module first runs. A valid cookie takes precedence over browser storage and updates both `.dark` and `data-theme`. On the first visit after upgrading, a storage-only preference cannot be known by the server and may replace the system theme after the module loads; subsequent requests render the cookie preference immediately. Theme toggling still works when localStorage is unavailable.

The preference uses the package's URL prefix as its cookie path, normally `/translator`. Saving it removes a duplicate at the trailing-slash path (`/translator/`) so an older, more specific cookie cannot override the new choice on the next request.

The package enables strict browser security headers by default. Its scripts and styles load from external files, toast data is stored in an escaped HTML attribute, and the UI cannot be embedded in a frame. CDN deployments must configure allowed asset origins in `interpresso.security_headers.extra_sources`; see [Configuration](CONFIGURATION.md#browser-security-headers). These headers apply only to package web routes.

### Interface language

On any page, including login, choose English, Deutsch, Français, Español, or Italiano in the navbar and press **Change language**. The next page response renders the chosen language immediately and also selects the manual. This form works without JavaScript.

Signed-in changes are saved to the translator's nullable `locale` field and the `interpresso-locale` cookie. Anonymous changes use only the cookie. It lasts one year and follows the package URL prefix, normally `/translator`; it is encrypted, HttpOnly, SameSite=Lax and Secure on HTTPS. The account preference survives logout and a new login, including in another browser.

Resolution uses the first available value: translator preference, cookie, `interpresso.locale`, then `app.locale`. Invalid values are ignored; if none is supported, English is used, or the first available locale if English was removed. Submitting an unknown language is rejected without saving. Choices come from the package's `lang/` directories, cached for the application lifetime. Restart long-running application workers after adding translations. Set `global.locale_name` in a new catalogue for its native label; otherwise its code is shown.

Admins can set or clear **Interface language** in the translator create/edit profile form. **Browser / default** clears the account preference and resumes the cookie/configuration fallback. The choice leaves translation assignments, import source language and host application settings unchanged.

Upgrades add a nullable `locale` column without backfilling existing accounts. Run the package migrations and refresh published views if your application overrides them.

## CLI Reference

Run commands as `php artisan ...` from the host Laravel application's directory. These are all eleven command signatures in `src/Console/Commands/`. The working commands acquire the shared process lease and can return early with the current owner and start time; that early return is not a completed operation. Commands use the saved settings unless an exception is specified below.

### interpresso:import-languages

Signature:

```text
interpresso:import-languages
```

Imports supported locale directories directly under Laravel's language path, skipping codes already recorded. It does not discover languages solely from `{locale}.json` files or import translation content. Runs synchronously and queues an administrator result notification. Use it for the first import or after adding locale directories.

```bash
php artisan interpresso:import-languages
```

### interpresso:import-translations

Signature:

```text
interpresso:import-translations
```

Imports PHP/JSON sources and configured model translations for existing languages. Respects `import_vendor` and `import_only_from_root_language`. Existing language/shared-identifier pairs are retained; new imports start approved/exported. It reports total existing and newly inserted translations. Use it after introducing new source keys or model values, after importing the language records. It does not update existing translated values from changed files.

```bash
php artisan interpresso:import-translations
```

### interpresso:find-missing-translations

Signature:

```text
interpresso:find-missing-translations
```

Checks translation counts per language and, when counts differ, invokes root-language missing-counterpart creation. Empty languages are represented separately in that check. Uses `app.locale` as the source, or the first language when absent, and can use OpenAI. New rows are unapproved, need translation, and are not exported. It reports inserted counts and queues a root-language administrator notification when that root record exists.

Use it after importing root-language keys or adding languages. **Limitation:** equal row counts can hide different key sets; the CLI then says `Everything up to date.` without checking identifiers. The UI Find Missing Translations action calls the service without this count shortcut.

```bash
php artisan interpresso:find-missing-translations
```

### interpresso:approve-translations

Signature:

```text
interpresso:approve-translations {--translator=} {--language=}
```

Synchronously approves all unapproved translations, or only those in `--language=en`. `--translator=ID` is required and must identify an existing administrator translator; approvals are attributed to that ID. Invalid attribution or an unknown language exits with status 1 without writes. The command uses the shared process lease, refreshes it between languages, invalidates translation caches through the existing approval service, and sends administrator result notifications. It works under `QUEUE_CONNECTION=sync` without a worker. Review translations before invoking it.

```bash
php artisan interpresso:approve-translations --translator=1
php artisan interpresso:approve-translations --translator=1 --language=en
```

### interpresso:export-translations

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

### interpresso:export-translations-deployment

Signature:

```text
interpresso:export-translations-deployment
```

Synchronously force-exports each language's approved, not-updated translations, including models, ignoring the Exported flag. It takes no `--force` option. It uses the shared process guard, has no peer-export propagation, and does not pass the DB-loader model-only setting, so it can write files even when `db_loader=true`.

Use it after a file-mode deployment that replaced exported translations. It is unnecessary for DB-loader delivery; omit it from that deployment workflow. It prints a per-language completion message even if there was no eligible content.

```bash
php artisan interpresso:export-translations-deployment
```

### interpresso:work {#interpressowork}

Signature:

```text
interpresso:work [--max-time=SECONDS] [--memory=MB]
```

Drains only the configured package queue, then exits when empty or when a time/job budget is reached. Remaining work continues on the next cron tick. Returns 0 for an empty queue or a normal budget stop, 1 for non-deferring connections or invalid limits, and otherwise forwards the underlying worker exit code. Worker failures can still be recorded even when the worker exits 0; inspect the failed-job records and logs. Use `--max-time` and `--memory` to override those limits for one invocation; configure all defaults as described in [Cron without Supervisor](#cron-without-supervisor).

```bash
php artisan interpresso:work
```

### interpresso:prune-batches

Signature:

```text
interpresso:prune-batches
```

Deletes matching `interpresso.batch_name` rows from `job_batches` on `interpresso.db_connection` when their finished or cancelled timestamp is older than `interpresso.prune_batch_hours`, default 24 hours. It does not touch active batches, queue jobs, or failed-job records. There are no command-specific options or completion output.

Use it for retained-batch housekeeping. Enable `interpresso.schedule.prune_batches` for automatic cleanup every minute, or arrange your own schedule.

```bash
php artisan interpresso:prune-batches
```

### interpresso:send-automatic-pending-translations-notification

Signature:

```text
interpresso:send-automatic-pending-translations-notification
```

This is the actual command name implemented by `SendAutomaticPendingNotifications`; `interpresso:send-automatic-pending-notifications` is not a registered alias.

When `enable_automatic_pending_notifications` is true, it iterates every translator, including administrators, and their explicit language assignments. For each language with Needs Translation rows, notifications are queued for database and mail delivery. Zero pending rows produce no delivery. When the toggle is false, the command does nothing. It does not require `enable_pending_notifications`, uses the shared process guard, and prints no success summary.

Use it for recurring reminders with a working mail transport and either a long-running or cron worker. Enable `interpresso.schedule.pending_notifications` for the daily schedule, or arrange your own. Running it again can send another reminder for the same pending work.

```bash
php artisan interpresso:send-automatic-pending-translations-notification
```

### interpresso:developer-download

Signature:

```text
interpresso:developer-download
```

Downloads languages and paginated translations from `interpresso.main_server_domain`, configured through `INTERPRESSO_MAIN_SERVER_DOMAIN`, using `INTERPRESSO_API_SHARED_SECRET`. It replaces local language and translation rows, then force-exports the downloaded approved, not-updated content. File mode exports files and models; DB-loader mode exports models only. Settings, translator accounts, and assignments are not downloaded.

Use it only to intentionally replace a development copy with the main server's translation data. **This deletes/replaces local translation work without a confirmation prompt.** The shared process guard applies. There is no local-environment guard, dry-run flag, host argument, or merge mode. The database phase uses a transaction and MySQL/MariaDB `SET FOREIGN_KEY_CHECKS` statements, so it is not portable to SQLite or PostgreSQL as written. Export happens after that transaction commits; an export failure does not undo the downloaded database replacement. Local model targets must exist for model export.

```bash
php artisan interpresso:developer-download
```

### interpresso:unlock

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

## Troubleshooting

### Jobs do not finish or another process is reported

For asynchronous queues, confirm that a worker consumes the configured queue, normally `languageProcessor`. Cron/artisan commands under `QUEUE_CONNECTION=sync` require no worker for translation work; bulk HTTP actions always refuse sync. Check the reported process owner, start time, and expiry; use `interpresso:unlock` to clear stale metadata, or `--force` only after verifying the old run is stopped. Check the package queue's `jobs` records, unfinished `languageBatch` batches, application logs, and failed-job records. Pending notifications can also keep this queue occupied. A worker listening only to the default queue will not process the package queue.

After checking whether work is still executing, use **Delete running Batch (Jobs)** for abandoned work. Pruning old batches is not cancellation. For multi-host installations, check peers and shared secrets; an unavailable peer is ignored during the busy check but export propagation can fail. Review the queue-table connection and non-database-queue limits above.

### A language, key, or source example is missing

Import Languages only recognizes supported locale directories. Add a language explicitly for JSON-only sources. Ensure `lang/` exists before importing; DB mode does not create missing source directories during import. Make sure the root/fallback language records exist and refresh the list after background imports.

Import Translations only visits stored languages, and root-only/vendor settings can exclude sources. Re-import does not overwrite an existing value. Find Missing Translations copies root identifiers, not keys unique to another language, and the CLI's equal-count shortcut can miss differing key sets. Use the UI action in that case. A missing `app.fallback_locale` record prevents the editor mounting; recreate that language.

### Filters or settings appear not to save

Search and filters reload the page with query parameters. Settings fields submit independently on change. Wait for navigation to finish before reloading, and check inline errors when a setting is rejected. Save Domains before enabling multi-host, and disable multi-host before clearing Domains. If controls do nothing, rebuild and republish the package assets, then check for failed JavaScript or network requests. Batch and notification polling intentionally pause in hidden tabs.

### Approved content is not visible or files are unchanged

In file mode, approve edits and export them. Normal exports skip unapproved, updated, or already-exported rows; use `--force=1` only when re-exporting approved, not-updated content is needed. Verify filesystem permissions and PHP/JSON source validity. Malformed existing JSON and JSON encoding failures abort an export rather than silently replacing the file with invalid content.

In DB mode, absence of file writes in the normal workflow is expected. Check the stored approval and old-value state, cache configuration, and shared cache prefix. A false Exported flag does not prevent DB delivery. Model columns still need model export. The deployment command and peer forced-export endpoint can write files despite the DB setting.

### Database, cache, or settings failures

Restore database connectivity for a DB-mode web outage; there is no automatic web fallback to files. `CACHE_DRIVER`/`CACHE_STORE` must use a non-database store, and workers and web processes must share the appropriate cache configuration. Package writes invalidate versioned entries after commit; external bulk writes need the invalidation hook.

If the settings table is absent or has no row, loader selection can choose Laravel's file loader. Package operations that require settings throw `MissingSettingsException` when its row is absent. Restore the saved settings row; the package does not recreate it or replace saved preferences automatically. After repairing settings outside the UI, refresh its cached representation with `Setting::getFreshCached()` in application maintenance code.

### OpenAI or pending email does nothing

Check the relevant toggle, optional OpenAI package and API configuration, selected example, and application logs. OpenAI failures can return source text unchanged. Editor buttons have root/non-root and example-presence conditions; generated text still needs review and approval.

Pending reminders count Needs Translation, not every unapproved row. Check explicit language assignments, mail transport and the queue worker. Manual and automatic reminder settings are independent. For daily reminders, also enable `interpresso.schedule.pending_notifications`. Marking an on-screen notification read does not change translation state.

### Access, routes, and inter-host errors

For a missing UI route, check `INTERPRESSO_ENABLED`, the configured route prefix, and the exact match between the main-server value and `app.url`. For a non-admin 403, check assignments and whether the requested screen is admin-only. For a missing row action, check its approval/request/old-value conditions.

Inter-host HTTP 503 means no shared secret is configured after request validation; HTTP 401 means a mismatch, and HTTP 422 can mean a missing or invalid `api_key`. Configure the same secret on each host and use installation URLs with a scheme. The multi-host switch does not disable the API itself.

### Import, model-export, or developer-download exceptions

Check the logged path/error identifier for invalid source files or insert errors. Null source values, namespaces, or groups are not valid missing-translation copy metadata. Application translations use an empty namespace; JSON translations use an empty group. Model exports require an Eloquent model class, an existing record, and an existing JSON translation column. Missing targets raise an exception instead of being silently marked exported.

Developer download requires compatible MySQL/MariaDB SQL, reachable main-server API endpoints, and the matching shared secret. It replaces local records and commits before exporting, so inspect which phase failed before repeating it. Existing local translator/assignment data is not synchronized by the download.

<!--
UI styling update: preserve the rendered manual wording for the visual-only change.
The colour-based state-filter instructions above refer to the previous styling.
Current state cycle: outlined = unrestricted; filled with tick = true; filled with cross = false.

Button colours indicate consequences: green for approval, red for deletion or removing a translation request, amber for restoration, blue for export, and primary for import, finding missing entries and translation. Close and Search use quiet ghost buttons. Filter toggles are outlined until a selection is applied, then filled. Row actions are compact and wrap with small gaps; page actions are slightly larger. Tables use compact striped rows and sticky headings within the scroll area. Boolean ticks mean Yes and muted crosses mean No, with translated accessible labels. Search and its button form one joined control. These conventions apply in both light and dark themes.
-->
