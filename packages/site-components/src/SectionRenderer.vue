<script setup lang="ts">
import type { Section } from '@sass-blog/site-schema'
import { computed } from 'vue'
import { componentFor, isDynamicType } from './registry'
import type { ResolvedSections } from './types'
import SectionShell from './SectionShell.vue'

const props = defineProps<{
  section: Section
  editable?: boolean
  resolved?: ResolvedSections
  linkBase?: string
}>()

const component = computed(() => componentFor(props.section.type))
const dynamic = computed(() => isDynamicType(props.section.type))

// Props extra SÓLO para componentes dinámicos: los estáticos (Hero/Text) no reciben
// resolvedData/linkBase, así no se filtran atributos al DOM (ADR-008).
const dynamicBindings = computed(() =>
  dynamic.value
    ? { resolvedData: props.resolved?.[props.section.id], linkBase: props.linkBase ?? '' }
    : {},
)
</script>

<template>
  <template v-if="section.visible">
    <SectionShell v-if="component" :settings="section.settings">
      <component :is="component" :variant="section.variant" :props-data="section.props" v-bind="dynamicBindings" />
    </SectionShell>
    <!-- Tipo desconocido: placeholder sólo en edición; en producción no renderiza NADA (nunca lanza). -->
    <div v-else-if="editable" class="st-unknown">Sección desconocida: {{ section.type }}</div>
  </template>
</template>

<style>
.st-unknown {
  padding: 1rem;
  border: 1px dashed var(--st-color-secondary, #9ca3af);
  color: var(--st-color-text-muted, #6b7280);
  font-family: system-ui, sans-serif;
}
</style>
