import { z } from 'zod'
import { defineComponent } from '../define-component'
import { settingsSchema } from '../settings'

/**
 * CollectionGrid: inserta una lista de entries de una colección en cualquier página.
 * Los props son SÓLO la QUERY (qué mostrar) + presentación; los DATOS los resuelve el
 * backend en render (sidecar `resolved`, ADR-013), nunca el cliente. `order` es una
 * lista blanca cerrada (no una expresión). `collection`/`category` refieren por handle/
 * slug estables (portables entre entornos), no por id interno.
 */
const collectionGridProps = z
  .object({
    collection: z.string().min(1),
    mode: z.enum(['automatic', 'manual']).default('automatic'),
    category: z.string().optional(),
    limit: z.number().int().min(1).max(48).default(6),
    order: z.enum(['recent', 'oldest', 'title']).default('recent'),
    columns: z.number().int().min(1).max(4).default(3),
    showExcerpt: z.boolean().default(true),
    showImage: z.boolean().default(true),
    showDate: z.boolean().default(true),
    showAuthor: z.boolean().default(false),
    showCategory: z.boolean().default(false),
    // Modo manual (diferido): slugs de entries elegidas a mano.
    manualEntries: z.array(z.string()).default([]),
  })
  .strict()

export const collectionGrid = defineComponent({
  type: 'collection-grid',
  name: 'Rejilla de colección',
  category: 'dynamic',
  variants: [
    { type: 'collection-grid-cards', name: 'Tarjetas' },
    { type: 'collection-grid-list', name: 'Lista' },
  ],
  propsSchema: collectionGridProps,
  settingsSchema,
  fields: {
    collection: { label: 'Colección', control: 'dynamic-select', optionsSource: 'collections' },
    mode: {
      label: 'Modo',
      control: 'select',
      // MVP: sólo automático (manual se difiere).
      options: [{ value: 'automatic', label: 'Automático' }],
    },
    category: { label: 'Categoría', control: 'dynamic-select', optionsSource: 'categories' },
    limit: { label: 'Máximo de entradas', control: 'number' },
    order: {
      label: 'Orden',
      control: 'select',
      options: [
        { value: 'recent', label: 'Más recientes' },
        { value: 'oldest', label: 'Más antiguas' },
        { value: 'title', label: 'Título (A-Z)' },
      ],
    },
    columns: { label: 'Columnas', control: 'number' },
    showExcerpt: { label: 'Mostrar resumen', control: 'boolean' },
    showImage: { label: 'Mostrar imagen', control: 'boolean' },
    showDate: { label: 'Mostrar fecha', control: 'boolean' },
    showAuthor: { label: 'Mostrar autor', control: 'boolean' },
    showCategory: { label: 'Mostrar categoría', control: 'boolean' },
  },
  defaults: {
    'collection-grid-cards': {
      props: {
        collection: 'articles',
        mode: 'automatic',
        limit: 6,
        order: 'recent',
        columns: 3,
        showExcerpt: true,
        showImage: true,
        showDate: true,
      },
      settings: { spacing: { top: 'lg', bottom: 'lg' } },
    },
    'collection-grid-list': {
      props: {
        collection: 'articles',
        mode: 'automatic',
        limit: 10,
        order: 'recent',
        columns: 1,
        showExcerpt: true,
        showImage: false,
        showDate: true,
      },
      settings: { spacing: { top: 'md', bottom: 'md' } },
    },
  },
})
