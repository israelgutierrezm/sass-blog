import { expect, test } from '@playwright/test'

/**
 * El vertical de dominios propios de FASE 6, por el stack real (ADR-020 + ADR-025):
 * login Pro (capability site.custom_domain) → site → publicar página '/' → conectar un
 * dominio (queda "Pendiente" mostrando el TXT de propiedad y el CNAME) → "Verificar" → el job
 * (cola sync + DOMAINS_AUTO_VERIFY, sin DNS real) lo deja "Activo" → el renderer, ante una
 * petición con ese Host, resuelve el sitio por Host (/public/domains/resolve) y sirve la página
 * a la raíz. Cierra el enrutado por dominio.
 */
test('Conectar dominio → verificado → el renderer sirve el sitio por Host', async ({ page, request }) => {
  const heading = 'Sitio por Dominio Propio'
  // Hostname válido (HOSTNAME_REGEX) y que NO es un appHost del renderer → dominio de tenant.
  const hostname = `e2e-${Date.now()}.example.test`

  // --- Login Pro sembrado ---
  await page.goto('/')
  await page.getByTestId('email').fill('cms-e2e@sass.local')
  await page.getByTestId('password').fill('Password!123')
  await page.getByTestId('submit').click()

  await expect(page.getByRole('heading', { name: 'Tus espacios de trabajo' })).toBeVisible()
  await page.locator('[data-testid^="ws-"]').first().click()

  // --- Crear site + publicar la home con un hero identificable ---
  await page.getByTestId('site-name').fill('Dominio E2E')
  await page.getByTestId('site-slug').fill('dominio-e2e')
  await page.getByTestId('site-create').click()
  await page.getByTestId('site-dominio-e2e').click()

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

  // --- Conectar el dominio: queda pendiente con los registros DNS a publicar ---
  await page.goto(`/w/${ws}/s/${site}/domains`)
  await page.getByTestId('domain-hostname').fill(hostname)
  await page.getByTestId('domain-connect').click()

  await expect(page.locator('[data-testid^="domain-status-"]').first()).toHaveText('Pendiente')
  await expect(page.locator('[data-testid^="domain-txt-name-"]').first()).toHaveText(`_sassblog-verify.${hostname}`)
  await expect(page.locator('[data-testid^="domain-txt-value-"]').first()).toContainText('sassblog-verify=')

  // --- Verificar (inline: cola sync + AutoVerifyDnsResolver, sin DNS real) → "Activo" ---
  await page.locator('[data-testid^="domain-recheck-"]').first().click()
  await expect(page.locator('[data-testid^="domain-status-"]').first()).toHaveText('Activo')

  // --- El renderer resuelve el sitio por Host y sirve la home a la raíz ---
  // Petición al renderer (3000) con el Host del dominio propio → SSR pide
  // /public/domains/resolve?host=… y renderiza la página '/'.
  const res = await request.get('http://127.0.0.1:3000/', { headers: { host: hostname } })
  expect(res.ok()).toBeTruthy()
  const html = await res.text()
  expect(html).toContain(heading)

  // --- sitemap.xml y robots.txt en la RAÍZ del dominio propio, con ese dominio como base ---
  const sitemap = await request.get('http://127.0.0.1:3000/sitemap.xml', { headers: { host: hostname } })
  expect(sitemap.ok()).toBeTruthy()
  expect(await sitemap.text()).toContain(`<loc>https://${hostname}/</loc>`)

  const robots = await request.get('http://127.0.0.1:3000/robots.txt', { headers: { host: hostname } })
  expect(robots.ok()).toBeTruthy()
  expect(await robots.text()).toContain(`Sitemap: https://${hostname}/sitemap.xml`)
})
