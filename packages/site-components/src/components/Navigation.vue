<script setup lang="ts">
import { computed } from 'vue'
import NavList, { type NavItem } from './NavList.vue'

/**
 * Navigation: componente DETERMINISTA. No consulta datos: recibe `resolvedData` (el árbol
 * del menú ya resuelto por el backend, ADR-017) y `linkBase` (prefijo de ruta del sitio).
 * La variante decide la orientación. Sin ítems → no pinta nada; nunca lanza.
 */
const props = defineProps<{
  variant: string
  propsData: Record<string, unknown>
  resolvedData?: { items: NavItem[]; total: number }
  linkBase?: string
}>()

const items = computed<NavItem[]>(() => props.resolvedData?.items ?? [])
</script>

<template>
  <nav v-if="items.length" class="st-nav" :class="`st-nav--${variant}`">
    <NavList :items="items" :link-base="linkBase ?? ''" />
  </nav>
</template>

<style>
.st-nav__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  gap: var(--st-spacing-md, 1rem);
  font-family: var(--st-typography-body, system-ui, sans-serif);
}
.st-nav--navigation-vertical > .st-nav__list {
  flex-direction: column;
  gap: var(--st-spacing-sm, 0.5rem);
}
/* Submenús: siempre en columna, indentados. */
.st-nav__item .st-nav__list {
  flex-direction: column;
  gap: var(--st-spacing-sm, 0.5rem);
  margin-top: var(--st-spacing-sm, 0.5rem);
  padding-left: var(--st-spacing-md, 1rem);
}
.st-nav__link {
  color: var(--st-color-text, #111827);
  text-decoration: none;
}
.st-nav__link:hover {
  color: var(--st-color-primary, #2563eb);
}
</style>
