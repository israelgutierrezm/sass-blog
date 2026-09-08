<script setup lang="ts">
import { computed } from 'vue'

interface TextProps {
  heading?: string
  paragraphs?: string[]
  align?: 'left' | 'center'
}

const props = defineProps<{ variant: string; propsData: Record<string, unknown> }>()
const p = computed(() => props.propsData as unknown as TextProps)
</script>

<template>
  <div class="st-text" :style="{ textAlign: p.align ?? 'left' }">
    <h2 v-if="p.heading" class="st-text__heading">{{ p.heading }}</h2>
    <p v-for="(paragraph, i) in p.paragraphs ?? []" :key="i" class="st-text__p">{{ paragraph }}</p>
  </div>
</template>

<style>
.st-text__heading {
  font-family: var(--st-typography-heading, system-ui, sans-serif);
  color: var(--st-color-text, #111827);
}
.st-text__p {
  font-family: var(--st-typography-body, system-ui, sans-serif);
  color: var(--st-color-text, #111827);
  line-height: 1.7;
  margin: 0 0 1rem;
}
</style>
