<script setup lang="ts">
import type { AnalyticsSummaryDto } from '@sass-blog/shared-types'
import { onMounted, ref } from 'vue'
import { analyticsApi } from '../services/api'
import { ApiError } from '../services/http'

const props = defineProps<{ ws: string; site: string }>()

const data = ref<AnalyticsSummaryDto | null>(null)
const loading = ref(true)
const error = ref('')
const rangeDays = ref(30)

/** Rango [hoy-(días-1), hoy] en fechas UTC (YYYY-MM-DD), como el rollup del backend. */
function rangeParams(days: number): { from: string; to: string } {
  const to = new Date()
  const from = new Date()
  from.setUTCDate(from.getUTCDate() - (days - 1))
  const fmt = (d: Date) => d.toISOString().slice(0, 10)
  return { from: fmt(from), to: fmt(to) }
}

async function load(days: number): Promise<void> {
  rangeDays.value = days
  error.value = ''
  loading.value = true
  try {
    data.value = (await analyticsApi.summary(props.ws, props.site, rangeParams(days))).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo cargar la analítica'
  } finally {
    loading.value = false
  }
}

function maxViews(): number {
  return Math.max(1, ...(data.value?.series ?? []).map((p) => p.views))
}

async function exportCsv(): Promise<void> {
  error.value = ''
  try {
    const blob = await analyticsApi.exportBlob(props.ws, props.site, rangeParams(rangeDays.value))
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'analitica.csv'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo exportar'
  }
}

onMounted(() => load(30))
</script>

<template>
  <div class="mx-auto max-w-3xl px-6 py-8">
    <div class="mb-6 flex items-center gap-2">
      <RouterLink :to="{ name: 'pages', params: { ws, site } }" class="text-sm text-gray-400 hover:text-gray-700">Páginas</RouterLink>
      <span class="text-gray-300">/</span>
      <h1 class="text-2xl font-semibold">Analítica</h1>
    </div>

    <div class="mb-6 flex items-center gap-2">
      <button
        v-for="d in [7, 30]" :key="d" :data-testid="`analytics-range-${d}`"
        class="rounded-md px-3 py-1 text-sm"
        :class="rangeDays === d ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
        @click="load(d)"
      >{{ d }} días</button>
      <button
        v-if="data?.advanced" data-testid="analytics-range-90"
        class="rounded-md px-3 py-1 text-sm"
        :class="rangeDays === 90 ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
        @click="load(90)"
      >90 días</button>
      <span class="flex-1"></span>
      <button
        v-if="data?.advanced" data-testid="analytics-export"
        class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-50"
        @click="exportCsv"
      >Exportar CSV</button>
    </div>

    <p v-if="error" data-testid="analytics-error" class="mb-3 text-sm text-red-600">{{ error }}</p>
    <p v-if="loading" class="text-gray-500">Cargando…</p>

    <template v-else-if="data">
      <div class="mb-8 grid grid-cols-2 gap-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
          <div class="text-xs uppercase text-gray-400">Visitas</div>
          <div class="text-3xl font-semibold" data-testid="analytics-total-views">{{ data.totals.views }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
          <div class="text-xs uppercase text-gray-400">Visitantes</div>
          <div class="text-3xl font-semibold" data-testid="analytics-total-visitors">{{ data.totals.visitors }}</div>
        </div>
      </div>

      <div class="mb-8">
        <h2 class="mb-2 text-sm font-medium text-gray-700">Visitas por día</h2>
        <div v-if="data.series.length" class="flex h-32 items-end gap-1" data-testid="analytics-series">
          <div
            v-for="p in data.series" :key="p.date"
            class="min-h-[2px] flex-1 rounded-t bg-blue-500"
            :style="{ height: `${Math.round((p.views / maxViews()) * 100)}%` }"
            :title="`${p.date}: ${p.views} visitas`"
          ></div>
        </div>
        <p v-else class="text-sm text-gray-500">Sin datos en el rango.</p>
      </div>

      <div class="mb-8">
        <h2 class="mb-2 text-sm font-medium text-gray-700">Páginas más vistas</h2>
        <ul v-if="data.top_pages.length" data-testid="analytics-top-pages" class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
          <li v-for="pg in data.top_pages" :key="pg.path" class="flex items-center justify-between px-4 py-2 text-sm">
            <span class="font-mono text-gray-700">{{ pg.path }}</span>
            <span class="text-gray-500">{{ pg.views }} visitas</span>
          </li>
        </ul>
        <p v-else class="text-sm text-gray-500">Aún no hay visitas registradas.</p>
      </div>

      <div>
        <h2 class="mb-2 text-sm font-medium text-gray-700">Referrers</h2>
        <ul v-if="data.top_referrers && data.top_referrers.length" class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
          <li v-for="r in data.top_referrers" :key="r.referrer" class="flex items-center justify-between px-4 py-2 text-sm">
            <span class="text-gray-700">{{ r.referrer }}</span>
            <span class="text-gray-500">{{ r.views }}</span>
          </li>
        </ul>
        <p v-else-if="data.top_referrers === null" class="text-sm text-gray-500">Los referrers están disponibles en el plan Pro.</p>
        <p v-else class="text-sm text-gray-500">Sin referrers en el rango.</p>
      </div>
    </template>
  </div>
</template>
