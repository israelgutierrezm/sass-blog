<script setup lang="ts">
import { computed } from 'vue'

interface Spacing {
  top?: string
  bottom?: string
}
interface Settings {
  spacing?: Spacing
  background?: string
  container?: string
}

const props = defineProps<{ settings: Record<string, unknown> }>()
const s = computed(() => props.settings as Settings)

const BACKGROUND: Record<string, string> = {
  transparent: 'transparent',
  surface: 'var(--st-color-surface, #ffffff)',
  'surface-muted': 'var(--st-color-surface-muted, #f3f4f6)',
  primary: 'var(--st-color-primary, #2563eb)',
  dark: 'var(--st-color-dark, #0b1220)',
}

const style = computed(() => {
  const st = s.value
  return {
    background: st.background ? BACKGROUND[st.background] : undefined,
    paddingTop: st.spacing?.top ? `var(--st-spacing-${st.spacing.top}, 0)` : undefined,
    paddingBottom: st.spacing?.bottom ? `var(--st-spacing-${st.spacing.bottom}, 0)` : undefined,
  }
})

const maxWidth = computed(() => {
  const container = s.value.container ?? 'default'
  if (container === 'full') {
    return '100%'
  }
  return container === 'wide' ? 'var(--st-container-wide, 90rem)' : 'var(--st-container-default, 72rem)'
})
</script>

<template>
  <section class="st-section" :style="style">
    <div class="st-section__inner" :style="{ maxWidth }">
      <slot />
    </div>
  </section>
</template>

<style>
.st-section {
  width: 100%;
}
.st-section__inner {
  margin: 0 auto;
  padding-left: 1rem;
  padding-right: 1rem;
}
</style>
