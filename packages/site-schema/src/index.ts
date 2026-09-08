export * from './types'
export * from './define-component'
export { settingsSchema, type SectionSettings } from './settings'
export {
  componentTypes,
  getComponent,
  registry,
  REGISTRY_VERSION,
  variantTypes,
} from './registry'
export { hero } from './components/hero'
export { text } from './components/text'
export { buildManifest, type ComponentManifest, type RegistryManifest } from './manifest'
export {
  pageSchemaForProfile,
  type SchemaError,
  validatePageSchema,
  type ValidationResult,
} from './validate'
