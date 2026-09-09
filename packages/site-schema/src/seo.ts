import { z } from 'zod'

/**
 * SEO por página, opcional, a nivel raíz del page schema (ADR-018). Aditivo-opcional:
 * no cambia `schema_version`. `robots` es una lista blanca cerrada. Sirve igual en SSR
 * y en el build estático de Fase 5. Las entries derivan su SEO del contenido (título/
 * excerpt/featured_image) con overrides opcionales, sin usar este objeto.
 */
export const robotsValues = ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'] as const

export const seoSchema = z
  .object({
    meta_title: z.string().max(200).optional(),
    meta_description: z.string().max(500).optional(),
    canonical: z.string().url().optional(),
    robots: z.enum(robotsValues).optional(),
    og_image: z.string().url().optional(),
    jsonld_type: z.enum(['WebPage', 'Article']).optional(),
  })
  .strict()

export type SeoMeta = z.infer<typeof seoSchema>
