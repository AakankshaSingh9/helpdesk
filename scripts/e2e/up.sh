#!/usr/bin/env bash
# Bring up the Playwright E2E stack.
#
# 1. Ensure the shared Postgres container is running.
# 2. Create the isolated `helpdesk_test` database if it doesn't exist yet
#    (idempotent — safe to run every time).
# 3. Start the test API (:8001, migrates + seeds on boot) and test SPA (:5174)
#    in the foreground so Playwright can supervise them.
#
# Playwright runs this via `webServer` in playwright.config.ts. You can also run
# it by hand to poke at the test stack.
set -euo pipefail

# Repo root, regardless of where this is invoked from.
cd "$(dirname "$0")/../.."

TEST_DB="helpdesk_test"

echo "▶ Ensuring the db container is up…"
docker compose up -d db

echo "▶ Waiting for Postgres to accept connections…"
until docker compose exec -T db pg_isready -U helpdesk >/dev/null 2>&1; do
  sleep 1
done

echo "▶ Ensuring the '${TEST_DB}' database exists…"
if docker compose exec -T db \
    psql -U helpdesk -d helpdesk -tAc \
    "SELECT 1 FROM pg_database WHERE datname='${TEST_DB}'" | grep -q 1; then
  echo "  '${TEST_DB}' already exists."
else
  docker compose exec -T db \
    psql -U helpdesk -d helpdesk -c "CREATE DATABASE ${TEST_DB}"
  echo "  created '${TEST_DB}'."
fi

echo "▶ Starting the test stack (api-test :8001, web-test :5174)…"
exec docker compose -f docker-compose.yml -f docker-compose.test.yml up api-test web-test
