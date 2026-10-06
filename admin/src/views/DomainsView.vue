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

// Qué corregir cuando la verificación falla (ADR-025).
const FAILURE_HINT: Record<string, string> = {
  ownership: 'No encontramos el registro TXT de verificación con el valor de abajo. Si acabas de crearlo, el DNS puede tardar en propagarse: vuelve a verificar en unos minutos.',
  routing: 'El registro TXT está bien, pero el dominio aún no apunta a nuestro servidor (CNAME o A).',
  taken: 'Este dominio ya está activo en otro sitio. Desconéctalo allí y vuelve a verificar.',
}

/** Sólo se refresca solo mientras el job está corriendo (pending se queda esperando al usuario). */
function verifying(): boolean {
  return items.value.some((d) => d.status === 'verifying')
}

async function refresh(): Promise<void> {
  try {
    items.value = (await domainsApi.list(props.ws, props.site)).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudieron cargar los dominios'
  }
  if (alive && verifying()) {
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

        <p v-if="d.status === 'failed' && d.failure_reason" class="mt-2 text-xs text-red-600" :data-testid="`domain-failure-${d.id}`">
          {{ FAILURE_HINT[d.failure_reason] }}
        </p>

        <!-- Registros DNS que prueban la propiedad (TXT) y enrutan el tráfico (CNAME/A). -->
        <div v-if="d.status !== 'active'" class="mt-3 rounded-md bg-gray-50 p-3 text-xs text-gray-600" :data-testid="`domain-dns-${d.id}`">
          <p class="mb-2">Publica estos registros en el DNS de tu dominio y pulsa <strong>Verificar</strong>:</p>
          <table class="w-full table-fixed">
            <thead class="text-left text-gray-400">
              <tr><th class="w-16 font-normal">Tipo</th><th class="font-normal">Nombre</th><th class="font-normal">Valor</th></tr>
            </thead>
            <tbody class="font-mono">
              <tr>
                <td>TXT</td>
                <td class="select-all break-all pr-2" :data-testid="`domain-txt-name-${d.id}`">{{ d.verification.txt_name }}</td>
                <td class="select-all break-all" :data-testid="`domain-txt-value-${d.id}`">{{ d.verification.txt_value }}</td>
              </tr>
              <tr>
                <td>CNAME</td>
                <td class="break-all pr-2">{{ d.hostname }}</td>
                <td class="select-all break-all">{{ d.verification.cname }}</td>
              </tr>
              <tr v-if="d.verification.ip">
                <td>A</td>
                <td class="break-all pr-2">{{ d.hostname }}</td>
                <td class="select-all break-all">{{ d.verification.ip }}</td>
              </tr>
            </tbody>
          </table>
          <p class="mt-2 text-gray-400">
            El TXT demuestra que el dominio es tuyo: mantenlo publicado. Usa el CNAME para un
            subdominio (blog.acme.com);
            <template v-if="d.verification.ip">para el dominio raíz (acme.com), el registro A.</template>
            <template v-else>para el dominio raíz (acme.com), un registro ALIAS/ANAME hacia {{ d.verification.cname }} si tu proveedor lo admite.</template>
            Si tu panel DNS añade tu dominio al final del nombre, escribe sólo la parte que va antes.
          </p>
        </div>

        <div class="mt-3 flex gap-4 text-xs">
          <button
            v-if="d.status !== 'active'"
            class="text-blue-600 hover:underline disabled:cursor-wait disabled:text-gray-400 disabled:no-underline"
            :disabled="d.status === 'verifying'"
            :data-testid="`domain-recheck-${d.id}`"
            @click="act(domainsApi.recheck(ws, site, d.id))"
          >{{ d.status === 'verifying' ? 'Verificando…' : 'Verificar' }}</button>
          <button v-if="!d.is_primary" class="text-blue-600 hover:underline" :data-testid="`domain-primary-${d.id}`" @click="act(domainsApi.setPrimary(ws, site, d.id))">Hacer primario</button>
          <button class="text-gray-400 hover:text-red-600" :data-testid="`domain-del-${d.id}`" @click="act(domainsApi.remove(ws, site, d.id))">Quitar</button>
        </div>
      </li>
    </ul>
  </div>
</template>
