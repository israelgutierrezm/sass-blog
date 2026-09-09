<script setup lang="ts">
import type { MediaAssetDto } from '@sass-blog/shared-types'
import { onMounted, ref } from 'vue'
import { mediaApi } from '../services/api'
import { ApiError } from '../services/http'

const props = defineProps<{ ws: string; site: string }>()

const assets = ref<MediaAssetDto[]>([])
const loading = ref(true)
const uploading = ref(false)
const error = ref('')

async function refresh(): Promise<void> {
  assets.value = (await mediaApi.list(props.ws, props.site)).data
  loading.value = false
}

onMounted(refresh)

async function onFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) {
    return
  }
  error.value = ''
  uploading.value = true
  try {
    await mediaApi.upload(props.ws, props.site, file)
    await refresh()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first('file') : 'No se pudo subir'
  } finally {
    uploading.value = false
    input.value = ''
  }
}

async function saveAlt(asset: MediaAssetDto): Promise<void> {
  await mediaApi.update(props.ws, props.site, asset.id, { alt: asset.alt, title: asset.title })
}

async function remove(asset: MediaAssetDto): Promise<void> {
  await mediaApi.remove(props.ws, props.site, asset.id)
  await refresh()
}
</script>

<template>
  <div class="mx-auto max-w-4xl px-6 py-8">
    <div class="mb-6 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <RouterLink :to="{ name: 'collections', params: { ws, site } }" class="text-sm text-gray-400 hover:text-gray-700">Colecciones</RouterLink>
        <span class="text-gray-300">/</span>
        <h1 class="text-2xl font-semibold">Medios</h1>
      </div>
      <label class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
        <span>{{ uploading ? 'Subiendo…' : 'Subir archivo' }}</span>
        <input type="file" class="hidden" data-testid="media-upload" :disabled="uploading" @change="onFile" />
      </label>
    </div>
    <p v-if="error" class="mb-3 text-sm text-red-600">{{ error }}</p>

    <p v-if="loading" class="text-gray-500">Cargando…</p>
    <p v-else-if="assets.length === 0" class="text-gray-500">Aún no hay archivos.</p>
    <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3">
      <div v-for="asset in assets" :key="asset.id" class="rounded-lg border border-gray-200 bg-white p-2" :data-testid="`asset-${asset.id}`">
        <img
          v-if="asset.mime_type.startsWith('image/')"
          :src="asset.variants?.thumb ?? asset.url"
          :alt="asset.alt ?? asset.original_filename"
          class="mb-2 aspect-video w-full rounded object-cover"
        />
        <div v-else class="mb-2 flex aspect-video w-full items-center justify-center rounded bg-gray-100 text-xs text-gray-500">
          {{ asset.mime_type }}
        </div>
        <input
          v-model="asset.alt"
          placeholder="Texto alternativo"
          class="mb-1 w-full rounded border border-gray-200 px-2 py-1 text-sm"
          @blur="saveAlt(asset)"
        />
        <div class="flex items-center justify-between text-xs text-gray-400">
          <span class="truncate">{{ asset.original_filename }}</span>
          <button class="hover:text-red-600" :data-testid="`asset-del-${asset.id}`" @click="remove(asset)">Eliminar</button>
        </div>
      </div>
    </div>
  </div>
</template>
