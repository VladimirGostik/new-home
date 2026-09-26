import { defineConfig, devices } from '@playwright/test';

const BASE_URL = process.env.E2E_BASE_URL ?? 'http://localhost:8003';

export default defineConfig({
    testDir: 'tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: BASE_URL,
        locale: 'sk-SK',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    webServer: {
        command: `echo "dev server expected on ${BASE_URL} (docker compose)"`,
        url: BASE_URL,
        reuseExistingServer: true,
        timeout: 120_000,
    },
    projects: [
        {
            name: 'mobile-webkit',
            use: { ...devices['iPhone 13'] },
        },
    ],
});
