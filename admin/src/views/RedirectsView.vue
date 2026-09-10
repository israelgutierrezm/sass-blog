<script setup lang="ts">
import type { RedirectDto } from '@sass-blog/shared-types'
import { onMounted, ref } from 'vue'
import { redirectsApi } from '../services/api'
import { ApiError } from '../services/http'

const props = defineProps<{ ws: string; site: string }>()

const items = ref<RedirectDto[]>([])
const loading = ref(true)
const creating = ref(false)
const error = ref('')

const fromPath = ref('')
const toPath = ref('')
const status = ref(301)

async function refresh(): Promise<void> {
  try {
    items.value = (await redirectsApi.list(props.ws, props.site)).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudieron cargar los redirects'
  } finally {
    loading.value = false
  }
}

onMounted(refresh)

async function create(): Promise<void> {
  error.value = ''
  creating.value = true
  try {
    await redirectsApi.create(props.ws, props.site, {
      from_path: fromPath.value,
      to_path: toPath.value,
      status: status.value,
    })
    fromPath.value = ''
    toPath.value = ''
    status.value = 301
    await refresh()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo crear el redirect'
  } finally {
    creating.value = false
  }
}

async function toggleActive(redirect: RedirectDto): Promise<void> {
  error.value = ''
  try {
    const { data } = await redirectsApi.update(props.ws, props.site, redirect.id, {
      is_active: !redirect.is_active,
    })
    Object.assign(redirect, data)
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo actualizar'
  }
}

async function remove(redirect: RedirectDto): Promise<void> {
  error.value = ''
  try {
    await redirectsApi.remove(props.ws, props.site, redirect.id)
    items.value = items.value.filter((r) => r.id !== redirect.id)
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo eliminar'
  }
}
</script>

<template>
  <div class="mx-auto max-w-3xl px-6 py-8">
    <div class="mb-6 flex items-center gap-2">
      <RouterLink :to="{ name: 'pages', params: { ws, site } }" class="text-sm text-gray-400 hover:text-gray-700">Páginas</RouterLink>
      <span class="text-gray-300">/</span>
      <h1 class="text-2xl font-semibold">Redirects</h1>
    </div>

    <form class="mb-6 flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4" @submit.prevent="create">
      <label class="flex-1">
        <span class="mb-1 block text-sm text-gray-700">Ruta de origen</span>
        <input v-model="fromPath" type="text" placeholder="/vieja-ruta" data-testid="redirect-from" required
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
      </label>
      <label class="flex-1">
        <span class="mb-1 block text-sm text-gray-700">Destino</span>
        <input v-model="toPath" type="text" placeholder="/nueva-ruta" data-testid="redirect-to" required
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
      </label>
      <label class="w-28">
        <span class="mb-1 block text-sm text-gray-700">Código</span>
        <select v-model.number="status" data-testid="redirect-status"
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500">
          <option :value="301">301</option>
          <option :value="302">302</option>
        </select>
      </label>
      <button type="submit" data-testid="redirect-create" :disabled="creating"
        class="rounded-md bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 disabled:opacity-50">
        Crear
      </button>
    </form>
    <p v-if="error" data-testid="redirect-error" class="mb-3 text-sm text-red-600">{{ error }}</p>

    <p v-if="loading" class="text-gray-500">Cargando…</p>
    <p v-else-if="items.length === 0" class="text-gray-500">Aún no hay redirects.</p>
    <table v-else class="w-full text-sm">
      <thead>
        <tr class="border-b border-gray-200 text-left text-xs uppercase text-gray-400">
          <th class="py-2">Origen → Destino</th>
          <th class="py-2 w-16">Código</th>
          <th class="py-2 w-24">Origen</th>
          <th class="py-2 w-20">Activo</th>
          <th class="py-2 w-16"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="r in items" :key="r.id" class="border-b border-gray-100" :data-testid="`redirect-${r.id}`">
          <td class="py-2">
            <span class="font-mono text-gray-700">{{ r.from_path }}</span>
            <span class="text-gray-400"> → </span>
            <span class="font-mono text-gray-700">{{ r.to_path }}</span>
          </td>
          <td class="py-2">{{ r.status }}</td>
          <td class="py-2">
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{{ r.source === 'slug_change' ? 'automático' : 'manual' }}</span>
          </td>
          <td class="py-2">
            <button
              type="button"
              :data-testid="`redirect-toggle-${r.id}`"
              class="rounded-full px-2 py-0.5 text-xs"
              :class="r.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
              @click="toggleActive(r)"
            >
              {{ r.is_active ? 'Sí' : 'No' }}
            </button>
          </td>
          <td class="py-2 text-right">
            <button class="text-xs text-gray-400 hover:text-red-600" :data-testid="`redirect-del-${r.id}`" @click="remove(r)">Eliminar</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
