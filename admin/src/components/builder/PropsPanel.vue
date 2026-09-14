<script setup lang="ts">
import type { CategoryDto, CollectionDto, MenuDto } from '@sass-blog/shared-types'
import { type FieldDescriptor, getComponent } from '@sass-blog/site-schema'
import { computed, onMounted, ref, watch } from 'vue'
import { categoriesApi, collectionsApi, menusApi } from '../../services/api'
import { useBuilderStore } from '../../stores/builder'
import EntryPickerControl from './EntryPickerControl.vue'
import FieldControl from './FieldControl.vue'

const builder = useBuilderStore()
const section = computed(() => builder.selected)
const component = computed(() => (section.value ? getComponent(section.value.type) : undefined))
const fields = computed(() => (component.value ? Object.entries(component.value.fields) : []))

// Fuentes de opciones para los controles dynamic-select.
const collections = ref<CollectionDto[]>([])
const categories = ref<CategoryDto[]>([])
const menus = ref<MenuDto[]>([])

const collectionOptions = computed(() => collections.value.map((c) => ({ value: c.handle, label: c.name })))

// El entry-picker (Portada) necesita el id de la colección elegida por su prop `collection`.
const pickerCollectionId = computed(() => {
  const handle = (section.value?.props as Record<string, unknown> | undefined)?.collection
  return collections.value.find((c) => c.handle === handle)?.id
})
const categoryOptions = computed(() => categories.value.map((c) => ({ value: c.slug, label: c.name })))
// El menú se referencia por handle (estable), como la colección.
const menuOptions = computed(() => menus.value.map((m) => ({ value: m.handle, label: m.name })))

function optionsFor(field: FieldDescriptor): { value: string; label: string }[] | undefined {
  if (field.control !== 'dynamic-select') {
    return undefined
  }
  if (field.optionsSource === 'categories') {
    return categoryOptions.value
  }
  if (field.optionsSource === 'menus') {
    return menuOptions.value
  }
  return collectionOptions.value
}

async function loadCategories(handle: unknown): Promise<void> {
  if (typeof handle !== 'string' || handle === '') {
    categories.value = []
    return
  }
  const collection = collections.value.find((c) => c.handle === handle)
  categories.value = collection ? (await categoriesApi.list(builder.ws, builder.site, collection.id)).data : []
}

onMounted(async () => {
  if (!builder.ws || !builder.site) {
    return
  }

  // Menús: core (todos los planes).
  try {
    menus.value = (await menusApi.list(builder.ws, builder.site)).data
  } catch {
    menus.value = []
  }

  // Colecciones/categorías: sólo con plan que las incluya. Best-effort para no romper
  // el panel (p.ej. una sección navigation en un plan sin cms.collections).
  try {
    collections.value = (await collectionsApi.list(builder.ws, builder.site)).data
    await loadCategories((section.value?.props as Record<string, unknown> | undefined)?.collection)
  } catch {
    collections.value = []
  }
})

// Las categorías dependen de la colección elegida por el grid; recargar al cambiarla.
watch(() => (section.value?.props as Record<string, unknown> | undefined)?.collection, loadCategories)

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
        <template v-for="[key, field] in fields" :key="key">
          <EntryPickerControl
            v-if="field.control === 'entry-picker'"
            :label="field.label"
            :model-value="section.props[key]"
            :ws="builder.ws"
            :site="builder.site"
            :collection-id="pickerCollectionId"
            :data-testid="`field-${key}`"
            @update:model-value="updateField(key, $event)"
          />
          <FieldControl
            v-else
            :field="field"
            :model-value="section.props[key]"
            :dynamic-options="optionsFor(field)"
            :data-testid="`field-${key}`"
            @update:model-value="updateField(key, $event)"
          />
        </template>
      </div>
    </div>
  </aside>
</template>
