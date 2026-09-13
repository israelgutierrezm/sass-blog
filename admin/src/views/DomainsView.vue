<script setup lang="ts">
import type { DomainDto } from '@sass-blog/shared-types'
import { onMounted, onUnmounted, ref } from 'vue'
import { domainsApi } from '../services/api'
import { ApiError } from '../services/http'

const props = defineProps<{ ws: string; site: string }>()

const items = ref<DomainDto[]>([])
const loading = ref(true)
const connecting = ref(false)
const error = ref('')
const hostname = ref('')
let alive = true

const STATUS_LABEL: Record<string, string> = {
  pending: 'Pendiente', verifying: 'Verificando', active: 'Activo', failed: 'Falló',
}

function pending(): boolean {
  return items.value.some((d) => d.status === 'pending' || d.status === 'verifying')
}

/** Un dominio de 2 etiquetas (acme.com) es apex → A/ALIAS; si no, subdominio → CNAME. */
function isApex(host: string): boolean {
  return host.split('.').length === 2
}

async function refresh(): Promise<void> {
  try {
    items.value = (await domainsApi.list(props.ws, props.site)).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudieron cargar los dominios'
  }
  if (alive && pending()) {
    setTimeout(() => alive && refresh(), 4000)
  }
}

onMounted(async () => {
  await refresh()
  loading.value = false
})

onUnmounted(() => {
  alive = false
})

async function connect(): Promise<void> {
  error.value = ''
  connecting.value = true
  try {
    await domainsApi.create(props.ws, props.site, hostname.value)
    hostname.value = ''
    await refresh()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first('hostname') : 'No se pudo conectar el dominio'
  } finally {
    connecting.value = false
  }
}

async function act(fn: Promise<unknown>): Promise<void> {
  error.value = ''
  try {
    await fn
    await refresh()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'Acción fallida'
  }
}
</script>

<template>
  <div class="mx-auto max-w-3xl px-6 py-8">
    <div class="mb-6 flex items-center gap-2">
      <RouterLink :to="{ name: 'pages', params: { ws, site } }" class="text-sm text-gray-400 hover:text-gray-700">Páginas</RouterLink>
      <span class="text-gray-300">/</span>
      <h1 class="text-2xl font-semibold">Dominios</h1>
    </div>

    <form class="mb-6 flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4" @submit.prevent="connect">
      <label class="flex-1">
        <span class="mb-1 block text-sm text-gray-700">Dominio</span>
        <input v-model="hostname" type="text" placeholder="blog.acme.com" data-testid="domain-hostname" required
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
      </label>
      <button type="submit" data-testid="domain-connect" :disabled="connecting"
        class="rounded-md bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 disabled:opacity-50">
        Conectar dominio
      </button>
    </form>
    <p v-if="error" data-testid="domain-error" class="mb-3 text-sm text-red-600">{{ error }}</p>

    <p v-if="loading" class="text-gray-500">Cargando…</p>
    <p v-else-if="items.length === 0" class="text-gray-500">Aún no hay dominios conectados.</p>
    <ul v-else class="space-y-3">
      <li v-for="d in items" :key="d.id" class="rounded-lg border border-gray-200 bg-white p-4" :data-testid="`domain-${d.id}`">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="font-mono font-medium">{{ d.hostname }}</span>
            <span v-if="d.is_primary" class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">Primario</span>
          </div>
          <div class="flex items-center gap-2">
            <span
              class="rounded-full px-2 py-0.5 text-xs"
              :class="{
                'bg-green-100 text-green-700': d.status === 'active',
                'bg-red-100 text-red-700': d.status === 'failed',
                'bg-amber-100 text-amber-700': d.status === 'pending' || d.status === 'verifying',
              }"
              :data-testid="`domain-status-${d.id}`"
            >{{ STATUS_LABEL[d.status] ?? d.status }}</span>
            <span class="text-xs text-gray-400">SSL: {{ d.ssl_status }}</span>
          </div>
        </div>

        <!-- Instrucciones de apuntado DNS mientras no esté activo. -->
        <p v-if="d.status !== 'active'" class="mt-2 text-xs text-gray-500">
          <template v-if="isApex(d.hostname) && d.verification.ip">
            Añade un registro <strong>A</strong> de <span class="font-mono">{{ d.hostname }}</span> → <span class="font-mono">{{ d.verification.ip }}</span>
          </template>
          <template v-else>
            Añade un <strong>CNAME</strong> de <span class="font-mono">{{ d.hostname }}</span> → <span class="font-mono">{{ d.verification.cname }}</span>
          </template>
        </p>

        <div class="mt-3 flex gap-4 text-xs">
          <button class="text-blue-600 hover:underline" :data-testid="`domain-recheck-${d.id}`" @click="act(domainsApi.recheck(ws, site, d.id))">Re-verificar</button>
          <button v-if="!d.is_primary" class="text-blue-600 hover:underline" :data-testid="`domain-primary-${d.id}`" @click="act(domainsApi.setPrimary(ws, site, d.id))">Hacer primario</button>
          <button class="text-gray-400 hover:text-red-600" :data-testid="`domain-del-${d.id}`" @click="act(domainsApi.remove(ws, site, d.id))">Quitar</button>
        </div>
      </li>
    </ul>
  </div>
</template>
