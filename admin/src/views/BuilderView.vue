<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import CanvasPreview from '../components/builder/CanvasPreview.vue'
import PropsPanel from '../components/builder/PropsPanel.vue'
import SectionList from '../components/builder/SectionList.vue'
import SeoPanel from '../components/builder/SeoPanel.vue'
import { ApiError } from '../services/http'
import { useBuilderStore } from '../stores/builder'

const props = defineProps<{ ws: string; site: string; page: string }>()
const builder = useBuilderStore()

const loading = ref(true)
const error = ref('')
const showSeo = ref(false)

const rendererBase = import.meta.env.VITE_RENDERER_BASE ?? 'http://localhost:3000'
const publicUrl = computed(() => {
  const path = builder.page?.path ?? '/'
  return `${rendererBase}/_site/${props.site}${path}`
})

onMounted(async () => {
  try {
    await builder.load(props.ws, props.site, props.page)
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo cargar la página'
  } finally {
    loading.value = false
  }
})

async function save(): Promise<void> {
  error.value = ''
  try {
    await builder.save()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo guardar'
  }
}

async function publish(): Promise<void> {
  error.value = ''
  try {
    await builder.publish()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo publicar'
  }
}

async function preview(): Promise<void> {
  const url = await builder.previewLink()
  window.open(url, '_blank')
}
</script>

<template>
  <div v-if="loading" class="p-8 text-gray-500">Cargando…</div>
  <div v-else class="flex h-[calc(100vh-49px)] flex-col">
    <div class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-2">
      <div class="flex items-center gap-3">
        <RouterLink :to="{ name: 'pages', params: { ws, site } }" class="text-sm text-gray-400 hover:text-gray-700">
          ← Páginas
        </RouterLink>
        <span class="font-medium">{{ builder.page?.title }}</span>
        <span data-testid="page-status" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs uppercase">{{ builder.status }}</span>
        <span v-if="builder.dirty" class="text-xs text-amber-600">sin guardar</span>
      </div>
      <div class="flex items-center gap-2">
        <a
          v-if="builder.status === 'published'"
          :href="publicUrl"
          target="_blank"
          data-testid="view-public"
          class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50"
        >Ver público</a>
        <button data-testid="seo" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50" @click="showSeo = true">
          SEO
        </button>
        <button data-testid="preview" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50" @click="preview">
          Vista previa
        </button>
        <button data-testid="save" :disabled="builder.saving" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50 disabled:opacity-50" @click="save">
          {{ builder.saving ? '…' : 'Guardar' }}
        </button>
        <button data-testid="publish" :disabled="builder.publishing" class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50" @click="publish">
          {{ builder.publishing ? '…' : 'Publicar' }}
        </button>
      </div>
    </div>

    <p v-if="error" data-testid="builder-error" class="bg-red-50 px-4 py-1.5 text-sm text-red-600">{{ error }}</p>

    <div class="flex flex-1 overflow-hidden">
      <SectionList />
      <CanvasPreview />
      <PropsPanel />
    </div>

    <SeoPanel v-if="showSeo" :ws="ws" :site="site" @close="showSeo = false" />
  </div>
</template>
