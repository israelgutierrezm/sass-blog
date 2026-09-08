<script setup lang="ts">
import type { CollectionFieldDto } from '@sass-blog/shared-types'
import { computed } from 'vue'

const props = defineProps<{ field: CollectionFieldDto; modelValue: unknown }>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()

function set(value: unknown): void {
  emit('update:modelValue', value)
}

const inputClass = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500'

/** Opciones de select/multiselect: acepta strings o {value,label}. */
const options = computed(() => {
  const raw = (props.field.config?.options as unknown[]) ?? []
  return raw.map((o) =>
    typeof o === 'object' && o !== null
      ? { value: String((o as { value: unknown }).value), label: String((o as { label?: unknown }).label ?? (o as { value: unknown }).value) }
      : { value: String(o), label: String(o) },
  )
})

const asText = computed(() => (props.modelValue == null ? '' : String(props.modelValue)))
const selected = computed<string[]>(() => (Array.isArray(props.modelValue) ? props.modelValue.map(String) : []))

function toggleMulti(value: string, checked: boolean): void {
  const next = new Set(selected.value)
  checked ? next.add(value) : next.delete(value)
  set([...next])
}

/** json / media_array como texto crudo; intenta parsear JSON, si falla emite el texto. */
function setJson(text: string): void {
  try {
    set(JSON.parse(text))
  } catch {
    set(text)
  }
}

const jsonText = computed(() =>
  typeof props.modelValue === 'string' ? props.modelValue : JSON.stringify(props.modelValue ?? [], null, 2),
)
</script>

<template>
  <label class="block">
    <span class="mb-1 flex items-center gap-1 text-sm font-medium text-gray-700">
      {{ field.label }}
      <span v-if="field.required" class="text-red-500">*</span>
    </span>

    <!-- Texto multilínea: textarea / richtext (texto plano MVP). -->
    <textarea
      v-if="field.type === 'textarea' || field.type === 'richtext'"
      :value="asText"
      rows="4"
      :class="inputClass"
      :data-testid="`field-${field.key}`"
      @input="set(($event.target as HTMLTextAreaElement).value)"
    />

    <!-- Numéricos. -->
    <input
      v-else-if="field.type === 'integer' || field.type === 'decimal' || field.type === 'money'"
      type="number"
      :step="field.type === 'integer' ? '1' : 'any'"
      :value="asText"
      :class="inputClass"
      :data-testid="`field-${field.key}`"
      @input="set(($event.target as HTMLInputElement).valueAsNumber)"
    />

    <!-- Booleano. -->
    <input
      v-else-if="field.type === 'boolean'"
      type="checkbox"
      :checked="Boolean(modelValue)"
      class="h-4 w-4"
      :data-testid="`field-${field.key}`"
      @change="set(($event.target as HTMLInputElement).checked)"
    />

    <!-- Fechas. -->
    <input
      v-else-if="field.type === 'date'"
      type="date"
      :value="asText"
      :class="inputClass"
      :data-testid="`field-${field.key}`"
      @input="set(($event.target as HTMLInputElement).value)"
    />
    <input
      v-else-if="field.type === 'datetime'"
      type="datetime-local"
      :value="asText"
      :class="inputClass"
      :data-testid="`field-${field.key}`"
      @input="set(($event.target as HTMLInputElement).value)"
    />

    <!-- Select. -->
    <select
      v-else-if="field.type === 'select'"
      :value="asText"
      :class="inputClass"
      :data-testid="`field-${field.key}`"
      @change="set(($event.target as HTMLSelectElement).value)"
    >
      <option value="">—</option>
      <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
    </select>

    <!-- Multiselect. -->
    <div v-else-if="field.type === 'multiselect'" class="flex flex-wrap gap-3" :data-testid="`field-${field.key}`">
      <label v-for="o in options" :key="o.value" class="flex items-center gap-1 text-sm">
        <input
          type="checkbox"
          class="h-4 w-4"
          :checked="selected.includes(o.value)"
          @change="toggleMulti(o.value, ($event.target as HTMLInputElement).checked)"
        />
        {{ o.label }}
      </label>
    </div>

    <!-- json / media_array: JSON crudo (MVP). -->
    <textarea
      v-else-if="field.type === 'json' || field.type === 'media_array'"
      :value="jsonText"
      rows="3"
      :class="`${inputClass} font-mono`"
      :data-testid="`field-${field.key}`"
      @input="setJson(($event.target as HTMLTextAreaElement).value)"
    />

    <!-- text / slug / email / url / media / relation: input de texto. -->
    <input
      v-else
      :type="field.type === 'email' ? 'email' : field.type === 'url' || field.type === 'media' ? 'url' : 'text'"
      :value="asText"
      :placeholder="field.type === 'relation' ? 'ULID de la entrada relacionada' : undefined"
      :class="inputClass"
      :data-testid="`field-${field.key}`"
      @input="set(($event.target as HTMLInputElement).value)"
    />
  </label>
</template>
