<script setup lang="ts">
import type { FieldDescriptor } from '@sass-blog/site-schema'

const props = defineProps<{
  field: FieldDescriptor
  modelValue: unknown
  // Opciones resueltas por API para el control dynamic-select (las provee PropsPanel).
  dynamicOptions?: { value: string; label: string }[]
}>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()

function set(value: unknown): void {
  emit('update:modelValue', value)
}

const inputClass = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500'
</script>

<template>
  <label class="block">
    <span class="mb-1 block text-sm text-gray-700">{{ field.label }}</span>

    <textarea
      v-if="field.control === 'textarea'"
      :value="(modelValue as string) ?? ''"
      rows="3"
      :class="inputClass"
      @input="set((($event.target as HTMLTextAreaElement).value))"
    />

    <input
      v-else-if="field.control === 'number'"
      type="number"
      :value="modelValue as number"
      :class="inputClass"
      @input="set((($event.target as HTMLInputElement).valueAsNumber))"
    />

    <select
      v-else-if="field.control === 'select'"
      :value="(modelValue as string) ?? ''"
      :class="inputClass"
      @change="set((($event.target as HTMLSelectElement).value))"
    >
      <option v-for="option in field.options ?? []" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>

    <!-- dynamic-select: opciones cargadas por API (colecciones / categorías). -->
    <select
      v-else-if="field.control === 'dynamic-select'"
      :value="(modelValue as string) ?? ''"
      :class="inputClass"
      @change="set((($event.target as HTMLSelectElement).value))"
    >
      <option value="">—</option>
      <option v-for="option in dynamicOptions ?? []" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>

    <input
      v-else-if="field.control === 'boolean'"
      type="checkbox"
      :checked="Boolean(modelValue)"
      class="h-4 w-4"
      @change="set((($event.target as HTMLInputElement).checked))"
    />

    <textarea
      v-else-if="field.control === 'string-list'"
      :value="((modelValue as string[]) ?? []).join('\n')"
      rows="4"
      :class="inputClass"
      @input="set((($event.target as HTMLTextAreaElement).value).split('\n'))"
    />

    <input
      v-else
      type="text"
      :value="(modelValue as string) ?? ''"
      :class="inputClass"
      @input="set((($event.target as HTMLInputElement).value))"
    />
  </label>
</template>
