<script setup lang="ts">
import type { Section } from '@sass-blog/site-schema'
import { computed } from 'vue'
import { componentFor } from './registry'
import SectionShell from './SectionShell.vue'

const props = defineProps<{ section: Section; editable?: boolean }>()
const component = computed(() => componentFor(props.section.type))
</script>

<template>
  <template v-if="section.visible">
    <SectionShell v-if="component" :settings="section.settings">
      <component :is="component" :variant="section.variant" :props-data="section.props" />
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
