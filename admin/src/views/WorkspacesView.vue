<script setup lang="ts">
import type { WorkspaceDto } from '@sass-blog/shared-types'
import { onMounted, ref } from 'vue'
import { workspacesApi } from '../services/api'

const items = ref<WorkspaceDto[]>([])
const loading = ref(true)

onMounted(async () => {
  items.value = (await workspacesApi.list()).data
  loading.value = false
})
</script>

<template>
  <div class="mx-auto max-w-3xl px-6 py-8">
    <h1 class="mb-6 text-2xl font-semibold">Tus espacios de trabajo</h1>
    <p v-if="loading" class="text-gray-500">Cargando…</p>
    <ul v-else class="space-y-2">
      <li v-for="w in items" :key="w.id">
        <RouterLink
          :to="{ name: 'sites', params: { ws: w.id } }"
          :data-testid="`ws-${w.slug}`"
          class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 hover:border-blue-300"
        >
          <span class="font-medium">{{ w.name }}</span>
          <span class="text-xs uppercase tracking-wide text-gray-400">{{ w.role }}</span>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>
