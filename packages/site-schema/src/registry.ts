import { hero } from './components/hero'
import { text } from './components/text'
import type { ComponentDefinition } from './define-component'

export const REGISTRY_VERSION = 1

/** Catálogo cerrado de componentes (fuente única, ADR-004). */
export const registry: Record<string, ComponentDefinition> = {
  [hero.type]: hero,
  [text.type]: text,
}

export function componentTypes(): string[] {
  return Object.keys(registry)
}

export function getComponent(type: string): ComponentDefinition | undefined {
  return registry[type]
}

export function variantTypes(type: string): string[] {
  return getComponent(type)?.variants.map((v) => v.type) ?? []
}
