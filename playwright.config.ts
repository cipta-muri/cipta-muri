import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/playwright',
  fullyParallel: false,
  workers: 1,
  timeout: 90_000,
  expect: {
    timeout: 15_000,
  },
  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8000',
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: 'php artisan serve --env=dusk.local --host=127.0.0.1 --port=8000',
    url: 'http://localhost:8000',
    reuseExistingServer: true,
    timeout: 120_000,
  },
});
