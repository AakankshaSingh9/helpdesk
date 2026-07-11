import { defineConfig, devices } from '@playwright/test'

/**
 * Playwright configuration for the AI Helpdesk monorepo.
 *
 * Tests drive the SPA in a real browser (`baseURL`), which talks to the Laravel
 * API, which talks to an ISOLATED test database (`helpdesk_test`) — never the
 * dev data. The whole stack is started for you by the `webServer` block below,
 * which runs scripts/e2e/up.sh (create test DB → boot api-test + web-test).
 *
 * No tests are checked in yet; add specs under ./tests/e2e.
 *
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
  testDir: './tests/e2e',

  // Fail the build on CI if someone accidentally left test.only in the source.
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,

  reporter: process.env.CI ? [['html', { open: 'never' }], ['list']] : 'list',

  use: {
    // The test SPA (web-test) served by the E2E stack.
    baseURL: 'http://localhost:5174',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    // Enable more browsers once there are tests worth running across them.
    // { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
    // { name: 'webkit', use: { ...devices['Desktop Safari'] } },
  ],

  // Boot the isolated test stack and wait until the API is migrated, seeded,
  // and serving. api-test runs `migrate:fresh --seed` before `artisan serve`,
  // so a 200 from :8001/up means the database is ready — web-test (:5174) comes
  // up faster and is ready by then. Reuses an already-running stack locally.
  webServer: {
    command: 'bash scripts/e2e/up.sh',
    url: 'http://localhost:8001/up',
    reuseExistingServer: !process.env.CI,
    timeout: 240_000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
})
