import { defineConfig, devices } from '@playwright/test';

/**
 * Browser gate for the public and member surfaces (3.3.0 / 3.4.0).
 *
 * The suite drives a REAL server with REAL built assets — that is the whole
 * point of it. Pest already covers controller behaviour against the framework;
 * what it cannot see is whether the page actually works once Vite has built,
 * Alpine has booted and a keyboard user tries to get through it.
 *
 * The server is started by Playwright itself so a developer runs the same
 * command CI does. `reuseExistingServer` is off in CI so a stale process can
 * never make a run pass against the wrong code.
 */
const baseURL = process.env.APP_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
    testDir: './tests/Browser',
    outputDir: './storage/playwright',

    // Every assertion here is about a real page load, so a flaky-retry would
    // hide exactly the kind of race the gate exists to catch. One retry in CI
    // absorbs runner noise without papering over a genuine failure.
    retries: process.env.CI ? 1 : 0,
    workers: process.env.CI ? 2 : undefined,
    forbidOnly: !! process.env.CI,

    timeout: 30_000,
    expect: { timeout: 10_000 },

    reporter: process.env.CI
        ? [['github'], ['html', { open: 'never', outputFolder: 'storage/playwright-report' }]]
        : [['list']],

    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'off',
        locale: 'bg-BG',
        timezoneId: 'Europe/Sofia',
    },

    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],

    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8000',
        url: baseURL,
        reuseExistingServer: ! process.env.CI,
        timeout: 60_000,
        stdout: 'pipe',
        stderr: 'pipe',
    },
});
