#!/usr/bin/env bash
# Tear down the Playwright E2E stack (api-test + web-test).
#
# Leaves the dev stack (api, web) and the shared db/redis containers untouched.
# The helpdesk_test database is intentionally left in place — it's recreated
# (migrate:fresh) on the next run, and keeping it avoids a needless re-CREATE.
set -euo pipefail

cd "$(dirname "$0")/../.."

echo "▶ Stopping the test stack…"
docker compose -f docker-compose.yml -f docker-compose.test.yml rm -sf api-test web-test

echo "✓ Test stack stopped. To also drop the test database:"
echo "    docker compose exec db psql -U helpdesk -d helpdesk -c 'DROP DATABASE IF EXISTS helpdesk_test'"
