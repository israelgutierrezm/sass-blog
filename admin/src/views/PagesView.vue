<script setup lang="ts">
import type { PageSummaryDto } from '@sass-blog/shared-types'
import { onMounted, ref } from 'vue'
import { pagesApi } from '../services/api'
import { ApiError } from '../services/http'

const props = defineProps<{ ws: string; site: string }>()

const items = ref<PageSummaryDto[]>([])
const loading = ref(true)
const title = ref('')
const path = ref('/')
const error = ref('')
const creating = ref(false)

async function refresh(): Promise<void> {
  items.value = (await pagesApi.list(props.ws, props.site)).data
  loading.value = false
}

onMounted(refresh)

async function create(): Promise<void> {
  error.value = ''
  creating.value = true
  try {
    await pagesApi.create(props.ws, props.site, title.value, path.value)
    title.value = ''
    path.value = '/'
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
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Páginas</h1>
      <div class="flex items-center gap-4">
        <RouterLink
          :to="{ name: 'analytics', params: { ws, site } }"
          data-testid="nav-analytics"
          class="text-sm text-blue-600 hover:underline"
        >
          Analítica
        </RouterLink>
        <RouterLink
          :to="{ name: 'newsletter', params: { ws, site } }"
          data-testid="nav-newsletter"
          class="text-sm text-blue-600 hover:underline"
        >
          Newsletter
        </RouterLink>
        <RouterLink
          :to="{ name: 'domains', params: { ws, site } }"
          data-testid="nav-domains"
          class="text-sm text-blue-600 hover:underline"
        >
          Dominios
        </RouterLink>
        <RouterLink
          :to="{ name: 'deployments', params: { ws, site } }"
          data-testid="nav-deployments"
          class="text-sm text-blue-600 hover:underline"
        >
          Exportar
        </RouterLink>
        <RouterLink
          :to="{ name: 'menus', params: { ws, site } }"
          data-testid="nav-menus"
          class="text-sm text-blue-600 hover:underline"
        >
          Menús
        </RouterLink>
        <RouterLink
          :to="{ name: 'redirects', params: { ws, site } }"
          data-testid="nav-redirects"
          class="text-sm text-blue-600 hover:underline"
        >
          Redirects
        </RouterLink>
        <RouterLink
          :to="{ name: 'collections', params: { ws, site } }"
          data-testid="nav-collections"
          class="text-sm text-blue-600 hover:underline"
        >
          Colecciones →
        </RouterLink>
      </div>
    </div>

    <form class="mb-6 flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4" @submit.prevent="create">
      <label class="flex-1">
        <span class="mb-1 block text-sm text-gray-700">Título</span>
        <input v-model="title" type="text" data-testid="page-title" required
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
      </label>
      <label class="w-40">
        <span class="mb-1 block text-sm text-gray-700">Ruta</span>
        <input v-model="path" type="text" data-testid="page-path" required
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
      </label>
      <button type="submit" data-testid="page-create" :disabled="creating"
        class="rounded-md bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 disabled:opacity-50">
        Crear página
      </button>
    </form>
    <p v-if="error" class="mb-3 text-sm text-red-600">{{ error }}</p>

    <p v-if="loading" class="text-gray-500">Cargando…</p>
    <ul v-else class="space-y-2">
      <li v-for="p in items" :key="p.id">
        <RouterLink
          :to="{ name: 'builder', params: { ws, site, page: p.id } }"
          :data-testid="`page-${p.path}`"
          class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 hover:border-blue-300"
        >
          <span class="font-medium">{{ p.title }}</span>
          <span class="flex items-center gap-3 text-xs text-gray-400">
            <span>{{ p.path }}</span>
            <span class="rounded-full bg-gray-100 px-2 py-0.5 uppercase">{{ p.status }}</span>
          </span>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>
