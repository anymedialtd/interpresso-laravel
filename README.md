# Interpresso for Laravel

A Laravel translation management UI for PHP/JSON language files and Eloquent model translations. Import languages and translations, assign translators to languages, review and approve changes, and export approved content.

```bash
composer require anymedialtd/interpresso-laravel
```

Version 2.0.0 uses server-rendered Blade, ordinary GET navigation and POST forms, and vanilla JavaScript ES modules. DaisyUI and Tailwind 4 provide the interface; Vite builds the assets. There is no Livewire or Alpine runtime. The UI enforces a strict Content Security Policy without inline JavaScript or `eval`.

Requires PHP `^8.2` and Laravel `^12.0 || ^13.0` (Laravel 13 requires PHP 8.3+). New installations load translations from the database by default. Background operations and notifications use Laravel's queue; model translations still need export to their application columns. Translators have a separate authentication guard and language-scoped access.

Start with the [installation guide](docs/INSTALLATION.md), [application manual](docs/APPLICATION_MANUAL.md), and [documentation index](docs/README.md). Existing installations should follow [Adopting the Interpresso identity](docs/INSTALLATION.md#adopting-the-interpresso-identity) and [Upgrading from 1.x](CHANGELOG.md#upgrading-from-1x), including the model identifier and PHP export format changes.

Licensed under the [MIT license](LICENSE). Copyright Saverio Migale.
