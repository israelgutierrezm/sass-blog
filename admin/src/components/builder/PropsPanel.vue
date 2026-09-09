<script setup lang="ts">
import type { CategoryDto, CollectionDto } from '@sass-blog/shared-types'
import { type FieldDescriptor, getComponent } from '@sass-blog/site-schema'
import { computed, onMounted, ref, watch } from 'vue'
import { categoriesApi, collectionsApi } from '../../services/api'
import { useBuilderStore } from '../../stores/builder'
import FieldControl from './FieldControl.vue'

const builder = useBuilderStore()
const section = computed(() => builder.selected)
const component = computed(() => (section.value ? getComponent(section.value.type) : undefined))
const fields = computed(() => (component.value ? Object.entries(component.value.fields) : []))

// Fuentes de opciones para los controles dynamic-select.
const collections = ref<CollectionDto[]>([])
const categories = ref<CategoryDto[]>([])

const collectionOptions = computed(() => collections.value.map((c) => ({ value: c.handle, label: c.name })))
const categoryOptions = computed(() => categories.value.map((c) => ({ value: c.slug, label: c.name })))

function optionsFor(field: FieldDescriptor): { value: string; label: string }[] | undefined {
  if (field.control !== 'dynamic-select') {
    return undefined
  }
  return field.optionsSource === 'categories' ? categoryOptions.value : collectionOptions.value
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
  if (builder.ws && builder.site) {
    collections.value = (await collectionsApi.list(builder.ws, builder.site)).data
    // Ya con las colecciones cargadas, resolver las categorías de la elegida.
    await loadCategories((section.value?.props as Record<string, unknown> | undefined)?.collection)
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
        <FieldControl
          v-for="[key, field] in fields"
          :key="key"
          :field="field"
          :model-value="section.props[key]"
          :dynamic-options="optionsFor(field)"
          :data-testid="`field-${key}`"
          @update:model-value="updateField(key, $event)"
        />
      </div>
    </div>
  </aside>
</template>
