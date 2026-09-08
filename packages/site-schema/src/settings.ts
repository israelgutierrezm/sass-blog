import { z } from 'zod'

/**
 * `settings` compartido por todas las secciones (presentación). Los valores de
 * background son ROLES de token (nunca hex arbitrario). Ver ADR-008.
 */
const spacingScale = z.enum(['none', 'sm', 'md', 'lg', 'xl'])

export const settingsSchema = z
  .object({
    spacing: z.object({ top: spacingScale, bottom: spacingScale }).strict().optional(),
    background: z.enum(['transparent', 'surface', 'surface-muted', 'primary', 'dark']).optional(),
    container: z.enum(['default', 'wide', 'full']).optional(),
    theme: z.enum(['inherit', 'light', 'dark']).optional(),
  })
  .strict()

export type SectionSettings = z.infer<typeof settingsSchema>
