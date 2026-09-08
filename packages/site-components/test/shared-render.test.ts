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
