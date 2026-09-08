<script setup lang="ts">
import { computed } from 'vue'

interface Cta {
  label: string
  href: string
}
interface HeroProps {
  eyebrow?: string
  heading: string
  subheading?: string
  align?: 'left' | 'center'
  primaryCta?: Cta
  secondaryCta?: Cta
  image?: { src: string; alt: string }
}

const props = defineProps<{ variant: string; propsData: Record<string, unknown> }>()
const p = computed(() => props.propsData as unknown as HeroProps)
const isSplit = computed(() => props.variant === 'hero-split')
</script>

<template>
  <div class="st-hero" :class="[`st-hero--${variant}`, `st-hero--${p.align ?? 'center'}`]">
    <div class="st-hero__content">
      <p v-if="p.eyebrow" class="st-hero__eyebrow">{{ p.eyebrow }}</p>
      <h1 class="st-hero__heading">{{ p.heading }}</h1>
      <p v-if="p.subheading" class="st-hero__subheading">{{ p.subheading }}</p>
      <div v-if="p.primaryCta || p.secondaryCta" class="st-hero__actions">
        <a v-if="p.primaryCta" class="st-btn st-btn--primary" :href="p.primaryCta.href">{{ p.primaryCta.label }}</a>
        <a v-if="p.secondaryCta" class="st-btn st-btn--ghost" :href="p.secondaryCta.href">{{ p.secondaryCta.label }}</a>
      </div>
    </div>
    <div v-if="isSplit && p.image" class="st-hero__media">
      <img :src="p.image.src" :alt="p.image.alt" />
    </div>
  </div>
</template>

<style>
.st-hero {
  display: flex;
  gap: var(--st-spacing-lg, 2rem);
  align-items: center;
}
.st-hero--center {
  flex-direction: column;
  text-align: center;
}
.st-hero__content {
  flex: 1;
}
.st-hero__eyebrow {
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--st-color-accent, #f59e0b);
  font-weight: 600;
  margin: 0 0 0.5rem;
}
.st-hero__heading {
  font-family: var(--st-typography-heading, system-ui, sans-serif);
  color: var(--st-color-text, #111827);
  font-size: 2.75rem;
  line-height: 1.1;
  margin: 0;
}
.st-hero__subheading {
  color: var(--st-color-text-muted, #6b7280);
  font-size: 1.15rem;
  margin-top: 1rem;
}
.st-hero__actions {
  display: flex;
  gap: 0.75rem;
  margin-top: 1.5rem;
}
.st-hero--center .st-hero__actions {
  justify-content: center;
}
.st-btn {
  display: inline-block;
  padding: 0.7em 1.4em;
  border-radius: var(--st-radius-md, 8px);
  text-decoration: none;
  font-weight: 600;
}
.st-btn--primary {
  background: var(--st-color-primary, #2563eb);
  color: var(--st-color-on-primary, #ffffff);
}
.st-btn--ghost {
  border: 1px solid var(--st-color-secondary, #4b5563);
  color: var(--st-color-text, #111827);
}
.st-hero__media {
  flex: 1;
}
.st-hero__media img {
  max-width: 100%;
  border-radius: var(--st-radius-lg, 16px);
  display: block;
}
</style>
