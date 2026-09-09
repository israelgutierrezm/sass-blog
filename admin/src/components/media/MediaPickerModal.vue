<script setup lang="ts">
import type { MediaAssetDto } from '@sass-blog/shared-types'
import { onMounted, ref } from 'vue'
import { mediaApi } from '../../services/api'
import { ApiError } from '../../services/http'

const props = defineProps<{ ws: string; site: string }>()
const emit = defineEmits<{ select: [url: string]; close: [] }>()

const assets = ref<MediaAssetDto[]>([])
const loading = ref(true)
const uploading = ref(false)
const error = ref('')

async function refresh(): Promise<void> {
  assets.value = (await mediaApi.list(props.ws, props.site, 1, 'image')).data
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

function pick(asset: MediaAssetDto): void {
  // El picker inserta la URL pública del asset (D3).
  emit('select', asset.variants?.medium ?? asset.url)
  emit('close')
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-6" @click.self="emit('close')">
    <div class="flex max-h-[80vh] w-full max-w-3xl flex-col rounded-lg bg-white shadow-xl" data-testid="media-picker">
      <header class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
        <h2 class="font-semibold">Librería de medios</h2>
        <button class="text-gray-400 hover:text-gray-700" data-testid="picker-close" @click="emit('close')">✕</button>
      </header>

      <div class="border-b border-gray-100 px-5 py-3">
        <label class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
          <span>{{ uploading ? 'Subiendo…' : 'Subir imagen' }}</span>
          <input type="file" accept="image/*" class="hidden" data-testid="picker-upload" :disabled="uploading" @change="onFile" />
        </label>
        <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
      </div>

      <div class="flex-1 overflow-y-auto p-5">
        <p v-if="loading" class="text-gray-500">Cargando…</p>
        <p v-else-if="assets.length === 0" class="text-gray-500">Aún no hay imágenes. Sube la primera.</p>
        <div v-else class="grid grid-cols-3 gap-3 sm:grid-cols-4">
          <button
            v-for="asset in assets"
            :key="asset.id"
            type="button"
            class="group overflow-hidden rounded-md border border-gray-200 hover:border-blue-400"
            :data-testid="`pick-${asset.id}`"
            @click="pick(asset)"
          >
            <img :src="asset.variants?.thumb ?? asset.url" :alt="asset.alt ?? asset.original_filename" class="aspect-square w-full object-cover" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
