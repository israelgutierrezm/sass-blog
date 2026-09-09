<script setup lang="ts">
import { onMounted } from 'vue'
import { useContentStore } from '../stores/content'

const props = defineProps<{ ws: string; site: string }>()
const content = useContentStore()

onMounted(() => content.loadCollections(props.ws, props.site))
</script>

<template>
  <div class="mx-auto max-w-3xl px-6 py-8">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Colecciones</h1>
      <div class="flex items-center gap-4 text-sm">
        <RouterLink :to="{ name: 'media', params: { ws, site } }" data-testid="nav-media" class="text-blue-600 hover:underline">
          Medios
        </RouterLink>
        <RouterLink :to="{ name: 'authors', params: { ws, site } }" data-testid="nav-authors" class="text-blue-600 hover:underline">
          Autores
        </RouterLink>
        <RouterLink :to="{ name: 'pages', params: { ws, site } }" class="text-blue-600 hover:underline">
          Páginas →
        </RouterLink>
      </div>
    </div>

    <p v-if="content.loading" class="text-gray-500">Cargando…</p>
    <ul v-else class="space-y-2">
      <li v-for="c in content.collections" :key="c.id">
        <RouterLink
          :to="{ name: 'entries', params: { ws, site, collection: c.id } }"
          :data-testid="`collection-${c.handle}`"
          class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 hover:border-blue-300"
        >
          <span class="font-medium">{{ c.name }}</span>
          <span class="flex items-center gap-3 text-xs text-gray-400">
            <span>{{ c.fields?.length ?? 0 }} campos</span>
            <span class="rounded-full bg-gray-100 px-2 py-0.5 uppercase">{{ c.kind }}</span>
          </span>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>
