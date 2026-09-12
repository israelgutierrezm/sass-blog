<script setup lang="ts">
import type { MenuDto } from '@sass-blog/shared-types'
import { onMounted, ref } from 'vue'
import { menusApi } from '../services/api'
import { ApiError } from '../services/http'

const props = defineProps<{ ws: string; site: string }>()

const items = ref<MenuDto[]>([])
const loading = ref(true)
const creating = ref(false)
const error = ref('')

const handle = ref('')
const name = ref('')

async function refresh(): Promise<void> {
  try {
    items.value = (await menusApi.list(props.ws, props.site)).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudieron cargar los menús'
  } finally {
    loading.value = false
  }
}

onMounted(refresh)

async function create(): Promise<void> {
  error.value = ''
  creating.value = true
  try {
    await menusApi.create(props.ws, props.site, { handle: handle.value, name: name.value })
    handle.value = ''
    name.value = ''
    await refresh()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo crear el menú'
  } finally {
    creating.value = false
  }
}

async function remove(menu: MenuDto): Promise<void> {
  error.value = ''
  try {
    await menusApi.remove(props.ws, props.site, menu.id)
    items.value = items.value.filter((m) => m.id !== menu.id)
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
      <h1 class="text-2xl font-semibold">Menús</h1>
    </div>

    <form class="mb-6 flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4" @submit.prevent="create">
      <label class="w-40">
        <span class="mb-1 block text-sm text-gray-700">Identificador</span>
        <input v-model="handle" type="text" placeholder="primary" data-testid="menu-handle" required
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
      </label>
      <label class="flex-1">
        <span class="mb-1 block text-sm text-gray-700">Nombre</span>
        <input v-model="name" type="text" placeholder="Navegación principal" data-testid="menu-name" required
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
      </label>
      <button type="submit" data-testid="menu-create" :disabled="creating"
        class="rounded-md bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 disabled:opacity-50">
        Crear menú
      </button>
    </form>
    <p v-if="error" data-testid="menu-error" class="mb-3 text-sm text-red-600">{{ error }}</p>

    <p v-if="loading" class="text-gray-500">Cargando…</p>
    <p v-else-if="items.length === 0" class="text-gray-500">Aún no hay menús.</p>
    <ul v-else class="space-y-2">
      <li v-for="m in items" :key="m.id" class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3" :data-testid="`menu-${m.handle}`">
        <RouterLink :to="{ name: 'menu-edit', params: { ws, site, menu: m.id } }" class="flex-1">
          <span class="font-medium">{{ m.name }}</span>
          <span class="ml-2 text-xs text-gray-400">{{ m.handle }}</span>
        </RouterLink>
        <button class="text-xs text-gray-400 hover:text-red-600" :data-testid="`menu-del-${m.handle}`" @click="remove(m)">Eliminar</button>
      </li>
    </ul>
  </div>
</template>
