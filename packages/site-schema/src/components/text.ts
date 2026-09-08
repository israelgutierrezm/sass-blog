import { z } from 'zod'
import { defineComponent } from '../define-component'
import { settingsSchema } from '../settings'

const textProps = z
  .object({
    heading: z.string().optional(),
    // Texto plano en FASE 2 (richtext estructurado es Fase 3).
    paragraphs: z.array(z.string()).default([]),
    align: z.enum(['left', 'center']).default('left'),
  })
  .strict()

export const text = defineComponent({
  type: 'text',
  name: 'Texto',
  category: 'content',
  variants: [{ type: 'text-prose', name: 'Prosa' }],
  propsSchema: textProps,
  settingsSchema,
  fields: {
    heading: { label: 'Título', control: 'text' },
    paragraphs: { label: 'Párrafos', control: 'string-list' },
    align: {
      label: 'Alineación',
      control: 'select',
      options: [
        { value: 'left', label: 'Izquierda' },
        { value: 'center', label: 'Centro' },
      ],
    },
  },
  defaults: {
    'text-prose': {
      props: { paragraphs: ['Escribe aquí tu contenido.'], align: 'left' },
      settings: { spacing: { top: 'md', bottom: 'md' } },
    },
  },
})
