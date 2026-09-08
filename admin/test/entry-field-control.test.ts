import type { CollectionFieldDto } from '@sass-blog/shared-types'
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import EntryFieldControl from '../src/components/cms/EntryFieldControl.vue'

function field(overrides: Partial<CollectionFieldDto>): CollectionFieldDto {
  return { id: 'F', key: 'k', label: 'Campo', type: 'text', required: false, config: null, position: 0, ...overrides }
}

function mountControl(f: CollectionFieldDto, modelValue: unknown = undefined) {
  return mount(EntryFieldControl, { props: { field: f, modelValue } })
}

describe('EntryFieldControl', () => {
  it('richtext y textarea usan <textarea>', () => {
    expect(mountControl(field({ type: 'richtext', key: 'body' })).find('textarea').exists()).toBe(true)
    expect(mountControl(field({ type: 'textarea' })).find('textarea').exists()).toBe(true)
  })

  it('integer usa <input type=number> y emite un número', async () => {
    const wrapper = mountControl(field({ type: 'integer', key: 'reading_time' }))
    const input = wrapper.find('input[type="number"]')
    expect(input.exists()).toBe(true)
    await input.setValue('7')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([7])
  })

  it('boolean usa checkbox y emite booleano', async () => {
    const wrapper = mountControl(field({ type: 'boolean', key: 'featured' }))
    const input = wrapper.find('input[type="checkbox"]')
    await input.setValue(true)
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([true])
  })

  it('media usa <input type=url>', () => {
    expect(mountControl(field({ type: 'media', key: 'featured_image' })).find('input[type="url"]').exists()).toBe(true)
  })

  it('select renderiza las opciones del config', () => {
    const wrapper = mountControl(field({ type: 'select', config: { options: ['a', 'b'] } }))
    const opts = wrapper.findAll('option')
    // '—' + a + b
    expect(opts).toHaveLength(3)
    expect(opts[1]!.text()).toBe('a')
  })

  it('marca los campos requeridos', () => {
    const wrapper = mountControl(field({ required: true }))
    expect(wrapper.find('span.text-red-500').exists()).toBe(true)
  })

  it('emite el texto en un campo de texto', async () => {
    const wrapper = mountControl(field({ type: 'text' }))
    await wrapper.find('input').setValue('hola')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['hola'])
  })
})
