<script setup lang="ts">
import type { CollectionFieldDto } from '@sass-blog/shared-types'
import { computed, ref } from 'vue'
import MediaPickerModal from '../media/MediaPickerModal.vue'

const props = defineProps<{
  field: CollectionFieldDto
  modelValue: unknown
  // Contexto para el selector de librería (sólo campos media).
  ws?: string
  site?: string
}>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()

function set(value: unknown): void {
  emit('update:modelValue', value)
}

const inputClass = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500'

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

// Selector de librería (D3: inserta la URL del asset).
const hasLibrary = computed(() => Boolean(props.ws && props.site))
const showPicker = ref(false)

function onPick(url: string): void {
  if (props.field.type === 'media_array') {
    const current = Array.isArray(props.modelValue) ? props.modelValue : []
    set([...current, url])
  } else {
    set(url)
  }
  showPicker.value = false
}
</script>

<template>
  <label class="block">
    <span class="mb-1 flex items-center gap-1 text-sm font-medium text-gray-700">
      {{ field.label }}
      <span v-if="field.required" class="text-red-500">*</span>
    </span>

    <textarea
      v-if="field.type === 'textarea' || field.type === 'richtext'"
      :value="asText"
      rows="4"
      :class="inputClass"
      :data-testid="`field-${field.key}`"
      @input="set(($event.target as HTMLTextAreaElement).value)"
    />

    <input
      v-else-if="field.type === 'integer' || field.type === 'decimal' || field.type === 'money'"
      type="number"
      :step="field.type === 'integer' ? '1' : 'any'"
      :value="asText"
      :class="inputClass"
      :data-testid="`field-${field.key}`"
      @input="set(($event.target as HTMLInputElement).valueAsNumber)"
    />

    <input
      v-else-if="field.type === 'boolean'"
      type="checkbox"
      :checked="Boolean(modelValue)"
      class="h-4 w-4"
      :data-testid="`field-${field.key}`"
      @change="set(($event.target as HTMLInputElement).checked)"
    />

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

    <!-- media (una imagen/archivo): URL + selector de librería (D3). -->
    <div v-else-if="field.type === 'media'" class="space-y-1">
      <input
        type="url"
        :value="asText"
        :class="inputClass"
        :data-testid="`field-${field.key}`"
        @input="set(($event.target as HTMLInputElement).value)"
      />
      <button
        v-if="hasLibrary"
        type="button"
        class="text-xs text-blue-600 hover:underline"
        :data-testid="`pick-${field.key}`"
        @click="showPicker = true"
      >
        Elegir de la librería
      </button>
    </div>

    <!-- json / media_array: JSON crudo; media_array puede añadir desde la librería. -->
    <div v-else-if="field.type === 'json' || field.type === 'media_array'" class="space-y-1">
      <textarea
        :value="jsonText"
        rows="3"
        :class="`${inputClass} font-mono`"
        :data-testid="`field-${field.key}`"
        @input="setJson(($event.target as HTMLTextAreaElement).value)"
      />
      <button
        v-if="hasLibrary && field.type === 'media_array'"
        type="button"
        class="text-xs text-blue-600 hover:underline"
        :data-testid="`pick-${field.key}`"
        @click="showPicker = true"
      >
        Añadir de la librería
      </button>
    </div>

    <input
      v-else
      :type="field.type === 'email' ? 'email' : field.type === 'url' ? 'url' : 'text'"
      :value="asText"
      :placeholder="field.type === 'relation' ? 'ULID de la entrada relacionada' : undefined"
      :class="inputClass"
      :data-testid="`field-${field.key}`"
      @input="set(($event.target as HTMLInputElement).value)"
    />
  </label>

  <MediaPickerModal
    v-if="showPicker && ws && site"
    :ws="ws"
    :site="site"
    @select="onPick"
    @close="showPicker = false"
  />
</template>
