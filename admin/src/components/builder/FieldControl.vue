<script setup lang="ts">
import type { FieldDescriptor } from '@sass-blog/site-schema'

const props = defineProps<{ field: FieldDescriptor; modelValue: unknown }>()
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
