# Installation

## 1. Require Package

```bash
composer require anymedialtd/interpresso-laravel
```

Laravel package auto-discovery loads:

- `AnyMedia\Interpresso\InterpressoServiceProvider`
- `AnyMedia\Interpresso\InterpressoTranslatorServiceProvider`

Optional (only for OpenAI-powered translation suggestions):

```bash
composer require openai-php/laravel
```

## 2. Publish Files

```bash
php artisan vendor:publish --tag=interpresso-config
php artisan vendor:publish --tag=interpresso-migrations
php artisan vendor:publish --tag=interpresso-public
```

Optional publishes:

- `php artisan vendor:publish --tag=interpresso-translations`
- `php artisan vendor:publish --tag=interpresso-views`

## 3. Run Migrations

```bash
php artisan migrate
```

The package creates language tables and also queue-related tables (`jobs`, `job_batches`, `failed_jobs`) if missing.

## 4. Build Front-End Assets

Inside this package repository, assets are built with Vite:

```bash
npm install
npm run production
```

Published runtime assets are served from:

- `public/vendor/interpresso/css/app.css`
- `public/vendor/interpresso/js/app.js`

## 5. Access the UI

Default routes:

- Login: `/translator/login`
- Languages: `/translator/languages`
- Translators: `/translator/translators`
- Settings: `/translator/settings`

The prefix and path segments are configurable in `config/interpresso.php`.

## 6. Initial Credentials

By default, migration `2023_02_02_205158_create_admin_translator.php` creates:

- Email: `admin@admin.com`
- Password: `aaaaaaaa`

Change this password immediately after first login.

## 7. First-Time Data Import

After login (admin):

1. Go to **Languages**.
2. Run **Import Languages**.
3. Run **Import Translations**.
4. Optionally run **Find Missing Translations**.

Equivalent CLI:

```bash
php artisan interpresso:import-languages
php artisan interpresso:import-translations
php artisan interpresso:find-missing-translations
```

## Adopting the Interpresso identity

Use `anymedialtd/interpresso-laravel` in the host application's Composer requirements and `AnyMedia\Interpresso\` in PHP imports. Manual provider registration uses `AnyMedia\Interpresso\InterpressoServiceProvider` and `AnyMedia\Interpresso\InterpressoTranslatorServiceProvider`.

Move the package configuration to `config/interpresso.php` and use `interpresso.*` config keys and `INTERPRESSO_*` environment variables. Before running migrations, set every `table_*` option to the existing database table name if retaining an existing database. The new defaults are `interpresso_languages`, `interpresso_translations`, `interpresso_translators`, `interpresso_settings` and `interpresso_language`; the package does not rename existing tables or copy their data.

Update named routes to `interpresso.*`, commands and schedules to `interpresso:*`, middleware references to their `interpresso` aliases, and view/translation calls to `interpresso::`. Coordinate all API hosts: the branded API endpoints now start with `/api/interpresso-`. The configured UI URL segments, including `/translator/languages`, retain their values.

Move customized templates and interface translations to `resources/views/vendor/interpresso` and `lang/vendor/interpresso`. Publish assets with `php artisan vendor:publish --tag=interpresso-public --force` and use `public/vendor/interpresso` asset paths. The other publish tags are `interpresso-config`, `interpresso-migrations`, `interpresso-views` and `interpresso-translations`.

Complete or cancel queued work before switching PHP namespaces; serialized pending jobs and persisted notification model types may otherwise refer to unavailable classes. Migrate stored polymorphic type values to the new namespace when retaining notifications. Clear compiled configuration and views, then restart queue workers. The default cache prefix is `interpresso_cache`, the default translator guard is `interpresso_translator`, and the display preference cookie is `interpresso-color-theme`; translators should sign in again after the upgrade.

## Mail required for translator onboarding

**Working SMTP is now a hard requirement. A newly created translator has no password and cannot complete a first login without receiving the invitation email.** Configure Laravel's mail transport and sender before adding accounts. Creation sends a **Set password** link immediately; the account edit page offers **Resend invitation** until a password is set. Administrator profile forms cannot set passwords.

Set the initial administrator's email to an address you control, then log out and use **Forgot your password?** to replace the seeded password. Password recovery uses a persistent queue, default `database`, with a worker on `languageProcessor`: `php artisan queue:work database --queue=languageProcessor`. All valid recovery requests enqueue the same work, keeping account lookup and SMTP timing outside the public response.

Run the new reset-token migration on the configured package database. Set custom token-table names before migrating and publish updated assets, views and all five language catalogues on upgrades. See [password reset configuration](CONFIGURATION.md#translator-password-reset-and-invitations) for expiry, queue configuration, delivery failures and rollout details.
