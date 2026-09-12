<script setup lang="ts">
import type { CollectionDto, MenuDto, MenuNodeDto, PageSummaryDto } from '@sass-blog/shared-types'
import { computed, onMounted, ref } from 'vue'
import { collectionsApi, type MenuItemInput, menusApi, pagesApi } from '../services/api'
import { ApiError } from '../services/http'

const props = defineProps<{ ws: string; site: string; menu: string }>()

const menu = ref<MenuDto | null>(null)
const pages = ref<PageSummaryDto[]>([])
const collections = ref<CollectionDto[]>([])
const loading = ref(true)
const error = ref('')

// Formulario de alta.
const label = ref('')
const linkType = ref('home')
const target = ref('')
const url = ref('')
const parentId = ref('')

interface FlatNode { node: MenuNodeDto; depth: number }

function flatten(nodes: MenuNodeDto[], depth = 0): FlatNode[] {
  return nodes.flatMap((n) => [{ node: n, depth }, ...flatten(n.children ?? [], depth + 1)])
}

const flat = computed<FlatNode[]>(() => flatten(menu.value?.items ?? []))

const targetOptions = computed(() => {
  if (linkType.value === 'page') {
    return pages.value.map((p) => ({ value: p.id, label: `${p.title} (${p.path})` }))
  }
  if (linkType.value === 'collection') {
    return collections.value.map((c) => ({ value: c.id, label: c.name }))
  }
  return []
})

const needsTarget = computed(() => linkType.value === 'page' || linkType.value === 'collection')

async function load(): Promise<void> {
  try {
    // Núcleo (core en todos los planes): el menú y las páginas del sitio.
    menu.value = (await menusApi.get(props.ws, props.site, props.menu)).data
    pages.value = (await pagesApi.list(props.ws, props.site)).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo cargar el menú'
  }

  // Colecciones: sólo con plan que las incluya (cms.collections). Best-effort: si el
  // plan no las tiene, el tipo "colección" simplemente queda sin opciones.
  try {
    collections.value = (await collectionsApi.list(props.ws, props.site)).data
  } catch {
    collections.value = []
  }

  loading.value = false
}

onMounted(load)

/** Nº de hermanos bajo el padre elegido (para anexar al final). */
function siblingCount(parent: string): number {
  if (!parent) {
    return menu.value?.items?.length ?? 0
  }
  return flat.value.find((f) => f.node.id === parent)?.node.children?.length ?? 0
}

async function addItem(): Promise<void> {
  error.value = ''
  const input: MenuItemInput = { label: label.value, link_type: linkType.value, position: siblingCount(parentId.value) }
  if (linkType.value === 'url') {
    input.url = url.value
  }
  if (needsTarget.value) {
    input.target = target.value
  }
  if (parentId.value) {
    input.parent = parentId.value
  }

  try {
    await menusApi.addItem(props.ws, props.site, props.menu, input)
    label.value = ''
    target.value = ''
    url.value = ''
    parentId.value = ''
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo añadir el elemento'
  }
}

async function removeItem(id: string): Promise<void> {
  error.value = ''
  try {
    await menusApi.removeItem(props.ws, props.site, props.menu, id)
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo eliminar'
  }
}

function describe(node: MenuNodeDto): string {
  if (node.link_type === 'url') {
    return node.url ?? ''
  }
  if (node.link_type === 'home') {
    return 'Inicio (/)'
  }
  return `${node.link_type}: ${node.target ?? ''}`
}
</script>

<template>
  <div class="mx-auto max-w-3xl px-6 py-8">
    <div class="mb-6 flex items-center gap-2">
      <RouterLink :to="{ name: 'menus', params: { ws, site } }" class="text-sm text-gray-400 hover:text-gray-700">Menús</RouterLink>
      <span class="text-gray-300">/</span>
      <h1 class="text-2xl font-semibold">{{ menu?.name ?? 'Menú' }}</h1>
    </div>

    <p v-if="loading" class="text-gray-500">Cargando…</p>
    <template v-else>
      <!-- Árbol actual -->
      <ul class="mb-6 rounded-lg border border-gray-200 bg-white" data-testid="menu-tree">
        <li v-if="flat.length === 0" class="px-4 py-3 text-sm text-gray-500">Aún no hay elementos.</li>
        <li v-for="{ node, depth } in flat" :key="node.id" class="flex items-center justify-between border-b border-gray-100 px-4 py-2 last:border-b-0" :data-testid="`item-${node.label}`">
          <span :style="{ paddingLeft: `${depth * 1.25}rem` }">
            <span class="font-medium">{{ node.label }}</span>
            <span class="ml-2 text-xs text-gray-400">{{ describe(node) }}</span>
          </span>
          <button class="text-xs text-gray-400 hover:text-red-600" :data-testid="`item-del-${node.label}`" @click="removeItem(node.id)">Eliminar</button>
        </li>
      </ul>

      <!-- Alta de elemento -->
      <form class="space-y-3 rounded-lg border border-gray-200 bg-white p-4" @submit.prevent="addItem">
        <h2 class="text-sm font-semibold text-gray-700">Añadir elemento</h2>
        <div class="flex flex-wrap gap-3">
          <label class="flex-1">
            <span class="mb-1 block text-xs text-gray-600">Etiqueta</span>
            <input v-model="label" type="text" required data-testid="item-label"
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500" />
          </label>
          <label class="w-40">
            <span class="mb-1 block text-xs text-gray-600">Tipo de enlace</span>
            <select v-model="linkType" data-testid="item-type"
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
              <option value="home">Inicio</option>
              <option value="url">URL</option>
              <option value="page">Página</option>
              <option value="collection">Colección</option>
            </select>
          </label>
        </div>

        <label v-if="linkType === 'url'" class="block">
          <span class="mb-1 block text-xs text-gray-600">URL</span>
          <input v-model="url" type="text" placeholder="/contacto o https://…" data-testid="item-url"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500" />
        </label>

        <label v-if="needsTarget" class="block">
          <span class="mb-1 block text-xs text-gray-600">Destino</span>
          <select v-model="target" data-testid="item-target"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
            <option value="">—</option>
            <option v-for="o in targetOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </label>

        <label class="block">
          <span class="mb-1 block text-xs text-gray-600">Dentro de (opcional)</span>
          <select v-model="parentId" data-testid="item-parent"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
            <option value="">— (raíz)</option>
            <option v-for="{ node, depth } in flat" :key="node.id" :value="node.id">{{ '— '.repeat(depth) }}{{ node.label }}</option>
          </select>
        </label>

        <button type="submit" data-testid="item-add"
          class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
          Añadir
        </button>
      </form>
      <p v-if="error" data-testid="menu-editor-error" class="mt-3 text-sm text-red-600">{{ error }}</p>
    </template>
  </div>
</template>
