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
  // Construye el CLI de render estático (export) una vez antes de la suite.
  globalSetup: './global-setup.ts',

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
      // migrate:fresh + siembra tarda ~3 min con el MySQL de WampServer (DDL lento en
      // Windows); margen amplio para no dar falsos timeouts al arrancar el backend.
      timeout: 300_000,
      reuseExistingServer: false,
      // QUEUE sync: el build estático, la verificación de dominios y el envío de newsletter
      // corren inline. DOMAINS_AUTO_VERIFY: sin DNS real, un dominio conectado se verifica.
      // MAIL_MAILER=log: no envía correo real (ni falla) al confirmar/enviar newsletter.
      env: { DB_DATABASE: 'sass_blog_e2e', QUEUE_CONNECTION: 'sync', DOMAINS_AUTO_VERIFY: 'true', MAIL_MAILER: 'log' },
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
