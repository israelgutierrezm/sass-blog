<script setup lang="ts">
import type { SiteDto } from '@sass-blog/shared-types'
import { onMounted, ref } from 'vue'
import { sitesApi } from '../services/api'
import { ApiError } from '../services/http'

const props = defineProps<{ ws: string }>()

const items = ref<SiteDto[]>([])
const loading = ref(true)
const name = ref('')
const slug = ref('')
const error = ref('')
const creating = ref(false)

async function refresh(): Promise<void> {
  items.value = (await sitesApi.list(props.ws)).data
  loading.value = false
}

onMounted(refresh)

async function create(): Promise<void> {
  error.value = ''
  creating.value = true
  try {
    await sitesApi.create(props.ws, name.value, slug.value)
    name.value = ''
    slug.value = ''
    await refresh()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'Error'
  } finally {
    creating.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-3xl px-6 py-8">
    <h1 class="mb-6 text-2xl font-semibold">Sitios</h1>

    <form class="mb-6 flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4" @submit.prevent="create">
      <label class="flex-1">
        <span class="mb-1 block text-sm text-gray-700">Nombre</span>
        <input v-model="name" type="text" data-testid="site-name" required
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
      </label>
      <label class="flex-1">
        <span class="mb-1 block text-sm text-gray-700">Slug</span>
        <input v-model="slug" type="text" data-testid="site-slug" required
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
      </label>
      <button type="submit" data-testid="site-create" :disabled="creating"
        class="rounded-md bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 disabled:opacity-50">
        Crear sitio
      </button>
    </form>
    <p v-if="error" class="mb-3 text-sm text-red-600">{{ error }}</p>

    <p v-if="loading" class="text-gray-500">Cargando…</p>
    <ul v-else class="space-y-2">
      <li v-for="s in items" :key="s.id">
        <RouterLink
          :to="{ name: 'pages', params: { ws, site: s.id } }"
          :data-testid="`site-${s.slug}`"
          class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 hover:border-blue-300"
        >
          <span class="font-medium">{{ s.name }}</span>
          <span class="text-xs text-gray-400">/{{ s.slug }}</span>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>
