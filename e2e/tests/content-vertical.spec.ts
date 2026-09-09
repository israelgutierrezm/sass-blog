import { expect, test } from '@playwright/test'

/**
 * El vertical de FASE 3 (CMS), por la UI real:
 * login (usuario Pro sembrado) → workspace → crear site (siembra la colección
 * `articles`) → Colecciones → Artículos → nueva entrada → rellenar campos dinámicos →
 * publicar → ver el artículo público renderizado por Nuxt con bindings resueltos.
 *
 * El CMS exige la capability cms.collections (plan Pro): por eso se entra con el
 * usuario Pro que siembra E2eContentSeeder, no con un registro nuevo (Free).
 */
test('CMS: login Pro → site → colección → entrada → publicar → ver artículo público', async ({ page }) => {
  // --- Login como el usuario Pro sembrado (modo login es el default) ---
  await page.goto('/')
  await page.getByTestId('email').fill('cms-e2e@sass.local')
  await page.getByTestId('password').fill('Password!123')
  await page.getByTestId('submit').click()

  // --- Workspace (personal) ---
  await expect(page.getByRole('heading', { name: 'Tus espacios de trabajo' })).toBeVisible()
  await page.locator('[data-testid^="ws-"]').first().click()

  // --- Crear y abrir el site (dispara el sembrado del preset de artículos) ---
  await page.getByTestId('site-name').fill('Contenido E2E')
  await page.getByTestId('site-slug').fill('contenido-e2e')
  await page.getByTestId('site-create').click()
  await page.getByTestId('site-contenido-e2e').click()

  // --- Ir a Colecciones → Artículos → Nueva entrada ---
  await page.getByTestId('nav-collections').click()
  await page.getByTestId('collection-articles').click()
  await page.getByTestId('entry-new').click()

  // --- Rellenar los campos dinámicos (título + cuerpo requerido) y publicar ---
  await page.getByTestId('entry-title').fill('Artículo E2E')
  await page.getByTestId('field-body').fill('Cuerpo del artículo E2E.')
  await page.getByTestId('entry-publish').click()
  await expect(page.getByTestId('entry-status')).toHaveText('published')

  // --- Ver el artículo público (Nuxt SSR) con bindings resueltos ---
  const siteUlid = page.url().match(/\/s\/([^/]+)/)?.[1]
  expect(siteUlid).toBeTruthy()

  await page.goto(`http://localhost:3000/_site/${siteUlid}/blog/articulo-e2e`)
  await expect(page.locator('.st-hero__heading')).toHaveText('Artículo E2E')
  await expect(page.locator('.st-text__p')).toContainText('Cuerpo del artículo E2E')
})
