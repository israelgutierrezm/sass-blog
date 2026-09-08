<script setup lang="ts">
import { computed } from 'vue'

interface Card {
  id: string
  title: string
  path?: string | null
  excerpt?: string | null
  image?: string | null
  date?: string | null
  author?: { name: string; slug: string } | null
  category?: { name: string; slug: string } | null
}

interface GridProps {
  columns?: number
  showExcerpt?: boolean
  showImage?: boolean
  showDate?: boolean
  showAuthor?: boolean
  showCategory?: boolean
}

/**
 * CollectionGrid: componente DETERMINISTA. No consulta datos: recibe `resolvedData`
 * (tarjetas ya resueltas por el backend, ADR-013) y `linkBase` (prefijo de ruta del
 * sitio). Los enlaces son `linkBase + card.path`. Sin datos → placeholder; nunca lanza.
 */
const props = defineProps<{
  variant: string
  propsData: Record<string, unknown>
  resolvedData?: { items: Card[]; total: number }
  linkBase?: string
}>()

const p = computed(() => props.propsData as unknown as GridProps)
const items = computed<Card[]>(() => props.resolvedData?.items ?? [])
const columns = computed(() => Math.min(4, Math.max(1, p.value.columns ?? 3)))

function href(card: Card): string | undefined {
  return card.path ? (props.linkBase ?? '') + card.path : undefined
}
</script>

<template>
  <div class="st-grid" :class="`st-grid--${variant}`" :style="{ '--st-grid-cols': columns }">
    <p v-if="items.length === 0" class="st-grid__empty">Aún no hay contenido para mostrar.</p>
    <a v-for="card in items" :key="card.id" class="st-grid__card" :href="href(card)">
      <img
        v-if="(p.showImage ?? true) && card.image"
        class="st-grid__image"
        :src="card.image"
        :alt="card.title"
      />
      <div class="st-grid__body">
        <h3 class="st-grid__title">{{ card.title }}</h3>
        <p v-if="(p.showExcerpt ?? true) && card.excerpt" class="st-grid__excerpt">{{ card.excerpt }}</p>
        <div class="st-grid__meta">
          <span v-if="(p.showCategory ?? false) && card.category" class="st-grid__category">{{ card.category.name }}</span>
          <span v-if="(p.showAuthor ?? false) && card.author" class="st-grid__author">{{ card.author.name }}</span>
          <time v-if="(p.showDate ?? true) && card.date" class="st-grid__date" :datetime="card.date">{{ card.date }}</time>
        </div>
      </div>
    </a>
  </div>
</template>

<style>
.st-grid {
  display: grid;
  grid-template-columns: repeat(var(--st-grid-cols, 3), minmax(0, 1fr));
  gap: var(--st-spacing-lg, 2rem);
}
.st-grid--collection-grid-list {
  grid-template-columns: minmax(0, 1fr);
}
.st-grid__empty {
  grid-column: 1 / -1;
  color: var(--st-color-text-muted, #6b7280);
  font-family: var(--st-typography-body, system-ui, sans-serif);
}
.st-grid__card {
  display: block;
  text-decoration: none;
  color: inherit;
  border-radius: var(--st-radius-lg, 16px);
  overflow: hidden;
  background: var(--st-color-surface, #ffffff);
  border: 1px solid var(--st-color-border, #e5e7eb);
}
.st-grid__image {
  width: 100%;
  aspect-ratio: 16 / 9;
  object-fit: cover;
  display: block;
}
.st-grid__body {
  padding: var(--st-spacing-md, 1rem);
}
.st-grid__title {
  font-family: var(--st-typography-heading, system-ui, sans-serif);
  color: var(--st-color-text, #111827);
  font-size: 1.2rem;
  margin: 0 0 0.5rem;
}
.st-grid__excerpt {
  font-family: var(--st-typography-body, system-ui, sans-serif);
  color: var(--st-color-text-muted, #6b7280);
  margin: 0 0 0.75rem;
  line-height: 1.5;
}
.st-grid__meta {
  display: flex;
  gap: 0.75rem;
  font-size: 0.85rem;
  color: var(--st-color-text-muted, #6b7280);
}
</style>
