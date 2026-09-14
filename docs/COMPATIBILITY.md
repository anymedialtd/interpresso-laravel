# Compatibility

## Laravel

The package Composer constraints currently allow:

- `php`: `^8.2` (CI is configured for PHP 8.2, 8.3 and 8.4)
- `illuminate/support`: `^12.0 || ^13.0`
- `orchestra/testbench`: `^10.0 || ^11.0`

Laravel 9, 10 and 11 were dropped because every released version of those
majors is blocked by unresolved Composer security advisories. `config.platform.php`
is pinned to `8.2.0` so the lock file always resolves at the declared floor.
Testing against Laravel 13 needs PHP 8.3+, because `orchestra/testbench ^11`
requires it.

## UI runtime

The interface uses server-rendered Blade, ordinary HTML forms and vanilla JavaScript.
No Livewire or Alpine runtime is required. DaisyUI components and Tailwind 4 replace
Flowbite. The UI enforces a strict CSP with external scripts and styles. Republish
compiled assets and port customized views to the new markup when upgrading to 2.0.0.
See [Upgrading from 1.x](../CHANGELOG.md#upgrading-from-1x).

## Build Tooling

Asset pipeline is Vite-based:

- `vite.config.mjs` (`.mjs` so Vite 8 loads it as ESM)
- npm scripts in `package.json`

Tailwind 4 is wired through `@tailwindcss/vite`. There is no `postcss.config.js`:
`postcss` and `autoprefixer` were removed, since Tailwind 4 handles vendor
prefixing via Lightning CSS.

`laravel-mix` is intentionally not used.

## Optional Integrations

- OpenAI translation support is optional and only enabled when `openai-php/laravel` is installed.
