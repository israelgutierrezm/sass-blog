<script setup lang="ts">
import type { EntrySummaryDto } from '@sass-blog/shared-types'
import { computed, onMounted, ref, watch } from 'vue'
import { entriesApi } from '../../services/api'

/**
 * Control de curación de portadas (ADR-024): elige y ORDENA artículos publicados a mano.
 * `modelValue` es la lista de ULIDs en orden. Reordenar por drag-and-drop (HTML5 nativo).
 * Lista sólo artículos PUBLICADOS de la colección elegida (`collectionId`). Un elegido que ya
 * no está publicado se marca como no disponible (la portada lo omite) para poder quitarlo.
 */
const props = defineProps<{
  label: string
  modelValue: unknown
  ws: string
  site: string
  collectionId?: string
}>()
const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>()

const available = ref<EntrySummaryDto[]>([])
const loaded = ref(false)
const toAdd = ref('')
const dragIndex = ref<number | null>(null)

const items = computed<string[]>(() => (Array.isArray(props.modelValue) ? (props.modelValue as string[]) : []))
const addable = computed(() => available.value.filter((e) => !items.value.includes(e.id)))

/** Título del elegido; undefined si ya no está entre los publicados de la colección. */
function titleOf(id: string): string | undefined {
  return available.value.find((e) => e.id === id)?.title
}

async function load(): Promise<void> {
  loaded.value = false
  if (!props.collectionId) {
    available.value = []
    return
  }
  try {
    const all = (await entriesApi.list(props.ws, props.site, props.collectionId)).data
    available.value = all.filter((e) => e.status === 'published')
  } catch {
    available.value = []
  }
  loaded.value = true
}

onMounted(load)
watch(() => props.collectionId, load)

function add(): void {
  if (toAdd.value && !items.value.includes(toAdd.value)) {
    emit('update:modelValue', [...items.value, toAdd.value])
    toAdd.value = ''
  }
}

function remove(id: string): void {
  emit('update:modelValue', items.value.filter((x) => x !== id))
}

function onDragStart(event: DragEvent, index: number): void {
  dragIndex.value = index
  // Firefox no inicia el arrastre si el dragstart no fija datos.
  event.dataTransfer?.setData('text/plain', items.value[index] ?? '')
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
  }
}

function onDrop(target: number): void {
  if (dragIndex.value === null || dragIndex.value === target) {
    return
  }
  const next = [...items.value]
  const [moved] = next.splice(dragIndex.value, 1)
  dragIndex.value = null
  if (moved === undefined) {
    return
  }
  next.splice(target, 0, moved)
  emit('update:modelValue', next)
}
</script>

<template>
  <div class="block">
    <span class="mb-1 block text-sm text-gray-700">{{ label }}</span>

    <ul data-testid="entry-picker-list" class="mb-2 space-y-1">
      <li
        v-for="(id, i) in items"
        :key="id"
        draggable="true"
        :data-testid="`picked-${id}`"
        class="flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-sm"
        @dragstart="onDragStart($event, i)"
        @dragover.prevent
        @drop.prevent="onDrop(i)"
        @dragend="dragIndex = null"
      >
        <span class="cursor-move select-none text-gray-400">⋮⋮</span>
        <span v-if="titleOf(id)" class="flex-1 truncate">{{ titleOf(id) }}</span>
        <span v-else-if="loaded" class="flex-1 truncate italic text-amber-700" :title="id" :data-testid="`unavailable-${id}`">
          No disponible (ya no está publicado)
        </span>
        <span v-else class="flex-1 truncate text-gray-400">…</span>
        <button type="button" class="text-gray-400 hover:text-red-600" :data-testid="`remove-${id}`" @click="remove(id)">✕</button>
      </li>
    </ul>
    <p v-if="items.length === 0" class="mb-2 text-xs text-gray-400">Sin artículos destacados. Añade abajo.</p>

    <div class="flex gap-2">
      <select v-model="toAdd" data-testid="entry-picker-add-select" class="flex-1 rounded-md border border-gray-300 px-2 py-1 text-sm">
        <option value="">Elegir artículo…</option>
        <option v-for="e in addable" :key="e.id" :value="e.id">{{ e.title }}</option>
      </select>
      <button type="button" data-testid="entry-picker-add" class="rounded-md bg-blue-600 px-3 py-1 text-sm font-medium text-white hover:bg-blue-700" @click="add">
        Añadir
      </button>
    </div>
  </div>
</template>
