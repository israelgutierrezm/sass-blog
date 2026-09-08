<script setup lang="ts">
import { type DeepPartial, type SiteTokens, tokensToCssVars } from '@sass-blog/design-tokens'
import type { PageSchema } from '@sass-blog/site-schema'
import { computed } from 'vue'
import SectionRenderer from './SectionRenderer.vue'
import type { ResolvedSections } from './types'

const props = defineProps<{
  schema: PageSchema
  editable?: boolean
  tokens?: DeepPartial<SiteTokens>
  resolved?: ResolvedSections
  /** Prefijo de ruta del sitio para los enlaces de los grids (p.ej. /_site/{ulid}). */
  linkBase?: string
}>()

// Override de tokens del sitio como estilo inline sobre .st-site-root (ADR-008).
const rootStyle = computed(() => (props.tokens ? tokensToCssVars(props.tokens) : undefined))
</script>

<template>
  <div class="st-site-root" :style="rootStyle">
    <SectionRenderer
      v-for="section in schema.sections"
      :key="section.id"
      :section="section"
      :editable="editable"
      :resolved="resolved"
      :link-base="linkBase"
    />
  </div>
</template>
