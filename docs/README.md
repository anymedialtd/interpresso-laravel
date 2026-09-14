# Documentation Index

- [Installation](INSTALLATION.md)
- [Configuration](CONFIGURATION.md)
- [Application Manual](APPLICATION_MANUAL.md)
- [CLI Commands](CLI_COMMANDS.md)
- [API Reference](API_REFERENCE.md)
- [Architecture](ARCHITECTURE.md)
- [Development](DEVELOPMENT.md)
- [Form and Control Audit](FORM_CONTROL_AUDIT.md)
- [Compatibility](COMPATIBILITY.md)
- [Troubleshooting](TROUBLESHOOTING.md)
- [Agent Guide](../AGENTS.md)

Each translator can choose the interface language from the navbar, including on
the login page. English, German, French, Spanish and Italian are included, with
preferences resolved as described in [Configuration](CONFIGURATION.md#interface-language).
The in-app manual uses the matching
`APPLICATION_MANUAL.{locale}.md`, falling back to the English original when absent.
The English original remains authoritative. Translations:
[Deutsch](APPLICATION_MANUAL.de.md), [Français](APPLICATION_MANUAL.fr.md),
[Español](APPLICATION_MANUAL.es.md), [Italiano](APPLICATION_MANUAL.it.md).

## Quick Start

1. Install the package and migrate:
   - `composer require anymedialtd/interpresso-laravel`
   - `php artisan vendor:publish --tag=interpresso-config`
   - `php artisan vendor:publish --tag=interpresso-migrations`
   - `php artisan migrate`
2. Build/publish package assets:
   - `npm install`
   - `npm run production`
   - `php artisan vendor:publish --tag=interpresso-public`
3. Login at `/translator/login` (or your configured prefix).
4. Change the default admin password immediately.
5. Import languages/translations from the UI or CLI.
6. Open the in-app manual at `/translator/manual`.
