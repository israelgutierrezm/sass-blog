/**
 * Forma EXACTA del page schema (ADR-002). El orden del array `sections` ES el
 * orden de render. Cada sección es { id, type, variant, visible, props, settings }.
 */
export interface Section {
  id: string
  type: string
  variant: string
  visible: boolean
  props: Record<string, unknown>
  settings: Record<string, unknown>
}

export interface PageSchema {
  schema_version: number
  sections: Section[]
}

export type SchemaProfile = 'draft' | 'publish'

/** ULID Crockford en MAYÚSCULAS (coincide con HasPublicUlid::newUlid del backend). */
export const ULID_RE = /^[0-9A-HJKMNP-TV-Z]{26}$/

export function emptyPageSchema(): PageSchema {
  return { schema_version: 1, sections: [] }
}
