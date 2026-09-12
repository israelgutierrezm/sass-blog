import type { PageSchema } from '@sass-blog/site-schema'
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { PageRenderer } from '../src'

const schema: PageSchema = {
  schema_version: 1,
  sections: [
    {
      id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
      type: 'hero',
      variant: 'hero-split',
      visible: true,
      props: { heading: 'Hola Mundo', image: { src: 'x.jpg', alt: 'x' } },
      settings: { background: 'surface', spacing: { top: 'xl', bottom: 'xl' } },
    },
    {
      id: '01BX5ZZKBKACTAV9WEVGEMMVRZ',
      type: 'text',
      variant: 'text-prose',
      visible: true,
      props: { paragraphs: ['uno', 'dos'] },
      settings: {},
    },
    {
      // Tipo desconocido: NO debe romper el render.
      id: '01CQ4Z6RF5N8K0P2W3X4Y5Z6A7',
      type: 'carousel',
      variant: 'x',
      visible: true,
      props: {},
      settings: {},
    },
  ],
}

describe('render compartido (Vite jsdom ↔ SSR)', () => {
  it('monta en jsdom sin lanzar y muestra el contenido', () => {
    const wrapper = mount(PageRenderer, { props: { schema } })
    const html = wrapper.html()

    expect(wrapper.find('.st-site-root').exists()).toBe(true)
    expect(html).toContain('Hola Mundo')
    expect(html).toContain('uno')
  })

  it('renderiza en SSR (renderToString) el mismo contenido, sin lanzar', async () => {
    const html = await renderToString(createSSRApp(PageRenderer, { schema }))

    expect(html).toContain('Hola Mundo')
    expect(html).toContain('uno')
    // El tipo desconocido no se muestra en producción (no editable) ni rompe.
    expect(html).not.toContain('Sección desconocida')
  })

  it('respeta visible=false (no renderiza la sección) en SSR', async () => {
    const first = schema.sections[0]!
    const hidden: PageSchema = { schema_version: 1, sections: [{ ...first, visible: false }] }

    const html = await renderToString(createSSRApp(PageRenderer, { schema: hidden }))
    expect(html).not.toContain('Hola Mundo')
  })

  it('aplica el override de tokens del sitio como estilo inline', async () => {
    const html = await renderToString(
      createSSRApp(PageRenderer, { schema, tokens: { colors: { primary: '#ff0000' } } }),
    )
    expect(html).toContain('--st-color-primary: #ff0000;')
  })
})

const GRID_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV'

const gridSchema: PageSchema = {
  schema_version: 1,
  sections: [
    {
      id: GRID_ID,
      type: 'collection-grid',
      variant: 'collection-grid-cards',
      visible: true,
      props: { collection: 'articles', columns: 3, showExcerpt: true },
      settings: {},
    },
  ],
}

const resolved = {
  [GRID_ID]: {
    items: [
      { id: 'E1', title: 'Uno', path: '/blog/uno', excerpt: 'Resumen uno' },
      { id: 'E2', title: 'Dos', path: '/blog/dos', excerpt: 'Resumen dos' },
    ],
    total: 2,
  },
}

describe('CollectionGrid (canal resolved)', () => {
  it('renderiza tarjetas con href = linkBase + path (SSR)', async () => {
    const html = await renderToString(
      createSSRApp(PageRenderer, { schema: gridSchema, resolved, linkBase: '/_site/ABC' }),
    )
    expect(html).toContain('Uno')
    expect(html).toContain('Resumen uno')
    expect(html).toContain('href="/_site/ABC/blog/uno"')
    expect(html).toContain('href="/_site/ABC/blog/dos"')
  })

  it('muestra placeholder cuando no hay datos resueltos', async () => {
    const html = await renderToString(createSSRApp(PageRenderer, { schema: gridSchema }))
    expect(html).toContain('Aún no hay contenido')
    expect(html).not.toContain('href=')
  })

  it('NO filtra resolvedData/linkBase a los componentes estáticos (ADR-008)', async () => {
    // Página de sólo hero/text con resolved + linkBase presentes.
    const html = await renderToString(createSSRApp(PageRenderer, { schema, resolved: {}, linkBase: '/_site/ABC' }))
    expect(html).toContain('Hola Mundo') // hero intacto
    expect(html.toLowerCase()).not.toContain('linkbase')
    expect(html.toLowerCase()).not.toContain('resolveddata')
  })
})

const NAV_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV'

const navSchema: PageSchema = {
  schema_version: 1,
  sections: [
    {
      id: NAV_ID,
      type: 'navigation',
      variant: 'navigation-horizontal',
      visible: true,
      props: { menu: 'primary' },
      settings: {},
    },
  ],
}

const navResolved = {
  [NAV_ID]: {
    items: [
      { label: 'Inicio', url: '/', children: [] },
      { label: 'Acerca', url: '/acerca', children: [{ label: 'Equipo', url: '/acerca/equipo', children: [] }] },
      { label: 'Externo', url: 'https://ejemplo.com', children: [] },
    ],
    total: 3,
  },
}

describe('Navigation (canal resolved)', () => {
  it('pinta el árbol: hrefs internos prefijados, externos tal cual, hijos anidados (SSR)', async () => {
    const html = await renderToString(
      createSSRApp(PageRenderer, { schema: navSchema, resolved: navResolved, linkBase: '/_site/ABC' }),
    )
    expect(html).toContain('Inicio')
    expect(html).toContain('href="/_site/ABC/"') // home interno prefijado
    expect(html).toContain('href="/_site/ABC/acerca"')
    expect(html).toContain('Equipo')
    expect(html).toContain('href="/_site/ABC/acerca/equipo"') // hijo anidado
    expect(html).toContain('href="https://ejemplo.com"') // externo tal cual
  })

  it('sin datos resueltos no pinta el nav', async () => {
    const html = await renderToString(createSSRApp(PageRenderer, { schema: navSchema }))
    expect(html).not.toContain('st-nav__link')
  })
})
