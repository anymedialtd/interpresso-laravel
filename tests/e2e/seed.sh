#!/usr/bin/env bash
# Rebuilds only the disposable e2e database. Called before each behavior test.
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
mkdir -p tests/e2e/.data
rm -f tests/e2e/.data/queue-connection
rm -f tests/e2e/.data/locale tests/e2e/.data/mail.jsonl
rm -rf tests/e2e/.data/cache tests/e2e/.data/sessions tests/e2e/.data/lang
mkdir -p tests/e2e/.data/cache tests/e2e/.data/sessions tests/e2e/.data/lang/en tests/e2e/.data/lang/de
: > "$DB_DATABASE"
./vendor/bin/testbench migrate:fresh --force >/dev/null
# Run inside the same Testbench application as the HTTP server. The migration
# supplies the admin and English; these fixtures add a regular translator and
# distinct translation states without depending on imports or external services.
export XDG_CONFIG_HOME="$PWD/tests/e2e/.data/config"
./vendor/bin/testbench tinker --execute="$(cat <<'PHP'
require getcwd().'/tests/e2e/fixtures.php';
echo "seeded: 2 languages, admin + non-admin, 6 translations\n";
PHP
)"
