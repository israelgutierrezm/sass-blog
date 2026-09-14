import { z } from 'zod'
import { defineComponent } from '../define-component'
import { settingsSchema } from '../settings'

/**
 * Newsletter: formulario de captura de suscriptores (ADR-022). Los props son sólo el copy
 * (título, descripción, etiqueta del botón, mensaje de éxito); el destino del envío (sitio +
 * API pública) lo aporta el renderer por contexto, nunca el schema. El submit hace POST al
 * endpoint público `subscribe` (doble opt-in). En el preview del Builder es inerte.
 */
const newsletterProps = z
  .object({
    heading: z.string().min(1),
    description: z.string().optional(),
    buttonLabel: z.string().min(1),
    successMessage: z.string().min(1),
  })
  .strict()

export const newsletter = defineComponent({
  type: 'newsletter',
  name: 'Newsletter',
  category: 'cta',
  variants: [{ type: 'newsletter-inline', name: 'En línea' }],
  propsSchema: newsletterProps,
  settingsSchema,
  fields: {
    heading: { label: 'Título', control: 'text' },
    description: { label: 'Descripción', control: 'textarea' },
    buttonLabel: { label: 'Texto del botón', control: 'text' },
    successMessage: { label: 'Mensaje de éxito', control: 'text' },
  },
  defaults: {
    'newsletter-inline': {
      props: {
        heading: 'Suscríbete a la newsletter',
        description: 'Recibe las novedades directamente en tu correo.',
        buttonLabel: 'Suscribirme',
        successMessage: '¡Gracias! Revisa tu correo para confirmar la suscripción.',
      },
      settings: { spacing: { top: 'lg', bottom: 'lg' } },
    },
  },
})
