#!/usr/bin/env bash
# Serves the package standalone for Playwright, on a file-backed sqlite database
# so the server process and the seeder share state. Testbench otherwise defaults
# to an in-memory "testing" connection, which the HTTP process cannot share.
set -euo pipefail
cd "$(dirname "$0")/../.."
export DB_CONNECTION=sqlite
# config(interpresso.db_connection) falls back to database.default, which Testbench
# pins to its in-memory "testing" connection; name the connection explicitly.
export INTERPRESSO_DB_CONNECTION=sqlite
export DB_DATABASE="$PWD/tests/e2e/.data/e2e.sqlite"
export INTERPRESSO_E2E=1
export CACHE_STORE=file
export SESSION_DRIVER=file
export QUEUE_CONNECTION=sync
# The layout references the package's published assets. Mirror them into the
# Testbench skeleton's public directory so the browser gets real CSS and JS
# rather than 404s.
PUBLIC_DIR="vendor/orchestra/testbench-core/laravel/public/vendor/interpresso"
mkdir -p "$PUBLIC_DIR"
cp -R public/css public/js "$PUBLIC_DIR"/

exec ./vendor/bin/testbench serve --port="${PORT:-8099}"
