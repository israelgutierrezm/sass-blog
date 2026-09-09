import { z } from 'zod'
import type { ComponentDefinition } from './define-component'
import { registry } from './registry'
import { seoSchema } from './seo'
import { type SchemaProfile, ULID_RE } from './types'

function sectionSchema(def: ComponentDefinition) {
  const variants = def.variants.map((v) => v.type) as [string, ...string[]]

  return z
    .object({
      id: z.string().regex(ULID_RE),
      type: z.literal(def.type),
      variant: z.enum(variants),
      visible: z.boolean(),
      props: def.propsSchema,
      settings: def.settingsSchema,
    })
    .strict()
}

type SectionSchema = ReturnType<typeof sectionSchema>

const sectionSchemas = Object.values(registry).map(sectionSchema) as [SectionSchema, ...SectionSchema[]]
const sectionUnion = z.discriminatedUnion('type', sectionSchemas)

/**
 * Zod del page schema por perfil. Draft: relajado (sections puede ir vacío).
 * Publish: estricto (al menos una sección). De aquí se genera el JSON Schema que
 * valida el backend (ADR-004).
 */
export function pageSchemaForProfile(profile: SchemaProfile) {
  const sections = profile === 'publish' ? z.array(sectionUnion).min(1) : z.array(sectionUnion)

  return z
    .object({ schema_version: z.number().int().positive(), seo: seoSchema.optional(), sections })
    .strict()
}

export interface SchemaError {
  path: string
  message: string
}

export interface ValidationResult {
  valid: boolean
  errors: SchemaError[]
}

/**
 * Valida un page schema en el frontend (para PREVISUALIZAR el veredicto). El
 * backend decide con el JSON Schema generado. Además de la forma, verifica que
 * los `id` de sección sean únicos (regla semántica fuera del JSON Schema).
 */
export function validatePageSchema(input: unknown, profile: SchemaProfile = 'draft'): ValidationResult {
  const parsed = pageSchemaForProfile(profile).safeParse(input)
  const errors: SchemaError[] = []

  if (!parsed.success) {
    for (const issue of parsed.error.issues) {
      errors.push({ path: issue.path.join('/'), message: issue.message })
    }

    return { valid: false, errors }
  }

  const seen = new Set<string>()
  for (const section of parsed.data.sections) {
    if (seen.has(section.id)) {
      errors.push({ path: 'sections', message: `id de sección duplicado: ${section.id}` })
    }
    seen.add(section.id)
  }

  return { valid: errors.length === 0, errors }
}
