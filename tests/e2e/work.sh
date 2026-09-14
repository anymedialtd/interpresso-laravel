#!/usr/bin/env bash
# Consume the same disposable database queue used by the real browser server.
set -euo pipefail
cd "$(dirname "$0")/../.."
export DB_CONNECTION=sqlite
export INTERPRESSO_DB_CONNECTION=sqlite
export DB_DATABASE="$PWD/tests/e2e/.data/e2e.sqlite"
export INTERPRESSO_E2E=1
export CACHE_STORE=file
export SESSION_DRIVER=file
export QUEUE_CONNECTION=database
exec ./vendor/bin/testbench queue:work database --queue=languageProcessor --stop-when-empty --tries=1 --sleep=0 "$@"
