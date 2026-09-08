import type { Component } from 'vue'
import Hero from './components/Hero.vue'
import Text from './components/Text.vue'

/** Mapa type -> componente Vue. Variante NO es un componente: es prop del suyo. */
const COMPONENTS: Record<string, Component> = {
  hero: Hero,
  text: Text,
}

export function componentFor(type: string): Component | undefined {
  return COMPONENTS[type]
}
