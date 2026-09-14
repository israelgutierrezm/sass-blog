import { z } from 'zod'
import { defineComponent } from '../define-component'
import { settingsSchema } from '../settings'

/**
 * Featured (Portada): bloque de artículos elegidos y ordenados A MANO (ADR-024). A diferencia
 * del CollectionGrid (query automática), aquí `items` es una lista ORDENADA de referencias
 * (ULIDs de entradas). Los datos los resuelve el backend en render (canal `resolved`, ADR-013),
 * nunca el cliente. Requiere el plan con `publisher.frontpages` (gating al guardar el schema).
 */
const featuredProps = z
  .object({
    collection: z.string().min(1),
    items: z.array(z.string()).default([]),
    heading: z.string().optional(),
    showExcerpt: z.boolean().default(true),
    showImage: z.boolean().default(true),
    showDate: z.boolean().default(true),
  })
  .strict()

export const featured = defineComponent({
  type: 'featured',
  name: 'Portada',
  category: 'dynamic',
  variants: [
    { type: 'featured-lead', name: 'Destacado + secundarias' },
    { type: 'featured-list', name: 'Lista' },
  ],
  propsSchema: featuredProps,
  settingsSchema,
  fields: {
    collection: { label: 'Colección', control: 'dynamic-select', optionsSource: 'collections' },
    items: { label: 'Artículos destacados', control: 'entry-picker', optionsSource: 'entries' },
    heading: { label: 'Título', control: 'text' },
    showExcerpt: { label: 'Mostrar resumen', control: 'boolean' },
    showImage: { label: 'Mostrar imagen', control: 'boolean' },
    showDate: { label: 'Mostrar fecha', control: 'boolean' },
  },
  defaults: {
    'featured-lead': {
      props: { collection: 'articles', items: [], showExcerpt: true, showImage: true, showDate: true },
      settings: { spacing: { top: 'lg', bottom: 'lg' } },
    },
    'featured-list': {
      props: { collection: 'articles', items: [], showExcerpt: false, showImage: false, showDate: true },
      settings: { spacing: { top: 'md', bottom: 'md' } },
    },
  },
})
