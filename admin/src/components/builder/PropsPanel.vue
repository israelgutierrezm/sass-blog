<script setup lang="ts">
import { getComponent } from '@sass-blog/site-schema'
import { computed } from 'vue'
import { useBuilderStore } from '../../stores/builder'
import FieldControl from './FieldControl.vue'

const builder = useBuilderStore()
const section = computed(() => builder.selected)
const component = computed(() => (section.value ? getComponent(section.value.type) : undefined))
const fields = computed(() => (component.value ? Object.entries(component.value.fields) : []))

function updateField(key: string, value: unknown): void {
  if (section.value) {
    builder.updateProps(section.value.id, { [key]: value })
  }
}
</script>

<template>
  <aside class="w-80 shrink-0 overflow-y-auto border-l border-gray-200 bg-white p-4">
    <p v-if="!section" class="text-sm text-gray-400">Selecciona una sección para editarla.</p>
    <div v-else>
      <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ component?.name }}</h2>
      <p class="mb-4 text-xs text-gray-400">{{ section.variant }}</p>
      <div class="space-y-4">
        <FieldControl
          v-for="[key, field] in fields"
          :key="key"
          :field="field"
          :model-value="section.props[key]"
          :data-testid="`field-${key}`"
          @update:model-value="updateField(key, $event)"
        />
      </div>
    </div>
  </aside>
</template>
