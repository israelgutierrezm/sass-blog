import type { FieldDescriptor } from '@sass-blog/site-schema'
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FieldControl from '../src/components/builder/FieldControl.vue'

function mountField(field: FieldDescriptor, modelValue: unknown, dynamicOptions?: { value: string; label: string }[]) {
  return mount(FieldControl, { props: { field, modelValue, dynamicOptions } })
}

describe('FieldControl (builder)', () => {
  it('number emite un número (no string)', async () => {
    const wrapper = mountField({ label: 'Máximo', control: 'number' }, 6)
    const input = wrapper.find('input[type="number"]')
    expect(input.exists()).toBe(true)
    await input.setValue('9')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([9])
  })

  it('dynamic-select renderiza las opciones cargadas y emite el value', async () => {
    const wrapper = mountField(
      { label: 'Colección', control: 'dynamic-select', optionsSource: 'collections' },
      '',
      [
        { value: 'articles', label: 'Artículos' },
        { value: 'guides', label: 'Guías' },
      ],
    )
    const select = wrapper.find('select')
    // '—' + 2 opciones
    expect(select.findAll('option')).toHaveLength(3)
    await select.setValue('guides')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['guides'])
  })

  it('boolean sigue emitiendo booleano', async () => {
    const wrapper = mountField({ label: 'Destacado', control: 'boolean' }, false)
    await wrapper.find('input[type="checkbox"]').setValue(true)
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([true])
  })
})
