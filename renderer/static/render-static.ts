import { mkdir, readFile, writeFile } from 'node:fs/promises'
import { dirname, join } from 'node:path'
import { PageRenderer } from '@sass-blog/site-components'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'

/**
 * CLI de render ESTÁTICO (ADR-019). Lee el build manifest producido por el backend y
 * emite HTML por página con los MISMOS site-components que el render dinámico
 * (PageRenderer + renderToString), a clean paths (`/`→index.html, `/a`→a/index.html).
 * No consulta datos: todo viene resuelto en el manifest (schema + resolved + seo).
 */

export interface ManifestSeo {
  title?: string
  description?: string | null
  canonical?: string
  robots?: string
  og_image?: string | null
  jsonld_type?: string | null
}

export interface ManifestPage {
  path: string
  render: {
    page: { schema_version: number; sections: unknown[] }
    resolved?: Record<string, unknown>
    seo?: ManifestSeo
  }
}

export interface ManifestSite {
  ulid: string
  name: string
  base_url: string
  /** API pública absoluta (p.ej. https://api.host/api/v1): destino de los forms (newsletter). */
  api_base?: string
}

export interface BuildManifest {
  site: ManifestSite
  pages: ManifestPage[]
  media: string[]
}

/** Ruta pública → fichero con clean path. `/`→index.html, `/a/b`→a/b/index.html. */
export function pathToFile(urlPath: string): string {
  const clean = urlPath.replace(/^\/+|\/+$/g, '')
  return clean === '' ? 'index.html' : `${clean}/index.html`
}

/** Href relativo a `assets/styles.css` desde el index.html de la ruta dada. */
export function stylesheetHref(urlPath: string): string {
  const clean = urlPath.replace(/^\/+|\/+$/g, '')
  const depth = clean === '' ? 0 : clean.split('/').length
  return `${'../'.repeat(depth)}assets/styles.css`
}

function esc(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

/** Etiquetas del <head>: SEO (title/description/canonical/robots/OG) + hoja de estilos. */
export function headTags(seo: ManifestSeo | undefined, stylesheet: string): string {
  const title = seo?.title ?? ''
  const tags = [`<title>${esc(title)}</title>`]

  if (seo?.description) {
    tags.push(`<meta name="description" content="${esc(seo.description)}">`)
  }
  if (seo?.canonical) {
    tags.push(`<link rel="canonical" href="${esc(seo.canonical)}">`)
  }
  if (seo?.robots) {
    tags.push(`<meta name="robots" content="${esc(seo.robots)}">`)
  }
  tags.push(`<meta property="og:title" content="${esc(title)}">`)
  if (seo?.description) {
    tags.push(`<meta property="og:description" content="${esc(seo.description)}">`)
  }
  if (seo?.og_image) {
    tags.push(`<meta property="og:image" content="${esc(seo.og_image)}">`)
  }
  tags.push(`<link rel="stylesheet" href="${esc(stylesheet)}">`)

  return tags.join('\n    ')
}

/** Documento HTML completo de una página (body renderizado + head). */
export function wrapDocument(body: string, seo: ManifestSeo | undefined, stylesheet: string): string {
  return `<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    ${headTags(seo, stylesheet)}
  </head>
  <body>
${body}
  </body>
</html>
`
}

/**
 * Renderiza una página del manifest a HTML (linkBase '' → clean paths). Con `site.api_base`
 * los componentes interactivos reciben el contexto público (el form de newsletter postea a la
 * API sin JS); sin él quedan inertes, como en el preview.
 */
export async function renderPage(page: ManifestPage, site?: ManifestSite): Promise<string> {
  const schema = { schema_version: page.render.page.schema_version, sections: page.render.page.sections }
  const app = createSSRApp(PageRenderer, {
    schema,
    resolved: page.render.resolved,
    linkBase: '',
    siteId: site?.api_base ? site.ulid : undefined,
    publicBase: site?.api_base,
  })
  const body = await renderToString(app)

  return wrapDocument(body, page.render.seo, stylesheetHref(page.path))
}

/** Escribe todo el sitio (HTML por página + assets/styles.css) en `outDir`. */
export async function renderManifest(manifest: BuildManifest, opts: { outDir: string, css: string }): Promise<{ files: number }> {
  for (const page of manifest.pages) {
    const html = await renderPage(page, manifest.site)
    const file = join(opts.outDir, pathToFile(page.path))
    await mkdir(dirname(file), { recursive: true })
    await writeFile(file, html, 'utf8')
  }

  const cssFile = join(opts.outDir, 'assets', 'styles.css')
  await mkdir(dirname(cssFile), { recursive: true })
  await writeFile(cssFile, opts.css, 'utf8')

  return { files: manifest.pages.length }
}

/** Entrada CLI: `render-static <manifest.json> <outDir> [styles.css]`. */
export async function main(argv: string[]): Promise<void> {
  const [manifestPath, outDir, cssPath] = argv
  if (!manifestPath || !outDir) {
    console.error('uso: render-static <manifest.json> <outDir> [styles.css]')
    process.exitCode = 1

    return
  }

  const manifest = JSON.parse(await readFile(manifestPath, 'utf8')) as BuildManifest
  const css = cssPath ? await readFile(cssPath, 'utf8') : ''
  const { files } = await renderManifest(manifest, { outDir, css })
  // eslint-disable-next-line no-console
  console.log(`render-static: ${files} páginas en ${outDir}`)
}
