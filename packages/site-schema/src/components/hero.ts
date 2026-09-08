import { z } from 'zod'
import { defineComponent } from '../define-component'
import { settingsSchema } from '../settings'

const cta = z.object({ label: z.string().min(1), href: z.string().min(1) }).strict()

const heroProps = z
  .object({
    eyebrow: z.string().optional(),
    heading: z.string().min(1),
    subheading: z.string().optional(),
    align: z.enum(['left', 'center']).default('center'),
    primaryCta: cta.optional(),
    secondaryCta: cta.optional(),
    image: z.object({ src: z.string().min(1), alt: z.string() }).strict().optional(),
  })
  .strict()

export const hero = defineComponent({
  type: 'hero',
  name: 'Hero',
  category: 'header',
  variants: [
    { type: 'hero-centered', name: 'Centrado' },
    { type: 'hero-split', name: 'Dividido con imagen', requires: ['image'] },
    { type: 'hero-minimal', name: 'Minimalista' },
  ],
  propsSchema: heroProps,
  settingsSchema,
  fields: {
    eyebrow: { label: 'Antetítulo', control: 'text' },
    heading: { label: 'Título', control: 'text' },
    subheading: { label: 'Subtítulo', control: 'textarea' },
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
    'hero-centered': {
      props: { heading: 'Título principal', subheading: 'Un subtítulo que explica la propuesta.', align: 'center' },
      settings: { spacing: { top: 'xl', bottom: 'xl' } },
    },
    'hero-split': {
      props: {
        heading: 'Título principal',
        align: 'left',
        image: { src: 'https://placehold.co/800x600', alt: '' },
      },
      settings: { spacing: { top: 'lg', bottom: 'lg' } },
    },
    'hero-minimal': {
      props: { heading: 'Título' },
      settings: {},
    },
  },
})
