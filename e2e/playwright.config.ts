import { defineConfig, devices } from '@playwright/test'

/**
 * E2E del vertical de FASE 2: admin (5173) + renderer (3000) contra un backend
 * (8000) sobre una BD dedicada `sass_blog_e2e` que se migra y siembra en cada
 * corrida. Sin mocks: todo real.
 */
export default defineConfig({
  testDir: './tests',
  timeout: 120_000,
  expect: { timeout: 20_000 },
  fullyParallel: false,
  workers: 1,
  reporter: 'list',

  use: {
    baseURL: 'http://127.0.0.1:5173',
    trace: 'retain-on-failure',
  },

  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],

  webServer: [
    {
      // Backend con BD de E2E: migra+siembra (+ usuario Pro para el CMS) y sirve.
      // DB_DATABASE en env pisa .env.
      command:
        'php artisan migrate:fresh --seed --force && php artisan db:seed --class="Database\\Seeders\\E2eContentSeeder" --force && php artisan serve --host=127.0.0.1 --port=8000',
      cwd: '../backend',
      url: 'http://127.0.0.1:8000/up',
      timeout: 120_000,
      reuseExistingServer: false,
      env: { DB_DATABASE: 'sass_blog_e2e' },
    },
    {
      command: 'pnpm --filter admin dev',
      cwd: '..',
      url: 'http://127.0.0.1:5173',
      timeout: 180_000,
      reuseExistingServer: true,
    },
    {
      command: 'pnpm --filter renderer dev',
      cwd: '..',
      url: 'http://127.0.0.1:3000/health',
      timeout: 180_000,
      reuseExistingServer: true,
    },
  ],
})
