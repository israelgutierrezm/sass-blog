import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import SeoPanel from '../src/components/builder/SeoPanel.vue'
import { useBuilderStore } from '../src/stores/builder'

describe('SeoPanel', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('aplica sólo los campos con valor y marca dirty', async () => {
    const builder = useBuilderStore()
    builder.schema = { schema_version: 1, sections: [] }
    builder.dirty = false

    const wrapper = mount(SeoPanel, { props: { ws: 'W', site: 'S' } })
    await wrapper.find('[data-testid="seo-meta-title"]').setValue('Mi título')
    await wrapper.find('[data-testid="seo-robots"]').setValue('noindex,follow')
    await wrapper.find('[data-testid="seo-apply"]').trigger('click')

    expect(builder.schema.seo).toEqual({ meta_title: 'Mi título', robots: 'noindex,follow' })
    expect(builder.dirty).toBe(true)
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('con todos los campos vacíos limpia el SEO (undefined)', async () => {
    const builder = useBuilderStore()
    builder.schema = { schema_version: 1, seo: { meta_title: 'Viejo' }, sections: [] }

    const wrapper = mount(SeoPanel, { props: { ws: 'W', site: 'S' } })
    await wrapper.find('[data-testid="seo-meta-title"]').setValue('')
    await wrapper.find('[data-testid="seo-apply"]').trigger('click')

    expect(builder.schema.seo).toBeUndefined()
  })

  it('precarga el SEO existente del schema', () => {
    const builder = useBuilderStore()
    builder.schema = { schema_version: 1, seo: { meta_title: 'Cargado', robots: 'index,follow' }, sections: [] }

    const wrapper = mount(SeoPanel, { props: { ws: 'W', site: 'S' } })

    expect((wrapper.find('[data-testid="seo-meta-title"]').element as HTMLInputElement).value).toBe('Cargado')
    expect((wrapper.find('[data-testid="seo-robots"]').element as HTMLSelectElement).value).toBe('index,follow')
  })
})
