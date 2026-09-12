import { expect, test } from '@playwright/test'

const RENDERER = 'http://localhost:3000'

/**
 * El vertical de Menús de FASE 4C, por la UI real:
 * registro (Free: los menús son core) → site → crear menú con ítems (editor) → añadir
 * la sección `navigation` a la página en el Builder → publicar → el sitio (Nuxt SSR)
 * pinta la navegación con los enlaces resueltos en vivo (ADR-017).
 */
test('Menús: crear menú con ítems → sección navigation → el sitio pinta la navegación', async ({ page }) => {
  const email = `nav_${Date.now()}@example.com`

  // --- Registro → workspace → site ---
  await page.goto('/')
  await page.getByRole('button', { name: 'Regístrate' }).click()
  await page.getByTestId('name').fill('Nav E2E')
  await page.getByTestId('email').fill(email)
  await page.getByTestId('password').fill('Password!123')
  await page.getByTestId('submit').click()

  await expect(page.getByRole('heading', { name: 'Tus espacios de trabajo' })).toBeVisible()
  await page.locator('[data-testid^="ws-"]').first().click()

  await page.getByTestId('site-name').fill('Nav E2E')
  await page.getByTestId('site-slug').fill('nav-e2e')
  await page.getByTestId('site-create').click()
  await page.getByTestId('site-nav-e2e').click()

  // Esperar a que cargue la vista de Páginas (SPA) antes de leer la URL.
  await expect(page.getByTestId('page-title')).toBeVisible()
  const url = page.url()
  const ws = url.match(/\/w\/([^/]+)/)![1]
  const site = url.match(/\/s\/([^/]+)/)![1]

  // --- Crear la página '/' ---
  await page.getByTestId('page-title').fill('Inicio')
  await page.getByTestId('page-create').click()

  // --- Crear el menú `primary` y añadir dos ítems (editor) ---
  await page.getByTestId('nav-menus').click()
  await page.getByTestId('menu-handle').fill('primary')
  await page.getByTestId('menu-name').fill('Principal')
  await page.getByTestId('menu-create').click()
  await page.getByTestId('menu-primary').getByRole('link').click()

  // Ítem home "Inicio" (sin destino).
  await page.getByTestId('item-label').fill('Inicio')
  await page.getByTestId('item-add').click()
  await expect(page.getByTestId('item-Inicio')).toBeVisible()

  // Ítem url "Contacto" → /contacto.
  await page.getByTestId('item-label').fill('Contacto')
  await page.getByTestId('item-type').selectOption('url')
  await page.getByTestId('item-url').fill('/contacto')
  await page.getByTestId('item-add').click()
  await expect(page.getByTestId('item-Contacto')).toBeVisible()

  // --- Builder: añadir la sección navigation (variante horizontal) y publicar ---
  await page.goto(`/w/${ws}/s/${site}`)
  await page.getByTestId('page-/').click()
  await page.getByTestId('add-section').click()
  await page.getByTestId('add-navigation-horizontal').click() // default props.menu = 'primary'
  await page.getByTestId('save').click()
  await page.getByTestId('publish').click()
  await expect(page.getByTestId('page-status')).toHaveText('published')

  // --- El sitio público pinta la navegación con los enlaces resueltos ---
  await page.goto(`${RENDERER}/_site/${site}/`)
  const nav = page.locator('nav.st-nav')
  await expect(nav).toBeVisible()
  await expect(nav.getByRole('link', { name: 'Inicio' })).toHaveAttribute('href', `/_site/${site}/`)
  await expect(nav.getByRole('link', { name: 'Contacto' })).toHaveAttribute('href', `/_site/${site}/contacto`)
})
