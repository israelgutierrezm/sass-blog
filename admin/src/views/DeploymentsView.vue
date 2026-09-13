<script setup lang="ts">
import type { DeploymentDto } from '@sass-blog/shared-types'
import { onMounted, onUnmounted, ref } from 'vue'
import { deploymentsApi } from '../services/api'
import { ApiError } from '../services/http'

const props = defineProps<{ ws: string; site: string }>()

const items = ref<DeploymentDto[]>([])
const loading = ref(true)
const exporting = ref(false)
const error = ref('')
let alive = true

const STATUS_LABEL: Record<string, string> = {
  pending: 'En cola', building: 'Construyendo', success: 'Listo', failed: 'Falló',
}

function pending(): boolean {
  return items.value.some((d) => d.status === 'pending' || d.status === 'building')
}

function humanBytes(bytes: number | null): string {
  if (!bytes) {
    return '—'
  }
  const kb = bytes / 1024
  return kb < 1024 ? `${kb.toFixed(0)} KB` : `${(kb / 1024).toFixed(1)} MB`
}

async function refresh(): Promise<void> {
  try {
    items.value = (await deploymentsApi.list(props.ws, props.site)).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudieron cargar los deployments'
  }
  // Sondeo: mientras haya builds en curso, refresca hasta que terminen.
  if (alive && pending()) {
    setTimeout(() => alive && refresh(), 3000)
  }
}

onMounted(async () => {
  await refresh()
  loading.value = false
})

onUnmounted(() => {
  alive = false
})

async function exportSite(): Promise<void> {
  error.value = ''
  exporting.value = true
  try {
    await deploymentsApi.create(props.ws, props.site)
    await refresh()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo exportar'
  } finally {
    exporting.value = false
  }
}

async function download(deployment: DeploymentDto): Promise<void> {
  error.value = ''
  try {
    const blob = await deploymentsApi.downloadBlob(props.ws, props.site, deployment.id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'sitio-estatico.zip'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo descargar'
  }
}
</script>

<template>
  <div class="mx-auto max-w-3xl px-6 py-8">
    <div class="mb-6 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <RouterLink :to="{ name: 'pages', params: { ws, site } }" class="text-sm text-gray-400 hover:text-gray-700">Páginas</RouterLink>
        <span class="text-gray-300">/</span>
        <h1 class="text-2xl font-semibold">Exportar sitio</h1>
      </div>
      <button
        data-testid="deploy-export"
        :disabled="exporting"
        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        @click="exportSite"
      >
        {{ exporting ? 'Exportando…' : 'Exportar sitio estático' }}
      </button>
    </div>
    <p class="mb-4 text-sm text-gray-500">Genera un ZIP estático (HTML + CSS + media + sitemap) de las páginas publicadas.</p>
    <p v-if="error" data-testid="deploy-error" class="mb-3 text-sm text-red-600">{{ error }}</p>

    <p v-if="loading" class="text-gray-500">Cargando…</p>
    <p v-else-if="items.length === 0" class="text-gray-500">Aún no hay exportaciones.</p>
    <table v-else class="w-full text-sm">
      <thead>
        <tr class="border-b border-gray-200 text-left text-xs uppercase text-gray-400">
          <th class="py-2">Estado</th>
          <th class="py-2 w-28">Tamaño</th>
          <th class="py-2 w-44">Fecha</th>
          <th class="py-2 w-24"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="d in items" :key="d.id" class="border-b border-gray-100" :data-testid="`deploy-${d.id}`">
          <td class="py-2">
            <span
              class="rounded-full px-2 py-0.5 text-xs"
              :class="{
                'bg-green-100 text-green-700': d.status === 'success',
                'bg-red-100 text-red-700': d.status === 'failed',
                'bg-amber-100 text-amber-700': d.status === 'pending' || d.status === 'building',
              }"
              :data-testid="`deploy-status-${d.id}`"
            >{{ STATUS_LABEL[d.status] ?? d.status }}</span>
            <span v-if="d.error" class="ml-2 text-xs text-red-500">{{ d.error }}</span>
          </td>
          <td class="py-2">{{ humanBytes(d.bytes) }}</td>
          <td class="py-2 text-gray-500">{{ d.created_at }}</td>
          <td class="py-2 text-right">
            <button
              v-if="d.status === 'success' && d.has_artifact"
              class="text-xs text-blue-600 hover:underline"
              :data-testid="`deploy-download-${d.id}`"
              @click="download(d)"
            >Descargar</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
