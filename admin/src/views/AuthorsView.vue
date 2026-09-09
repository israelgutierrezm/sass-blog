<script setup lang="ts">
import type { AuthorDto } from '@sass-blog/shared-types'
import { onMounted, ref } from 'vue'
import { authorsApi } from '../services/api'
import { ApiError } from '../services/http'

const props = defineProps<{ ws: string; site: string }>()

const items = ref<AuthorDto[]>([])
const loading = ref(true)
const name = ref('')
const error = ref('')

async function refresh(): Promise<void> {
  items.value = (await authorsApi.list(props.ws, props.site)).data
  loading.value = false
}

onMounted(refresh)

async function create(): Promise<void> {
  error.value = ''
  try {
    await authorsApi.create(props.ws, props.site, { name: name.value })
    name.value = ''
    await refresh()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'Error'
  }
}

async function rename(author: AuthorDto): Promise<void> {
  await authorsApi.update(props.ws, props.site, author.id, { name: author.name })
}

async function remove(author: AuthorDto): Promise<void> {
  await authorsApi.remove(props.ws, props.site, author.id)
  await refresh()
}
</script>

<template>
  <div class="mx-auto max-w-2xl px-6 py-8">
    <div class="mb-6 flex items-center gap-2">
      <RouterLink :to="{ name: 'collections', params: { ws, site } }" class="text-sm text-gray-400 hover:text-gray-700">
        Colecciones
      </RouterLink>
      <span class="text-gray-300">/</span>
      <h1 class="text-2xl font-semibold">Autores</h1>
    </div>

    <form class="mb-6 flex items-end gap-3 rounded-lg border border-gray-200 bg-white p-4" @submit.prevent="create">
      <label class="flex-1">
        <span class="mb-1 block text-sm text-gray-700">Nombre</span>
        <input v-model="name" type="text" required data-testid="author-name"
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
      </label>
      <button type="submit" data-testid="author-create"
        class="rounded-md bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700">Crear autor</button>
    </form>
    <p v-if="error" class="mb-3 text-sm text-red-600">{{ error }}</p>

    <p v-if="loading" class="text-gray-500">Cargando…</p>
    <ul v-else class="space-y-2">
      <li v-for="a in items" :key="a.id"
        class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2" :data-testid="`author-${a.slug}`">
        <input v-model="a.name" class="flex-1 rounded border border-transparent px-2 py-1 hover:border-gray-200 focus:border-blue-400"
          @blur="rename(a)" />
        <button class="text-sm text-gray-400 hover:text-red-600" :data-testid="`author-del-${a.slug}`" @click="remove(a)">Eliminar</button>
      </li>
    </ul>
  </div>
</template>
