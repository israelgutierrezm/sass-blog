import { mkdtemp, readFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { type BuildManifest, headTags, pathToFile, renderManifest, stylesheetHref } from '../static/render-static'

describe('render-static: clean paths', () => {
  it('mapea la ruta pública a fichero index.html', () => {
    expect(pathToFile('/')).toBe('index.html')
    expect(pathToFile('/acerca')).toBe('acerca/index.html')
    expect(pathToFile('/blog/mi-post')).toBe('blog/mi-post/index.html')
  })

  it('calcula el href relativo a la hoja de estilos según la profundidad', () => {
    expect(stylesheetHref('/')).toBe('assets/styles.css')
    expect(stylesheetHref('/acerca')).toBe('../assets/styles.css')
    expect(stylesheetHref('/blog/mi-post')).toBe('../../assets/styles.css')
  })

  it('el head incluye SEO y la hoja de estilos', () => {
    const head = headTags({ title: 'Inicio', description: 'Bienvenido', canonical: 'https://x/', robots: 'index,follow' }, 'assets/styles.css')
    expect(head).toContain('<title>Inicio</title>')
    expect(head).toContain('name="description" content="Bienvenido"')
    expect(head).toContain('rel="canonical" href="https://x/"')
    expect(head).toContain('name="robots" content="index,follow"')
    expect(head).toContain('rel="stylesheet" href="assets/styles.css"')
  })
})

const manifest: BuildManifest = {
  site: { ulid: 'S1', name: 'Demo', base_url: 'https://demo.test' },
  pages: [
    {
      path: '/',
      render: {
        page: {
          schema_version: 1,
          sections: [{ id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', type: 'hero', variant: 'hero-centered', visible: true, props: { heading: 'Hola Estático' }, settings: {} }],
        },
        seo: { title: 'Inicio', description: 'Portada' },
      },
    },
    {
      path: '/acerca',
      render: {
        page: {
          schema_version: 1,
          sections: [{ id: '01BX5ZZKBKACTAV9WEVGEMMVRZ', type: 'text', variant: 'text-prose', visible: true, props: { paragraphs: ['Sobre nosotros'] }, settings: {} }],
        },
        seo: { title: 'Acerca' },
      },
    },
  ],
  media: [],
}

describe('render-static: renderManifest', () => {
  it('emite HTML por página (site-components) a clean paths + styles.css', async () => {
    const outDir = await mkdtemp(join(tmpdir(), 'static-'))

    const result = await renderManifest(manifest, { outDir, css: '/* tokens + components */' })
    expect(result.files).toBe(2)

    const home = await readFile(join(outDir, 'index.html'), 'utf8')
    expect(home).toContain('Hola Estático') // render real de site-components
    expect(home).toContain('<title>Inicio</title>')
    expect(home).toContain('href="assets/styles.css"')

    const acerca = await readFile(join(outDir, 'acerca', 'index.html'), 'utf8')
    expect(acerca).toContain('Sobre nosotros')
    expect(acerca).toContain('href="../assets/styles.css"') // profundidad 1

    const css = await readFile(join(outDir, 'assets', 'styles.css'), 'utf8')
    expect(css).toContain('tokens + components')
  })
})
