import { expect, test } from '@playwright/test'

const API = 'http://127.0.0.1:8000/api/v1'
const RENDERER = 'http://localhost:3000'

/**
 * El vertical de SEO de FASE 4B, por el stack real:
 * login Pro → site → publicar artículo → cambiar su slug → la URL vieja responde 301
 * a la nueva (slug-history, ADR-018) y el sitemap lista lo publicado.
 *
 * El cambio de slug se hace por API (el editor no expone el slug); todo lo demás es UI
 * real + el renderer Nuxt emitiendo el 3xx y sirviendo el sitemap.
 */
test('SEO: cambiar el slug publicado → 301 desde la URL vieja; sitemap lista lo publicado', async ({ page, request }) => {
  // --- Login Pro sembrado + crear/abrir site (siembra el preset de artículos) ---
  await page.goto('/')
  await page.getByTestId('email').fill('cms-e2e@sass.local')
  await page.getByTestId('password').fill('Password!123')
  await page.getByTestId('submit').click()

  await page.locator('[data-testid^="ws-"]').first().click()
  await page.getByTestId('site-name').fill('SEO E2E')
  await page.getByTestId('site-slug').fill('seo-e2e')
  await page.getByTestId('site-create').click()
  await page.getByTestId('site-seo-e2e').click()

  // --- Crear y publicar un artículo (slug autogenerado: articulo-seo) ---
  await page.getByTestId('nav-collections').click()
  await page.getByTestId('collection-articles').click()
  await page.getByTestId('entry-new').click()
  await page.getByTestId('entry-title').fill('Artículo SEO')
  await page.getByTestId('field-body').fill('Cuerpo del artículo SEO.')
  await page.getByTestId('entry-publish').click()
  await expect(page.getByTestId('entry-status')).toHaveText('published')

  const url = page.url()
  const ws = url.match(/\/w\/([^/]+)/)![1]
  const site = url.match(/\/s\/([^/]+)/)![1]
  const collection = url.match(/\/c\/([^/]+)/)![1]
  const oldSlug = 'articulo-seo'
  const newSlug = 'articulo-seo-nuevo'

  // Token del admin ANTES de ir al renderer (localStorage es por-origen: el token vive
  // en el origen del admin, no en el del renderer).
  const token = await page.evaluate(() => localStorage.getItem('sb_token'))
  const headers = { Authorization: `Bearer ${token}`, Accept: 'application/json' }

  // La URL vieja renderiza el artículo (SSR).
  await page.goto(`${RENDERER}/_site/${site}/blog/${oldSlug}`)
  await expect(page.locator('.st-hero__heading')).toHaveText('Artículo SEO')

  // --- Cambiar el slug por API (el editor no expone el slug) ---

  const list = await (await request.get(`${API}/workspaces/${ws}/sites/${site}/collections/${collection}/entries`, { headers })).json()
  const entryId = (list.data as Array<{ id: string, slug: string }>).find((e) => e.slug === oldSlug)!.id

  const patch = await request.patch(`${API}/workspaces/${ws}/sites/${site}/collections/${collection}/entries/${entryId}`, {
    headers,
    data: { slug: newSlug },
  })
  expect(patch.ok()).toBeTruthy()

  // --- El redirect automático aparece en el admin (UI de gestión, source=automático) ---
  // exact: '/blog/articulo-seo' es substring de '…-nuevo'; sin exact casaría 2 elementos.
  await page.goto(`/w/${ws}/s/${site}/redirects`)
  await expect(page.getByText(`/blog/${oldSlug}`, { exact: true })).toBeVisible()
  await expect(page.getByText(`/blog/${newSlug}`, { exact: true })).toBeVisible()
  await expect(page.getByText('automático')).toBeVisible()

  // --- La URL VIEJA responde 301 a la nueva (Nuxt emite el 3xx real) ---
  await expect.poll(
    async () => (await request.get(`${RENDERER}/_site/${site}/blog/${oldSlug}`, { maxRedirects: 0 })).status(),
    { timeout: 20_000 },
  ).toBe(301)

  const redirected = await request.get(`${RENDERER}/_site/${site}/blog/${oldSlug}`, { maxRedirects: 0 })
  expect(redirected.headers().location).toContain(`/blog/${newSlug}`)

  // La URL nueva renderiza el mismo artículo.
  await page.goto(`${RENDERER}/_site/${site}/blog/${newSlug}`)
  await expect(page.locator('.st-hero__heading')).toHaveText('Artículo SEO')

  // --- El sitemap lista el artículo publicado (por su slug nuevo) ---
  const sitemap = await request.get(`${RENDERER}/_site/${site}/sitemap.xml`)
  expect(sitemap.status()).toBe(200)
  expect(sitemap.headers()['content-type']).toContain('xml')
  expect(await sitemap.text()).toContain(`/blog/${newSlug}`)
})
