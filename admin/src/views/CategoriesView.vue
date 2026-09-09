<script setup lang="ts">
import type { CategoryDto } from '@sass-blog/shared-types'
import { onMounted, ref } from 'vue'
import { categoriesApi } from '../services/api'
import { ApiError } from '../services/http'

const props = defineProps<{ ws: string; site: string; collection: string }>()

const items = ref<CategoryDto[]>([])
const loading = ref(true)
const name = ref('')
const error = ref('')

async function refresh(): Promise<void> {
  items.value = (await categoriesApi.list(props.ws, props.site, props.collection)).data
  loading.value = false
}

onMounted(refresh)

async function create(): Promise<void> {
  error.value = ''
  try {
    await categoriesApi.create(props.ws, props.site, props.collection, { name: name.value })
    name.value = ''
    await refresh()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'Error'
  }
}

async function rename(category: CategoryDto): Promise<void> {
  await categoriesApi.update(props.ws, props.site, props.collection, category.id, { name: category.name })
}

async function remove(category: CategoryDto): Promise<void> {
  await categoriesApi.remove(props.ws, props.site, props.collection, category.id)
  await refresh()
}
</script>

<template>
  <div class="mx-auto max-w-2xl px-6 py-8">
    <div class="mb-6 flex items-center gap-2">
      <RouterLink :to="{ name: 'entries', params: { ws, site, collection } }" class="text-sm text-gray-400 hover:text-gray-700">
        Entradas
      </RouterLink>
      <span class="text-gray-300">/</span>
      <h1 class="text-2xl font-semibold">Categorías</h1>
    </div>

    <form class="mb-6 flex items-end gap-3 rounded-lg border border-gray-200 bg-white p-4" @submit.prevent="create">
      <label class="flex-1">
        <span class="mb-1 block text-sm text-gray-700">Nombre</span>
        <input v-model="name" type="text" required data-testid="category-name"
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
      </label>
      <button type="submit" data-testid="category-create"
        class="rounded-md bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700">Crear categoría</button>
    </form>
    <p v-if="error" class="mb-3 text-sm text-red-600">{{ error }}</p>

    <p v-if="loading" class="text-gray-500">Cargando…</p>
    <ul v-else class="space-y-2">
      <li v-for="c in items" :key="c.id"
        class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2" :data-testid="`category-${c.slug}`">
        <input v-model="c.name" class="flex-1 rounded border border-transparent px-2 py-1 hover:border-gray-200 focus:border-blue-400"
          @blur="rename(c)" />
        <button class="text-sm text-gray-400 hover:text-red-600" :data-testid="`category-del-${c.slug}`" @click="remove(c)">Eliminar</button>
      </li>
    </ul>
  </div>
</template>
