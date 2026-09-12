import { z } from 'zod'
import { defineComponent } from '../define-component'
import { settingsSchema } from '../settings'

/**
 * Navigation: pinta un menú del sitio (ADR-017). El prop es SÓLO la referencia al menú
 * por `handle` estable; el ÁRBOL (labels + urls resueltas) lo aporta el backend en
 * render (sidecar `resolved`, ADR-013), nunca el cliente. La variante decide la
 * orientación. Las urls se resuelven en vivo (respeta slug history).
 */
const navigationProps = z
  .object({
    menu: z.string().min(1),
  })
  .strict()

export const navigation = defineComponent({
  type: 'navigation',
  name: 'Navegación',
  category: 'dynamic',
  variants: [
    { type: 'navigation-horizontal', name: 'Horizontal' },
    { type: 'navigation-vertical', name: 'Vertical' },
  ],
  propsSchema: navigationProps,
  settingsSchema,
  fields: {
    menu: { label: 'Menú', control: 'dynamic-select', optionsSource: 'menus' },
  },
  defaults: {
    'navigation-horizontal': {
      props: { menu: 'primary' },
      settings: { spacing: { top: 'sm', bottom: 'sm' } },
    },
    'navigation-vertical': {
      props: { menu: 'primary' },
      settings: { spacing: { top: 'sm', bottom: 'sm' } },
    },
  },
})
