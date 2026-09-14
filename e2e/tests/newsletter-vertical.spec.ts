import { expect, test } from '@playwright/test'

/**
 * El vertical de newsletter de FASE 8, por el stack real (ADR-022):
 * login Pro → publicar una página con una sección `newsletter` → VISITAR el sitio por el
 * renderer y suscribirse desde el formulario (POST público cross-origin al backend, doble
 * opt-in: queda pending) → el suscriptor aparece en el admin. Cierra captura → endpoint → admin.
 */
test('Publicar página con newsletter → suscribirse en el sitio → aparece en el admin', async ({ page }) => {
  // --- Login Pro sembrado ---
  await page.goto('/')
  await page.getByTestId('email').fill('cms-e2e@sass.local')
  await page.getByTestId('password').fill('Password!123')
  await page.getByTestId('submit').click()
  await expect(page.getByRole('heading', { name: 'Tus espacios de trabajo' })).toBeVisible()
  await page.locator('[data-testid^="ws-"]').first().click()

  // --- Crear site + publicar una página con sección newsletter ---
  await page.getByTestId('site-name').fill('Newsletter E2E')
  await page.getByTestId('site-slug').fill('newsletter-e2e')
  await page.getByTestId('site-create').click()
  await page.getByTestId('site-newsletter-e2e').click()
  await page.getByTestId('page-title').fill('Inicio')
  await page.getByTestId('page-create').click()
  await page.getByTestId('page-/').click()
  await page.getByTestId('add-section').click()
  await page.getByTestId('add-newsletter-inline').click()
  await page.getByTestId('save').click()
  await page.getByTestId('publish').click()
  await expect(page.getByTestId('page-status')).toHaveText('published')

  const url = page.url()
  const ws = url.match(/\/w\/([^/]+)/)![1]
  const site = url.match(/\/s\/([^/]+)/)![1]

  // --- Visitar el sitio publicado y suscribirse desde el formulario ---
  await page.goto(`http://127.0.0.1:3000/_site/${site}/`)
  await page.waitForLoadState('networkidle') // espera la hidratación antes de interactuar
  await expect(page.getByTestId('newsletter-email')).toBeVisible()
  await page.getByTestId('newsletter-email').fill('lector-e2e@example.com')
  await page.getByTestId('newsletter-submit').click()
  await expect(page.getByTestId('newsletter-success')).toBeVisible()

  // --- El suscriptor (pending, doble opt-in) aparece en el admin ---
  await page.goto(`/w/${ws}/s/${site}/newsletter`)
  await expect(page.getByTestId('newsletter-subscribers-total')).not.toHaveText('0')
})
