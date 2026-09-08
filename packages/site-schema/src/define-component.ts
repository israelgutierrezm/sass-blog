import type { z } from 'zod'

export type FieldControl =
  | 'text'
  | 'textarea'
  | 'select'
  | 'boolean'
  | 'url'
  | 'string-list'
  | 'number'
  | 'dynamic-select'

/** Fuente de opciones que el admin resuelve por API para un control dynamic-select. */
export type OptionsSource = 'collections' | 'categories'

/** Descriptor de UI de un prop (el admin deriva sus controles de aquí). */
export interface FieldDescriptor {
  label: string
  control: FieldControl
  options?: { value: string; label: string }[]
  /** Para control 'dynamic-select': el admin puebla las opciones desde esta fuente. */
  optionsSource?: OptionsSource
}

export interface VariantDefinition {
  /** Tipo completo con prefijo, p.ej. 'hero-centered'. */
  type: string
  name: string
  /** Props que la variante espera (metadato para el admin en FASE 2). */
  requires?: string[]
}

export interface VariantDefaults {
  props: Record<string, unknown>
  settings: Record<string, unknown>
}

export type ComponentCategory =
  | 'header'
  | 'content'
  | 'media'
  | 'cta'
  | 'contact'
  | 'footer'
  | 'dynamic'

export interface ComponentDefinition {
  type: string
  name: string
  category: ComponentCategory
  variants: VariantDefinition[]
  propsSchema: z.ZodTypeAny
  settingsSchema: z.ZodTypeAny
  /** Descriptores de UI por prop escalar (MVP: props de nivel superior). */
  fields: Record<string, FieldDescriptor>
  /** Defaults por variante (props + settings). */
  defaults: Record<string, VariantDefaults>
}

export function defineComponent(definition: ComponentDefinition): ComponentDefinition {
  return definition
}
