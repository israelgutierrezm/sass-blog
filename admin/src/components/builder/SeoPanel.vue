<script setup lang="ts">
import type { PageSeo } from '@sass-blog/shared-types'
import { robotsValues } from '@sass-blog/site-schema'
import { reactive, ref } from 'vue'
import { useBuilderStore } from '../../stores/builder'
import MediaPickerModal from '../media/MediaPickerModal.vue'

const props = defineProps<{ ws: string; site: string }>()
const emit = defineEmits<{ close: [] }>()

const builder = useBuilderStore()
const current = builder.schema.seo ?? {}

// Copia local; los <select> usan '' para "heredar" (sin override).
const form = reactive({
  meta_title: current.meta_title ?? '',
  meta_description: current.meta_description ?? '',
  canonical: current.canonical ?? '',
  robots: current.robots ?? '',
  og_image: current.og_image ?? '',
  jsonld_type: current.jsonld_type ?? '',
})

const showPicker = ref(false)

/** Sólo incluye los campos con valor: los vacíos se omiten (aditivo-opcional, strict). */
function apply(): void {
  const seo: PageSeo = {}
  if (form.meta_title.trim()) {
    seo.meta_title = form.meta_title.trim()
  }
  if (form.meta_description.trim()) {
    seo.meta_description = form.meta_description.trim()
  }
  if (form.canonical.trim()) {
    seo.canonical = form.canonical.trim()
  }
  if (form.robots) {
    seo.robots = form.robots as PageSeo['robots']
  }
  if (form.og_image.trim()) {
    seo.og_image = form.og_image.trim()
  }
  if (form.jsonld_type) {
    seo.jsonld_type = form.jsonld_type as PageSeo['jsonld_type']
  }

  builder.setSeo(Object.keys(seo).length > 0 ? seo : undefined)
  emit('close')
}
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-6" @click.self="emit('close')">
    <div class="flex max-h-[85vh] w-full max-w-lg flex-col overflow-y-auto rounded-lg bg-white shadow-xl" data-testid="seo-panel">
      <header class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
        <h2 class="font-semibold">SEO de la página</h2>
        <button class="text-gray-400 hover:text-gray-700" data-testid="seo-close" @click="emit('close')">✕</button>
      </header>

      <div class="space-y-4 p-5">
        <label class="block">
          <span class="mb-1 block text-sm text-gray-700">Meta título</span>
          <input v-model="form.meta_title" type="text" maxlength="200" data-testid="seo-meta-title"
            placeholder="Se usa el título de la página si se deja vacío"
            class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
        </label>

        <label class="block">
          <span class="mb-1 block text-sm text-gray-700">Meta descripción</span>
          <textarea v-model="form.meta_description" rows="3" maxlength="500" data-testid="seo-meta-description"
            class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500"></textarea>
        </label>

        <label class="block">
          <span class="mb-1 block text-sm text-gray-700">URL canónica</span>
          <input v-model="form.canonical" type="url" data-testid="seo-canonical"
            placeholder="https://… (se deriva de la ruta si se deja vacío)"
            class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
        </label>

        <label class="block">
          <span class="mb-1 block text-sm text-gray-700">Robots</span>
          <select v-model="form.robots" data-testid="seo-robots"
            class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500">
            <option value="">Predeterminado (según publicación)</option>
            <option v-for="value in robotsValues" :key="value" :value="value">{{ value }}</option>
          </select>
        </label>

        <div class="block">
          <span class="mb-1 block text-sm text-gray-700">Imagen social (og:image)</span>
          <div class="flex items-center gap-2">
            <input v-model="form.og_image" type="url" data-testid="seo-og-image"
              placeholder="https://…"
              class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
            <button type="button" data-testid="seo-pick-image"
              class="shrink-0 rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50"
              @click="showPicker = true">Elegir</button>
          </div>
          <img v-if="form.og_image" :src="form.og_image" alt="" class="mt-2 h-24 rounded object-cover" />
        </div>

        <label class="block">
          <span class="mb-1 block text-sm text-gray-700">Tipo JSON-LD</span>
          <select v-model="form.jsonld_type" data-testid="seo-jsonld"
            class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500">
            <option value="">Ninguno</option>
            <option value="WebPage">WebPage</option>
            <option value="Article">Article</option>
          </select>
        </label>
      </div>

      <footer class="flex justify-end gap-2 border-t border-gray-200 px-5 py-3">
        <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50" @click="emit('close')">Cancelar</button>
        <button type="button" data-testid="seo-apply"
          class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" @click="apply">
          Aplicar
        </button>
      </footer>
    </div>

    <MediaPickerModal
      v-if="showPicker"
      :ws="ws"
      :site="site"
      @select="(url) => (form.og_image = url)"
      @close="showPicker = false"
    />
  </div>
</template>
