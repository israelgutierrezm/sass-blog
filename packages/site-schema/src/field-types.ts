/**
 * Catálogo CERRADO de tipos de campo de colección (ADR-010). Fuente de verdad
 * ÚNICA en TS; el build emite `field-types.v1.json` (+ lock) a
 * `backend/resources/site-schema/`, y el enum PHP `FieldType` debe coincidir en el
 * CONJUNTO (test de conformidad Pest+Vitest). `label`/`control` son para el admin;
 * las reglas de validación por tipo viven en el validador (backend + espejo TS).
 */
export type FieldTypeControl =
  | 'text'
  | 'textarea'
  | 'number'
  | 'checkbox'
  | 'select'
  | 'multiselect'
  | 'date'
  | 'datetime'
  | 'relation'
  | 'media'
  | 'json'

export interface FieldTypeDef {
  key: string
  label: string
  control: FieldTypeControl
  /** Integridad exige BD + tenant (sólo validable en backend). */
  referential: boolean
  /** Escalar bindeable en plantillas (ADR-012). */
  bindable: boolean
}

export const fieldTypes: FieldTypeDef[] = [
  { key: 'text', label: 'Texto', control: 'text', referential: false, bindable: true },
  { key: 'textarea', label: 'Área de texto', control: 'textarea', referential: false, bindable: true },
  { key: 'richtext', label: 'Texto enriquecido', control: 'textarea', referential: false, bindable: true },
  { key: 'integer', label: 'Entero', control: 'number', referential: false, bindable: true },
  { key: 'decimal', label: 'Decimal', control: 'number', referential: false, bindable: true },
  { key: 'boolean', label: 'Booleano', control: 'checkbox', referential: false, bindable: true },
  { key: 'date', label: 'Fecha', control: 'date', referential: false, bindable: true },
  { key: 'datetime', label: 'Fecha y hora', control: 'datetime', referential: false, bindable: true },
  { key: 'money', label: 'Dinero', control: 'number', referential: false, bindable: true },
  { key: 'email', label: 'Correo', control: 'text', referential: false, bindable: true },
  { key: 'url', label: 'URL', control: 'text', referential: false, bindable: true },
  { key: 'slug', label: 'Slug', control: 'text', referential: false, bindable: true },
  { key: 'select', label: 'Selección', control: 'select', referential: false, bindable: true },
  { key: 'multiselect', label: 'Selección múltiple', control: 'multiselect', referential: false, bindable: false },
  { key: 'media', label: 'Medio', control: 'media', referential: true, bindable: false },
  { key: 'media_array', label: 'Medios', control: 'media', referential: true, bindable: false },
  { key: 'relation', label: 'Relación', control: 'relation', referential: true, bindable: false },
  { key: 'json', label: 'JSON', control: 'json', referential: false, bindable: false },
]

export function fieldTypeKeys(): string[] {
  return fieldTypes.map((t) => t.key)
}

/** Contenido JSON del artefacto commiteado (para el build y el test de frescura). */
export function fieldTypesArtifact(): string {
  return `${JSON.stringify({ version: 1, types: fieldTypes }, null, 2)}\n`
}
