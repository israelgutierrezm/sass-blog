import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import MediaPickerModal from '../src/components/media/MediaPickerModal.vue'

vi.mock('../src/services/api', () => ({
  mediaApi: {
    list: vi.fn().mockResolvedValue({
      data: [
        {
          id: 'A1',
          url: '/m/a.jpg',
          variants: { thumb: '/m/a-thumb.jpg', medium: '/m/a-medium.jpg' },
          original_filename: 'a.jpg',
          mime_type: 'image/jpeg',
          size_bytes: 1000,
          width: 800,
          height: 600,
          alt: null,
          title: null,
          status: 'ready',
        },
      ],
    }),
    upload: vi.fn(),
  },
}))

describe('MediaPickerModal', () => {
  it('lista los assets y emite la URL (medium) al elegir', async () => {
    const wrapper = mount(MediaPickerModal, { props: { ws: 'W', site: 'S' } })
    await flushPromises()

    const pick = wrapper.find('[data-testid="pick-A1"]')
    expect(pick.exists()).toBe(true)

    await pick.trigger('click')

    expect(wrapper.emitted('select')?.[0]).toEqual(['/m/a-medium.jpg'])
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('cierra al pulsar la X', async () => {
    const wrapper = mount(MediaPickerModal, { props: { ws: 'W', site: 'S' } })
    await flushPromises()

    await wrapper.find('[data-testid="picker-close"]').trigger('click')
    expect(wrapper.emitted('close')).toBeTruthy()
  })
})
