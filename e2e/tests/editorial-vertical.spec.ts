import { expect, test } from '@playwright/test'

/**
 * El vertical editorial de FASE 9, por la UI real (ADR-023):
 * login Pro → site (siembra `articles`) → nueva entrada → ENVIAR A REVISIÓN → APROBAR y
 * publicar → visible en el sitio. Y una segunda entrada: enviar a revisión → APROBAR con fecha
 * futura → queda PROGRAMADA y NO visible. (La transición programada→publicada del job se cubre
 * en backend.)
 */
test('Editorial: enviar a revisión → aprobar/publicar → visible; y programar → scheduled', async ({ page }) => {
  await page.goto('/')
  await page.getByTestId('email').fill('cms-e2e@sass.local')
  await page.getByTestId('password').fill('Password!123')
  await page.getByTestId('submit').click()
  await expect(page.getByRole('heading', { name: 'Tus espacios de trabajo' })).toBeVisible()
  await page.locator('[data-testid^="ws-"]').first().click()

  await page.getByTestId('site-name').fill('Editorial E2E')
  await page.getByTestId('site-slug').fill('editorial-e2e')
  await page.getByTestId('site-create').click()
  await page.getByTestId('site-editorial-e2e').click()

  await page.getByTestId('nav-collections').click()
  await page.getByTestId('collection-articles').click()

  // --- Entrada A: enviar a revisión → aprobar y publicar → visible ---
  await page.getByTestId('entry-new').click()
  // Captura estable desde la URL del editor (la ruta usa /c/{col}/).
  const [, ws, site, col] = page.url().match(/\/w\/([^/]+)\/s\/([^/]+)\/c\/([^/]+)/)!
  const entriesListUrl = `/w/${ws}/s/${site}/c/${col}/entries`

  await page.getByTestId('entry-title').fill('Reportaje E2E')
  await page.getByTestId('field-body').fill('Cuerpo del reportaje editorial.')
  await page.getByTestId('entry-submit-review').click()
  await expect(page.getByTestId('entry-status')).toHaveText('in_review')

  await page.getByTestId('entry-approve').click()
  await expect(page.getByTestId('entry-status')).toHaveText('published')

  await page.goto(`http://127.0.0.1:3000/_site/${site}/blog/reportaje-e2e`)
  await expect(page.locator('.st-hero__heading')).toHaveText('Reportaje E2E')

  // --- Entrada B: enviar a revisión → aprobar con fecha futura → programada ---
  await page.goto(entriesListUrl)
  await page.getByTestId('entry-new').click()
  await page.getByTestId('entry-title').fill('Programada E2E')
  await page.getByTestId('field-body').fill('Contenido programado.')
  await page.getByTestId('entry-submit-review').click()
  await expect(page.getByTestId('entry-status')).toHaveText('in_review')

  await page.getByTestId('entry-schedule-at').fill('2030-01-01T10:00')
  await page.getByTestId('entry-approve').click()
  await expect(page.getByTestId('entry-status')).toHaveText('scheduled')

  // La programada aún NO es visible en el sitio.
  const scheduled = await page.request.get(`http://127.0.0.1:3000/_site/${site}/blog/programada-e2e`)
  expect(scheduled.status()).toBe(404)
})
