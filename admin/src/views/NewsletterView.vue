<script setup lang="ts">
import type { CampaignDto, SubscriberStatsDto } from '@sass-blog/shared-types'
import { onMounted, onUnmounted, ref } from 'vue'
import { newsletterApi } from '../services/api'
import { ApiError } from '../services/http'

const props = defineProps<{ ws: string; site: string }>()

const stats = ref<SubscriberStatsDto>({ pending: 0, confirmed: 0, unsubscribed: 0, total: 0 })
const campaigns = ref<CampaignDto[]>([])
const loading = ref(true)
const error = ref('')
const subject = ref('')
const body = ref('')
const creating = ref(false)
const sendingId = ref<string | null>(null)
let alive = true

const STATUS_LABEL: Record<string, string> = {
  draft: 'Borrador', sending: 'Enviando', sent: 'Enviada', failed: 'Interrumpida',
}

function sending(): boolean {
  return campaigns.value.some((c) => c.status === 'sending')
}

async function refresh(): Promise<void> {
  try {
    const [s, c] = await Promise.all([
      newsletterApi.subscriberStats(props.ws, props.site),
      newsletterApi.campaigns(props.ws, props.site),
    ])
    stats.value = s.data
    campaigns.value = c.data
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo cargar la newsletter'
  }
  // Mientras haya envíos en curso (cola sin worker en dev), refresca hasta terminar.
  if (alive && sending()) {
    setTimeout(() => alive && refresh(), 3000)
  }
}

onMounted(async () => {
  await refresh()
  loading.value = false
})

onUnmounted(() => {
  alive = false
})

async function create(): Promise<void> {
  error.value = ''
  creating.value = true
  try {
    await newsletterApi.createCampaign(props.ws, props.site, { subject: subject.value, body: body.value })
    subject.value = ''
    body.value = ''
    await refresh()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first('subject') : 'No se pudo crear la campaña'
  } finally {
    creating.value = false
  }
}

async function send(c: CampaignDto): Promise<void> {
  error.value = ''
  sendingId.value = c.id
  try {
    await newsletterApi.sendCampaign(props.ws, props.site, c.id)
    await refresh()
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'No se pudo enviar. El envío requiere plan Pro.'
  } finally {
    sendingId.value = null
  }
}
</script>

<template>
  <div class="mx-auto max-w-3xl px-6 py-8">
    <div class="mb-6 flex items-center gap-2">
      <RouterLink :to="{ name: 'pages', params: { ws, site } }" class="text-sm text-gray-400 hover:text-gray-700">Páginas</RouterLink>
      <span class="text-gray-300">/</span>
      <h1 class="text-2xl font-semibold">Newsletter</h1>
    </div>

    <p v-if="error" data-testid="newsletter-error" class="mb-3 text-sm text-red-600">{{ error }}</p>
    <p v-if="loading" class="text-gray-500">Cargando…</p>

    <template v-else>
      <!-- Suscriptores -->
      <div class="mb-8 rounded-lg border border-gray-200 bg-white p-4">
        <div class="flex items-baseline justify-between">
          <h2 class="text-sm font-medium text-gray-700">Suscriptores confirmados</h2>
          <span class="text-2xl font-semibold" data-testid="newsletter-subscribers-count">{{ stats.confirmed }}</span>
        </div>
        <p class="mt-1 text-xs text-gray-500">
          <span data-testid="newsletter-subscribers-total">{{ stats.total }}</span> en total:
          {{ stats.pending }} sin confirmar · {{ stats.unsubscribed }} bajas.
        </p>
      </div>

      <!-- Compositor -->
      <form class="mb-8 rounded-lg border border-gray-200 bg-white p-4" @submit.prevent="create">
        <h2 class="mb-3 text-sm font-medium text-gray-700">Nueva campaña</h2>
        <label class="mb-3 block">
          <span class="mb-1 block text-sm text-gray-700">Asunto</span>
          <input v-model="subject" type="text" required data-testid="campaign-subject"
            class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
        </label>
        <label class="mb-3 block">
          <span class="mb-1 block text-sm text-gray-700">Contenido (HTML)</span>
          <textarea v-model="body" rows="5" required data-testid="campaign-body"
            class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm outline-none focus:border-blue-500"></textarea>
        </label>
        <button type="submit" :disabled="creating" data-testid="campaign-create"
          class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
          {{ creating ? 'Guardando…' : 'Crear borrador' }}
        </button>
      </form>

      <!-- Campañas -->
      <h2 class="mb-2 text-sm font-medium text-gray-700">Campañas</h2>
      <p v-if="campaigns.length === 0" class="text-gray-500">Aún no hay campañas.</p>
      <ul v-else class="space-y-3">
        <li v-for="c in campaigns" :key="c.id" class="rounded-lg border border-gray-200 bg-white p-4" :data-testid="`campaign-${c.id}`">
          <div class="flex items-center justify-between">
            <span class="font-medium">{{ c.subject }}</span>
            <span
              class="rounded-full px-2 py-0.5 text-xs"
              :class="{
                'bg-green-100 text-green-700': c.status === 'sent',
                'bg-red-100 text-red-700': c.status === 'failed',
                'bg-amber-100 text-amber-700': c.status === 'sending',
                'bg-gray-100 text-gray-600': c.status === 'draft',
              }"
              :data-testid="`campaign-status-${c.id}`"
            >{{ STATUS_LABEL[c.status] ?? c.status }}</span>
          </div>
          <p class="mt-1 text-xs text-gray-500" :data-testid="`campaign-detail-${c.id}`">
            <template v-if="c.status === 'sent'">{{ c.sent_count }} enviados · {{ c.failed_count }} fallidos</template>
            <template v-else-if="c.status === 'sending'">Enviando a {{ c.recipients_count }} suscriptores…</template>
            <template v-else-if="c.status === 'failed'">
              El envío se interrumpió: {{ c.sent_count }} enviados · {{ c.failed_count }} fallidos.
              Al reanudarlo nadie recibe el correo dos veces.
            </template>
            <template v-else>Aún no se ha enviado.</template>
          </p>
          <div v-if="c.status === 'draft' || c.status === 'failed'" class="mt-3">
            <button
              class="text-sm text-blue-600 hover:underline disabled:cursor-not-allowed disabled:text-gray-400 disabled:no-underline"
              :disabled="sendingId !== null || stats.confirmed === 0"
              :title="stats.confirmed === 0 ? 'Aún no hay suscriptores confirmados' : undefined"
              :data-testid="`campaign-send-${c.id}`"
              @click="send(c)"
            >
              <template v-if="c.status === 'failed'">Reanudar envío</template>
              <template v-else>Enviar a {{ stats.confirmed }} suscriptores</template>
            </button>
          </div>
        </li>
      </ul>
    </template>
  </div>
</template>
