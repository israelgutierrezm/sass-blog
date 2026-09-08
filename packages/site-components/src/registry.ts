import type { Component } from 'vue'
import CollectionGrid from './components/CollectionGrid.vue'
import Hero from './components/Hero.vue'
import Text from './components/Text.vue'

/** Mapa type -> componente Vue. Variante NO es un componente: es prop del suyo. */
const COMPONENTS: Record<string, Component> = {
  hero: Hero,
  text: Text,
  'collection-grid': CollectionGrid,
}

/**
 * Tipos DINÁMICOS: reciben `resolvedData`/`linkBase` del sidecar `resolved`. Espeja
 * `category: 'dynamic'` del registro de site-schema; se mantiene local para que la
 * ruta de render no cargue el registro zod.
 */
const DYNAMIC_TYPES = new Set<string>(['collection-grid'])

export function componentFor(type: string): Component | undefined {
  return COMPONENTS[type]
}

export function isDynamicType(type: string): boolean {
  return DYNAMIC_TYPES.has(type)
}
