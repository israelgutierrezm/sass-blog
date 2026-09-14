import { execSync } from 'node:child_process'
import { resolve } from 'node:path'
import { expect, test } from '@playwright/test'

/**
 * El vertical de analítica de FASE 7, por el stack real (ADR-021):
 * login Pro → site → publicar '/' → VISITAR el sitio por el renderer (dispara la captura
 * server-side: POST /public/analytics/collect) → correr el rollup del día → el dashboard del
 * admin muestra la visita. Cierra el bucle captura → rollup → dashboard.
 */
const BACKEND = resolve(import.meta.dirname, '../../backend')
// UA de navegador real: si fuese de bot, el evento quedaría fuera del rollup.
const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36'

test('Publicar → visitar → rollup → el dashboard muestra la visita', async ({ page, request }) => {
  const heading = 'Sitio con Analítica'

  // --- Login Pro sembrado ---
  await page.goto('/')
  await page.getByTestId('email').fill('cms-e2e@sass.local')
  await page.getByTestId('password').fill('Password!123')
  await page.getByTestId('submit').click()
  await expect(page.getByRole('heading', { name: 'Tus espacios de trabajo' })).toBeVisible()
  await page.locator('[data-testid^="ws-"]').first().click()

  // --- Crear site + publicar la home ---
  await page.getByTestId('site-name').fill('Analítica E2E')
  await page.getByTestId('site-slug').fill('analitica-e2e')
  await page.getByTestId('site-create').click()
  await page.getByTestId('site-analitica-e2e').click()
  await page.getByTestId('page-title').fill('Inicio')
  await page.getByTestId('page-create').click()
  await page.getByTestId('page-/').click()
  await page.getByTestId('add-section').click()
  await page.getByTestId('add-hero-split').click()
  await page.locator('[data-testid="field-heading"] input').fill(heading)
  await page.getByTestId('save').click()
  await page.getByTestId('publish').click()
  await expect(page.getByTestId('page-status')).toHaveText('published')

  const url = page.url()
  const ws = url.match(/\/w\/([^/]+)/)![1]
  const site = url.match(/\/s\/([^/]+)/)![1]

  // --- Visitar el sitio por el renderer (dispara la captura server-side) ---
  const rendererUrl = `http://127.0.0.1:3000/_site/${site}/`
  for (let i = 0; i < 2; i++) {
    const res = await request.get(rendererUrl, { headers: { 'User-Agent': BROWSER_UA } })
    expect(res.ok()).toBeTruthy()
    expect(await res.text()).toContain(heading)
  }

  // La captura es fire-and-forget: un instante para que persista antes del rollup.
  await new Promise((r) => setTimeout(r, 2000))

  // --- Rollup del día (el comando agrega ayer por defecto; pedimos HOY en UTC) ---
  const today = new Date().toISOString().slice(0, 10)
  execSync(`php artisan analytics:rollup ${today}`, {
    cwd: BACKEND,
    env: { ...process.env, DB_DATABASE: 'sass_blog_e2e', QUEUE_CONNECTION: 'sync' },
    stdio: 'pipe',
  })

  // --- El dashboard muestra la visita ---
  await page.goto(`/w/${ws}/s/${site}/analytics`)
  await expect(page.getByTestId('analytics-total-views')).not.toHaveText('0')
  await expect(page.getByTestId('analytics-top-pages')).toBeVisible()
})
