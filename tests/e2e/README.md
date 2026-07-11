# End-to-end tests (Playwright)

Browser-driven tests for the helpdesk SPA + API, running against an **isolated
test database** (`helpdesk_test`) so they never touch dev data.

No specs live here yet — this is the harness only. Add `*.spec.ts` files in this
directory.

## Running

```bash
npm run test:e2e          # headless, boots the test stack automatically
npm run test:e2e:ui       # Playwright UI mode (watch/inspect)
npm run test:e2e:report   # open the last HTML report
npm run e2e:down          # stop the test stack when you're done
```

`npm run test:e2e` starts everything for you (see `playwright.config.ts` →
`webServer`): it creates the test database if needed, then boots a second copy
of the API on **:8001** and the SPA on **:5174**, separate from the dev stack on
:8000 / :5173.

## How the isolation works

| | Dev stack | Test stack |
|---|---|---|
| API | `localhost:8000` | `localhost:8001` (`api-test`) |
| SPA | `localhost:5173` | `localhost:5174` (`web-test`) |
| Database | `helpdesk` | `helpdesk_test` |

The test stack is defined in `docker-compose.test.yml` and shares the `db` /
`redis` containers with dev — only the database name differs. On every boot,
`api-test` runs `php artisan migrate:fresh --seed`, so each run starts from a
clean, seeded schema.

## Seed data

The test database is seeded by `DatabaseSeeder` (currently the admin user from
`AdminUserSeeder`). To add fixtures specific to E2E, extend the seeders or create
data via the API inside a test's setup.
