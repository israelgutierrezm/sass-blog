import { expect, test } from '@playwright/test'

/**
 * El vertical completo de FASE 2, por la UI real:
 * registro → workspace → site → page → agregar Hero → editar → guardar →
 * publicar → ver el sitio público renderizado por Nuxt.
 */
test('User → Workspace → Site → Page → Hero → Edit → Save → Publish → View Public', async ({ page }) => {
  const email = `e2e_${Date.now()}@example.com`

  // --- Registro ---
  await page.goto('/')
  await page.getByRole('button', { name: 'Regístrate' }).click()
  await page.getByTestId('name').fill('E2E User')
  await page.getByTestId('email').fill(email)
  await page.getByTestId('password').fill('Password!123')
  await page.getByTestId('submit').click()

  // --- Workspace (personal) ---
  await expect(page.getByRole('heading', { name: 'Tus espacios de trabajo' })).toBeVisible()
  await page.locator('[data-testid^="ws-"]').first().click()

  // --- Crear y abrir el sitio ---
  await page.getByTestId('site-name').fill('Sitio E2E')
  await page.getByTestId('site-slug').fill('e2e-site')
  await page.getByTestId('site-create').click()
  await page.getByTestId('site-e2e-site').click()

  // --- Crear y abrir la página (ruta '/') ---
  await page.getByTestId('page-title').fill('Inicio')
  await page.getByTestId('page-create').click()
  await page.getByTestId('page-/').click()

  // --- Builder: agregar Hero dividido ---
  await page.getByTestId('add-section').click()
  await page.getByTestId('add-hero-split').click()

  // --- Editar el título; el lienzo se actualiza en vivo ---
  await page.locator('[data-testid="field-heading"] input').fill('Hola Mundo E2E')
  await expect(page.getByTestId('canvas')).toContainText('Hola Mundo E2E')

  // --- Guardar y publicar ---
  await page.getByTestId('save').click()
  await page.getByTestId('publish').click()
  await expect(page.getByTestId('page-status')).toHaveText('published')

  // --- Ver el sitio público (Nuxt SSR) ---
  const siteUlid = page.url().match(/\/s\/([^/]+)/)?.[1]
  expect(siteUlid).toBeTruthy()

  await page.goto(`http://localhost:3000/_site/${siteUlid}/`)
  await expect(page.locator('.st-hero__heading')).toHaveText('Hola Mundo E2E')
})
