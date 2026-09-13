import { statSync } from 'node:fs'
import { expect, test } from '@playwright/test'

/**
 * El vertical de export estático de FASE 5, por el stack real:
 * login Pro (capability site.export.static) → site → publicar página → Exportar → el build
 * (job en cola sync → NodeStaticRenderer → CLI Node → site-components → ZIP) termina en
 * "Listo" y el artefacto se descarga. Verifica el motor único (mismos site-components) y
 * el artefacto self-contained.
 */
test('Publicar → exportar sitio estático → el ZIP se genera y se descarga', async ({ page }) => {
  // --- Login Pro sembrado ---
  await page.goto('/')
  await page.getByTestId('email').fill('cms-e2e@sass.local')
  await page.getByTestId('password').fill('Password!123')
  await page.getByTestId('submit').click()

  await expect(page.getByRole('heading', { name: 'Tus espacios de trabajo' })).toBeVisible()
  await page.locator('[data-testid^="ws-"]').first().click()

  // --- Crear/abrir site + publicar una página con contenido ---
  await page.getByTestId('site-name').fill('Export E2E')
  await page.getByTestId('site-slug').fill('export-e2e')
  await page.getByTestId('site-create').click()
  await page.getByTestId('site-export-e2e').click()

  await page.getByTestId('page-title').fill('Inicio')
  await page.getByTestId('page-create').click()
  await page.getByTestId('page-/').click()
  await page.getByTestId('add-section').click()
  await page.getByTestId('add-hero-split').click()
  await page.locator('[data-testid="field-heading"] input').fill('Sitio Exportado E2E')
  await page.getByTestId('save').click()
  await page.getByTestId('publish').click()
  await expect(page.getByTestId('page-status')).toHaveText('published')

  const url = page.url()
  const ws = url.match(/\/w\/([^/]+)/)![1]
  const site = url.match(/\/s\/([^/]+)/)![1]

  // --- Exportar (cola sync → build inline) ---
  await page.goto(`/w/${ws}/s/${site}/deployments`)
  await page.getByTestId('deploy-export').click()

  // El deployment queda "Listo" (build real completado a través del CLI Node).
  await expect(page.locator('[data-testid^="deploy-status-"]').first()).toHaveText('Listo')

  // --- Descargar el ZIP ---
  const downloadPromise = page.waitForEvent('download')
  await page.locator('[data-testid^="deploy-download-"]').first().click()
  const download = await downloadPromise

  expect(download.suggestedFilename()).toBe('sitio-estatico.zip')
  const path = await download.path()
  expect(path).toBeTruthy()
  expect(statSync(path!).size).toBeGreaterThan(0)
})
