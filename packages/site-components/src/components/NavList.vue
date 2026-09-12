<script setup lang="ts">
/**
 * Lista recursiva de un menú. DETERMINISTA: pinta el árbol ya resuelto por el backend.
 * Los enlaces internos (empiezan por `/`) se prefijan con `linkBase`; los externos
 * (http(s) o protocol-relative) se dejan tal cual. Se auto-referencia para los hijos.
 */
export interface NavItem {
  label: string
  url: string
  children?: NavItem[]
}

const props = defineProps<{ items: NavItem[]; linkBase?: string }>()

function href(item: NavItem): string {
  return /^(?:https?:)?\/\//i.test(item.url) ? item.url : (props.linkBase ?? '') + item.url
}
</script>

<template>
  <ul class="st-nav__list">
    <li v-for="(item, i) in items" :key="i" class="st-nav__item">
      <a class="st-nav__link" :href="href(item)">{{ item.label }}</a>
      <NavList
        v-if="item.children && item.children.length"
        :items="item.children"
        :link-base="linkBase"
      />
    </li>
  </ul>
</template>
