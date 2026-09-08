<script setup lang="ts">
import { onMounted } from 'vue'
import { useEntryStore } from '../stores/entry'

const props = defineProps<{ ws: string; site: string; collection: string }>()
const entry = useEntryStore()

onMounted(() => entry.load(props.ws, props.site, props.collection))
</script>

<template>
  <div class="mx-auto max-w-3xl px-6 py-8">
    <div class="mb-6 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <RouterLink :to="{ name: 'collections', params: { ws, site } }" class="text-sm text-gray-400 hover:text-gray-700">
          Colecciones
        </RouterLink>
        <span class="text-gray-300">/</span>
        <h1 class="text-2xl font-semibold">Entradas</h1>
      </div>
      <RouterLink
        :to="{ name: 'entry-new', params: { ws, site, collection } }"
        data-testid="entry-new"
        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
      >
        Nueva entrada
      </RouterLink>
    </div>

    <p v-if="entry.loading" class="text-gray-500">Cargando…</p>
    <p v-else-if="entry.entries.length === 0" class="text-gray-500">Aún no hay entradas.</p>
    <ul v-else class="space-y-2">
      <li v-for="e in entry.entries" :key="e.id">
        <RouterLink
          :to="{ name: 'entry-edit', params: { ws, site, collection, entry: e.id } }"
          :data-testid="`entry-${e.slug}`"
          class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 hover:border-blue-300"
        >
          <span class="font-medium">{{ e.title }}</span>
          <span class="flex items-center gap-3 text-xs text-gray-400">
            <span>{{ e.path }}</span>
            <span
              class="rounded-full px-2 py-0.5 uppercase"
              :class="e.status === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100'"
            >{{ e.status }}</span>
          </span>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>
