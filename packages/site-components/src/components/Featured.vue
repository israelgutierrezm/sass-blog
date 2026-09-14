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

interface FeaturedProps {
  heading?: string
  showExcerpt?: boolean
  showImage?: boolean
  showDate?: boolean
}

/**
 * Featured (Portada, ADR-024): DETERMINISTA. No consulta datos: recibe `resolvedData` (tarjetas
 * ya resueltas por el backend, en el orden curado) y `linkBase`. `featured-lead` destaca la
 * primera y lista el resto; `featured-list` es una lista numerada. Sin datos → placeholder.
 */
const props = defineProps<{
  variant: string
  propsData: Record<string, unknown>
  resolvedData?: { items: Card[]; total: number }
  linkBase?: string
}>()

const p = computed(() => props.propsData as unknown as FeaturedProps)
const items = computed<Card[]>(() => props.resolvedData?.items ?? [])
const lead = computed<Card | undefined>(() => items.value[0])
const rest = computed<Card[]>(() => items.value.slice(1))

function href(card: Card): string | undefined {
  return card.path ? (props.linkBase ?? '') + card.path : undefined
}
</script>

<template>
  <section class="st-featured" :class="`st-featured--${variant}`">
    <h2 v-if="p.heading" class="st-featured__heading">{{ p.heading }}</h2>
    <p v-if="items.length === 0" class="st-featured__empty">Aún no hay artículos destacados.</p>

    <template v-else-if="variant === 'featured-lead'">
      <a v-if="lead" class="st-featured__lead" :href="href(lead)">
        <img v-if="(p.showImage ?? true) && lead.image" class="st-featured__lead-image" :src="lead.image" :alt="lead.title" />
        <div class="st-featured__lead-body">
          <h3 class="st-featured__lead-title">{{ lead.title }}</h3>
          <p v-if="(p.showExcerpt ?? true) && lead.excerpt" class="st-featured__excerpt">{{ lead.excerpt }}</p>
        </div>
      </a>
      <div class="st-featured__secondary">
        <a v-for="card in rest" :key="card.id" class="st-featured__item" :href="href(card)">
          <h4 class="st-featured__item-title">{{ card.title }}</h4>
          <time v-if="(p.showDate ?? true) && card.date" class="st-featured__date" :datetime="card.date">{{ card.date }}</time>
        </a>
      </div>
    </template>

    <ol v-else class="st-featured__list">
      <li v-for="(card, i) in items" :key="card.id" class="st-featured__row">
        <span class="st-featured__rank">{{ i + 1 }}</span>
        <a class="st-featured__row-link" :href="href(card)">
          <h3 class="st-featured__row-title">{{ card.title }}</h3>
          <p v-if="(p.showExcerpt ?? false) && card.excerpt" class="st-featured__excerpt">{{ card.excerpt }}</p>
        </a>
      </li>
    </ol>
  </section>
</template>

<style>
.st-featured {
  font-family: var(--st-typography-body, system-ui, sans-serif);
}
.st-featured__heading {
  font-family: var(--st-typography-heading, system-ui, sans-serif);
  color: var(--st-color-text, #111827);
  font-size: 1.5rem;
  margin: 0 0 1rem;
}
.st-featured__empty {
  color: var(--st-color-text-muted, #6b7280);
}
.st-featured__lead,
.st-featured__item,
.st-featured__row-link {
  text-decoration: none;
  color: inherit;
  display: block;
}
.st-featured__lead-image {
  width: 100%;
  aspect-ratio: 16 / 9;
  object-fit: cover;
  border-radius: var(--st-radius-lg, 16px);
  display: block;
  margin-bottom: 0.75rem;
}
.st-featured__lead-title {
  font-family: var(--st-typography-heading, system-ui, sans-serif);
  color: var(--st-color-text, #111827);
  font-size: 1.75rem;
  margin: 0 0 0.5rem;
}
.st-featured__excerpt {
  color: var(--st-color-text-muted, #6b7280);
  margin: 0;
  line-height: 1.5;
}
.st-featured__secondary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
  gap: var(--st-spacing-md, 1rem);
  margin-top: var(--st-spacing-md, 1rem);
  border-top: 1px solid var(--st-color-border, #e5e7eb);
  padding-top: var(--st-spacing-md, 1rem);
}
.st-featured__item-title {
  font-size: 1rem;
  margin: 0 0 0.25rem;
  color: var(--st-color-text, #111827);
}
.st-featured__date {
  font-size: 0.8rem;
  color: var(--st-color-text-muted, #6b7280);
}
.st-featured__list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.st-featured__row {
  display: flex;
  gap: 0.75rem;
  padding: 0.6rem 0;
  border-bottom: 1px solid var(--st-color-border, #e5e7eb);
}
.st-featured__rank {
  font-weight: 700;
  color: var(--st-color-primary, #2563eb);
  min-width: 1.5rem;
}
.st-featured__row-title {
  font-size: 1.05rem;
  margin: 0;
  color: var(--st-color-text, #111827);
}
</style>
