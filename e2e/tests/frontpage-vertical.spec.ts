import { expect, test } from '@playwright/test'

/**
 * El vertical de portadas de FASE 10, por la UI real (ADR-024):
 * login Pro → publicar 2 artículos → crear una página con una sección `featured` → CURAR a mano
 * (añadir Alfa y Beta con el entry-picker) → publicar → el sitio muestra los artículos en el
 * orden curado (Alfa destacado, Beta secundario).
 */
test('Portada: curar artículos a mano → aparecen en orden en el sitio', async ({ page }) => {
  await page.goto('/')
  await page.getByTestId('email').fill('cms-e2e@sass.local')
  await page.getByTestId('password').fill('Password!123')
  await page.getByTestId('submit').click()
  await expect(page.getByRole('heading', { name: 'Tus espacios de trabajo' })).toBeVisible()
  await page.locator('[data-testid^="ws-"]').first().click()

  await page.getByTestId('site-name').fill('Portada E2E')
  await page.getByTestId('site-slug').fill('portada-e2e')
  await page.getByTestId('site-create').click()
  await page.getByTestId('site-portada-e2e').click()

  // --- 2 artículos publicados ---
  await page.getByTestId('nav-collections').click()
  await page.getByTestId('collection-articles').click()
  await page.getByTestId('entry-new').click()
  const [, ws, site, col] = page.url().match(/\/w\/([^/]+)\/s\/([^/]+)\/c\/([^/]+)/)!
  const entriesUrl = `/w/${ws}/s/${site}/c/${col}/entries`

  await page.getByTestId('entry-title').fill('Alfa')
  await page.getByTestId('field-body').fill('Cuerpo Alfa')
  await page.getByTestId('entry-publish').click()
  await expect(page.getByTestId('entry-status')).toHaveText('published')

  await page.goto(entriesUrl)
  await page.getByTestId('entry-new').click()
  await page.getByTestId('entry-title').fill('Beta')
  await page.getByTestId('field-body').fill('Cuerpo Beta')
  await page.getByTestId('entry-publish').click()
  await expect(page.getByTestId('entry-status')).toHaveText('published')

  // --- Página con una sección featured curada a mano ---
  await page.goto(`/w/${ws}/s/${site}`)
  await page.getByTestId('page-title').fill('Inicio')
  await page.getByTestId('page-create').click()
  await page.getByTestId('page-/').click()
  await page.getByTestId('add-section').click()
  await page.getByTestId('add-featured-lead').click()

  // Curar: añadir Alfa y luego Beta (ese orden).
  await page.getByTestId('entry-picker-add-select').selectOption({ label: 'Alfa' })
  await page.getByTestId('entry-picker-add').click()
  await page.getByTestId('entry-picker-add-select').selectOption({ label: 'Beta' })
  await page.getByTestId('entry-picker-add').click()

  await page.getByTestId('save').click()
  await page.getByTestId('publish').click()
  await expect(page.getByTestId('page-status')).toHaveText('published')

  // --- El sitio muestra la portada en el orden curado ---
  await page.goto(`http://127.0.0.1:3000/_site/${site}/`)
  await expect(page.locator('.st-featured__lead-title')).toHaveText('Alfa') // destacado = primero
  await expect(page.locator('.st-featured')).toContainText('Beta')          // secundario
})
