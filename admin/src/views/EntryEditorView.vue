<script setup lang="ts">
import type { AuthorDto, CategoryDto, CollectionDto } from '@sass-blog/shared-types'
import { computed, onMounted, ref } from 'vue'
import EntryFieldControl from '../components/cms/EntryFieldControl.vue'
import { authorsApi, categoriesApi, collectionsApi, type EntryInput, entriesApi } from '../services/api'
import { ApiError } from '../services/http'

const props = defineProps<{ ws: string; site: string; collection: string; entry?: string }>()

const collectionModel = ref<CollectionDto | null>(null)
const authors = ref<AuthorDto[]>([])
const categories = ref<CategoryDto[]>([])

const entryId = ref<string | undefined>(props.entry)
const title = ref('')
const values = ref<Record<string, unknown>>({})
const authorId = ref('')
const categoryIds = ref<string[]>([])
const status = ref('draft')

const error = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
const saving = ref(false)
const publishing = ref(false)
const loaded = ref(false)

const fields = computed(() => collectionModel.value?.fields ?? [])

onMounted(async () => {
  collectionModel.value = (await collectionsApi.get(props.ws, props.site, props.collection)).data
  authors.value = (await authorsApi.list(props.ws, props.site)).data
  categories.value = (await categoriesApi.list(props.ws, props.site, props.collection)).data

  if (entryId.value) {
    const e = (await entriesApi.get(props.ws, props.site, props.collection, entryId.value)).data
    title.value = e.title
    values.value = { ...e.values }
    authorId.value = e.author?.id ?? ''
    categoryIds.value = e.categories.map((c) => c.id)
    status.value = e.status
  }
  loaded.value = true
})

function payload(): EntryInput {
  return {
    title: title.value,
    author: authorId.value || null,
    category_ids: categoryIds.value,
    values: values.value,
  }
}

async function save(): Promise<boolean> {
  error.value = ''
  fieldErrors.value = {}
  saving.value = true
  try {
    const res = entryId.value
      ? await entriesApi.update(props.ws, props.site, props.collection, entryId.value, payload())
      : await entriesApi.create(props.ws, props.site, props.collection, payload())
    entryId.value = res.data.id
    status.value = res.data.status
    return true
  } catch (err) {
    if (err instanceof ApiError) {
      error.value = err.first()
      fieldErrors.value = err.errors
    }
    return false
  } finally {
    saving.value = false
  }
}

async function publish(): Promise<void> {
  if (!(await save())) {
    return
  }
  publishing.value = true
  error.value = ''
  try {
    const res = await entriesApi.publish(props.ws, props.site, props.collection, entryId.value as string)
    status.value = res.data.status
  } catch (err) {
    if (err instanceof ApiError) {
      error.value = err.first('values') || err.first()
      fieldErrors.value = err.errors
    }
  } finally {
    publishing.value = false
  }
}

function toggleCategory(id: string, checked: boolean): void {
  const next = new Set(categoryIds.value)
  checked ? next.add(id) : next.delete(id)
  categoryIds.value = [...next]
}
</script>

<template>
  <div class="mx-auto max-w-2xl px-6 py-8">
    <div class="mb-6 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <RouterLink :to="{ name: 'entries', params: { ws, site, collection } }" class="text-sm text-gray-400 hover:text-gray-700">
          Entradas
        </RouterLink>
        <span class="text-gray-300">/</span>
        <h1 class="text-2xl font-semibold">{{ entryId ? 'Editar' : 'Nueva' }} entrada</h1>
      </div>
      <span
        class="rounded-full px-2 py-0.5 text-xs uppercase"
        :class="status === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100'"
        data-testid="entry-status"
      >{{ status }}</span>
    </div>

    <p v-if="!loaded" class="text-gray-500">Cargando…</p>

    <form v-else class="space-y-4" @submit.prevent="save">
      <label class="block">
        <span class="mb-1 block text-sm font-medium text-gray-700">Título</span>
        <input
          v-model="title"
          type="text"
          required
          data-testid="entry-title"
          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
      </label>

      <EntryFieldControl
        v-for="field in fields"
        :key="field.id"
        :field="field"
        :model-value="values[field.key]"
        @update:model-value="values[field.key] = $event"
      />

      <label class="block">
        <span class="mb-1 block text-sm font-medium text-gray-700">Autor</span>
        <select v-model="authorId" data-testid="entry-author" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
          <option value="">Sin autor</option>
          <option v-for="a in authors" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>
      </label>

      <div v-if="categories.length" class="block">
        <span class="mb-1 block text-sm font-medium text-gray-700">Categorías</span>
        <div class="flex flex-wrap gap-3" data-testid="entry-categories">
          <label v-for="c in categories" :key="c.id" class="flex items-center gap-1 text-sm">
            <input
              type="checkbox"
              class="h-4 w-4"
              :checked="categoryIds.includes(c.id)"
              @change="toggleCategory(c.id, ($event.target as HTMLInputElement).checked)"
            />
            {{ c.name }}
          </label>
        </div>
      </div>

      <p v-if="error" data-testid="entry-error" class="text-sm text-red-600">{{ error }}</p>

      <div class="flex gap-3 pt-2">
        <button
          type="submit"
          :disabled="saving"
          data-testid="entry-save"
          class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50 disabled:opacity-50"
        >
          Guardar borrador
        </button>
        <button
          type="button"
          :disabled="saving || publishing"
          data-testid="entry-publish"
          class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          @click="publish"
        >
          Publicar
        </button>
      </div>
    </form>
  </div>
</template>
