import { expect, test } from '@playwright/test'

/**
 * El vertical de FASE 4A (Media), por la UI real:
 * login Pro → site → Colecciones → Medios → subir una imagen → nueva entrada →
 * elegir esa imagen como featured_image desde el picker → publicar → reabrir la
 * entrada y comprobar que la URL persistió (round-trip completo con el backend).
 *
 * (El template del artículo no pinta featured_image, así que se verifica la
 * persistencia, no el render; mostrarla es un concern del CollectionGrid/plantilla.)
 */

// PNG 1x1 válido (base64).
const PNG_1x1 =
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='

test('Media: subir imagen → elegirla como featured_image → publicar → persiste', async ({ page }) => {
  // --- Login como el usuario Pro sembrado ---
  await page.goto('/')
  await page.getByTestId('email').fill('cms-e2e@sass.local')
  await page.getByTestId('password').fill('Password!123')
  await page.getByTestId('submit').click()

  // --- Workspace → crear y abrir el site (siembra la colección articles) ---
  await expect(page.getByRole('heading', { name: 'Tus espacios de trabajo' })).toBeVisible()
  await page.locator('[data-testid^="ws-"]').first().click()
  await page.getByTestId('site-name').fill('Media E2E')
  await page.getByTestId('site-slug').fill('media-e2e')
  await page.getByTestId('site-create').click()
  await page.getByTestId('site-media-e2e').click()

  // --- Colecciones → Medios → subir una imagen ---
  await page.getByTestId('nav-collections').click()
  await page.getByTestId('nav-media').click()
  await page.getByTestId('media-upload').setInputFiles({
    name: 'demo.png',
    mimeType: 'image/png',
    buffer: Buffer.from(PNG_1x1, 'base64'),
  })
  await expect(page.locator('[data-testid^="asset-"]').first()).toBeVisible()

  // --- Volver a Colecciones → Artículos → nueva entrada ---
  await page.getByRole('link', { name: 'Colecciones' }).first().click()
  await page.getByTestId('collection-articles').click()
  await page.getByTestId('entry-new').click()

  await page.getByTestId('entry-title').fill('Con imagen')
  await page.getByTestId('field-body').fill('Cuerpo con imagen.')

  // --- Elegir la imagen de la librería para featured_image ---
  await page.getByTestId('pick-featured_image').click()
  await expect(page.getByTestId('media-picker')).toBeVisible()
  await page.locator('[data-testid="media-picker"] button[data-testid^="pick-"]').first().click()
  await expect(page.getByTestId('field-featured_image')).toHaveValue(/storage\/media/)

  // --- Publicar ---
  await page.getByTestId('entry-publish').click()
  await expect(page.getByTestId('entry-status')).toHaveText('published')

  // --- Persistencia: reabrir la entrada y comprobar que la URL sigue ahí ---
  await page.getByRole('link', { name: 'Entradas' }).first().click()
  await page.getByTestId('entry-con-imagen').click()
  await expect(page.getByTestId('field-featured_image')).toHaveValue(/storage\/media/)
})
