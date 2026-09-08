import type { FieldDescriptor, VariantDefaults, VariantDefinition } from './define-component'
import { registry, REGISTRY_VERSION } from './registry'

export interface ComponentManifest {
  type: string
  name: string
  category: string
  variants: VariantDefinition[]
  fields: Record<string, FieldDescriptor>
  defaults: Record<string, VariantDefaults>
}

export interface RegistryManifest {
  version: number
  components: ComponentManifest[]
}

/**
 * Manifest para el ADMIN: de aquí deriva la paleta "Agregar sección" y los
 * controles del panel de props (ADR-004). No incluye los zod (no serializables);
 * la validación estructural va por el JSON Schema generado.
 */
export function buildManifest(): RegistryManifest {
  return {
    version: REGISTRY_VERSION,
    components: Object.values(registry).map((def) => ({
      type: def.type,
      name: def.name,
      category: def.category,
      variants: def.variants,
      fields: def.fields,
      defaults: def.defaults,
    })),
  }
}
