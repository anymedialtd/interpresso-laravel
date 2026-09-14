# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and releases use
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **Breaking: Interpresso identity.** The Composer package is `anymedialtd/interpresso-laravel`, with the `AnyMedia\Interpresso\` PHP namespace and `InterpressoServiceProvider` / `InterpressoTranslatorServiceProvider`. Configuration, view/translation namespaces, routes, commands, publish tags, middleware, API endpoint prefixes, assets and the theme cookie use the Interpresso identity. Environment variables use `INTERPRESSO_`.
- Default package tables, the translator guard and cache prefix now use `interpresso_`. Table access remains configurable; existing installations must retain their current table names in `config/interpresso.php` or migrate the tables themselves. Configured screen URL segments and the Language, Translation and Translator model names are unchanged. See [the adoption guide](docs/INSTALLATION.md#adopting-the-interpresso-identity).

### Removed

- Internal planning and PHPUnit backup artifacts.

## [2.0.0] - 2026-09-14

This release covers `git log 0685734..HEAD` and the accompanying background-process
verification and release preparation.

### Added

- Strict Content Security Policy and security response headers on the translation UI, with configurable policy settings and a CSP-compatible theme cookie.
- Translation-table indexes with short, explicit names and MySQL-safe rollback.
- Opt-in multi-host coordination through the `enable_multi_host` setting.
- Versioned translation cache keys and targeted invalidation after committed model changes and bulk writes.
- HTTP, browser and queue completion coverage for forms, authorization, imports, missing translations, approvals, file/model exports, notifications, gating, progress and cancellation. CLI coverage includes the eight documented commands and export force variants.
- CI for PHP 8.2/8.3/8.4 with Laravel 12/13 where compatible, PHPStan level 10, dependency audits, asset builds, Playwright, and a separate MySQL migration and developer-download suite.
- MIT license file matching Composer metadata.

### Changed

- **Breaking: Livewire removed.** Controllers and server-rendered Blade replace Livewire components. Search, filters and pagination use full-page GET requests; mutations use POST forms and redirects. Settings save independently per field. Vanilla ES modules handle modals, suggestions, notifications and progress. Custom Livewire components, bindings and overrides must be ported.
- **Breaking: Flowbite replaced by DaisyUI.** Custom views and styling must adopt the new components and markup. Tailwind 4 builds through `@tailwindcss/vite`, using the ESM `vite.config.mjs` entrypoint.
- Supported runtime is now PHP `^8.2` and Laravel `^12.0 || ^13.0`; Laravel 13 requires PHP 8.3+. Older Laravel majors are no longer supported.
- New installations default to `db_loader=true`; upgrades preserve existing saved values. In DB mode, normal exports select model translations, while application PHP/JSON translations are served from the database.
- Multi-host job checks, cancellation and export propagation are disabled until explicitly enabled; saved domains alone no longer activate coordination.
- Documentation and the in-app manual describe the controller UI, settings, queues, all eight commands and operational limits.

### Removed

- **Breaking: GitHub pull requests on export removed.** Export no longer creates branches or pull requests. The GitHub export service, job, controls and related configuration are gone. Move any required repository workflow to deployment tooling.
- Livewire/Alpine and Flowbite runtime dependencies, legacy event adapters, unused dependencies, and the old PostCSS/autoprefixer configuration. Assets use Vite, not Laravel Mix.

### Fixed

- **Data format change requiring operator review:** model translation `shared_identifier` values no longer incorporate the previous key's group. Existing rows retain their old identifiers; the release does not rewrite them. Audit and reconcile affected model rows, then re-import where needed. Back up reviewed values first, since corrected identifiers can create new rows beside legacy rows.
- **Data format change requiring file-mode re-export:** PHP exports expand dotted database keys into nested PHP arrays and remove matching legacy literal dotted keys. JSON keys remain literal. File-mode installations should force a re-export with `php artisan interpresso:export-translations --force=1` after reviewing and approving eligible content.
- Malformed existing JSON and JSON encoding failures raise export errors instead of silently destroying exported translation files.
- Missing settings, languages, model records/columns and invalid OpenAI responses receive explicit handling. Translation inserts no longer suppress unrelated database failures.
- The index migration restores a supporting single-column foreign-key index before dropping composites, so MySQL rollback succeeds.
- Settings cache stores attribute arrays instead of serialized Eloquent models, avoiding incomplete-class failures on Laravel 13. Updating the running flag refreshes its cached value.
- DB-loader setting changes take effect across requests; translation values preserve meaningful whitespace. Modal replacement, notification read state and theme-cookie behavior have regression coverage.
- Cancelled batches skip jobs that have not started, including reserved jobs. Success notifications and peer exports run only after successful, uncancelled batches. Running flags clear after success, failure, dispatch failure and CLI exceptions, including PHP errors.
- PHPUnit tests use attributes compatible with PHPUnit 12; PHPStan level 10 checks the full source tree without a baseline.

### Security

- Inter-host authentication fails closed: `INTERPRESSO_API_SHARED_SECRET` is required. An unset server secret returns HTTP 503, an incorrect key returns 401, and invalid input returns 422. The permissive shared-key fallback is removed; responses use real HTTP error statuses.
- Translation row actions resolve records inside an authorized language, preventing access to another translator's rows through forged IDs. Privileged mutations require administrator middleware; notification actions are scoped to the authenticated translator.
- The UI avoids inline scripts, inline event handlers and `eval`; CSP, CSRF, authentication and authorization checks have HTTP/browser regression coverage.
- Composer and npm dependencies were refreshed and unused packages removed. Support for older Laravel versions affected by unresolved advisories was dropped.

### Upgrading from 1.x

1. Back up the translation database, exported `lang/` files, configuration and customized package views. Complete or cancel queued work and coordinate the upgrade with workers.
2. Upgrade to PHP `^8.2` and Laravel `^12.0 || ^13.0`. Use PHP 8.3+ for Laravel 13. Update the package and dependencies in the host application; remove package-specific Livewire/Flowbite integrations and port custom bindings to the Blade/form/ES-module architecture.
3. Review published configuration against the new defaults. Set the same strong `INTERPRESSO_API_SHARED_SECRET` on every host using the inter-host API, including developer-download sources. Without it, that API fails closed. Enable multi-host coordination explicitly if needed and confirm the domains.
4. Run the new migrations. If migrations were published into the host, synchronize them, including the corrected index rollback. Existing `db_loader` values are preserved; new installations default to DB loading. Confirm the intended mode in Settings.
5. Re-publish compiled assets with `php artisan vendor:publish --tag=interpresso-public --force`. Customized views must be re-published/ported: back up overrides, publish the new views with `php artisan vendor:publish --tag=interpresso-views --force`, and reapply customizations to the DaisyUI markup. Clear compiled views and restart workers after deployment.
6. Audit model translation identifiers as described above. Reconcile legacy rows before re-importing affected model translations so reviewed content is retained and duplicate counterparts are avoided. Ordinary import skips existing identifier/language pairs; it does not repair old rows in place.
7. For file-mode installations (`db_loader=false`), force a re-export with `php artisan interpresso:export-translations --force=1` to write the nested PHP format. The option requires a value. Only approved, not-updated rows qualify; force does not bypass those checks or the running-job guard. DB-loader installations still export model values when required.
8. Replace any former GitHub pull-request-on-export workflow with external tooling. Verify translator access, run the worker for `languageProcessor` (or the configured queue), and confirm completion notifications. Schedule pruning and automatic pending reminders in the host if desired; the package does not register active schedules.

See the [application manual](docs/APPLICATION_MANUAL.md), [CLI reference](docs/CLI_COMMANDS.md),
and [development guide](docs/DEVELOPMENT.md) for configuration and verification commands.
