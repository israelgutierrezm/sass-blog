<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import { SITE_CONTEXT } from '../context'

/**
 * Formulario de captura de newsletter (ADR-022). El copy viene por `propsData`; el destino
 * (sitio + API pública) por el contexto inyectado por el renderer. El submit hace POST al
 * endpoint público `subscribe` (doble opt-in). Sin contexto público (preview del Builder) es
 * inerte: muestra el mensaje de éxito localmente sin postear.
 */
interface NewsletterProps {
  heading?: string
  description?: string
  buttonLabel?: string
  successMessage?: string
}

const props = defineProps<{ propsData?: NewsletterProps; variant?: string }>()

const ctx = inject(SITE_CONTEXT, {})
const email = ref('')
const state = ref<'idle' | 'sending' | 'done' | 'error'>('idle')

const canPost = computed(() => Boolean(ctx.siteId && ctx.publicBase))

async function submit(): Promise<void> {
  if (state.value === 'sending') {
    return
  }

  if (!canPost.value) {
    state.value = 'done' // preview: sin destino, muestra el éxito localmente
    return
  }

  state.value = 'sending'
  try {
    const res = await fetch(`${ctx.publicBase}/public/sites/${ctx.siteId}/newsletter/subscribe`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: email.value }),
    })
    state.value = res.ok ? 'done' : 'error'
  } catch {
    state.value = 'error'
  }
}
</script>

<template>
  <section class="st-newsletter">
    <h2 v-if="propsData?.heading" class="st-newsletter__heading">{{ propsData.heading }}</h2>
    <p v-if="propsData?.description" class="st-newsletter__desc">{{ propsData.description }}</p>

    <p v-if="state === 'done'" class="st-newsletter__success" data-testid="newsletter-success">
      {{ propsData?.successMessage ?? '¡Gracias!' }}
    </p>
    <form v-else class="st-newsletter__form" @submit.prevent="submit">
      <input
        v-model="email" type="email" required placeholder="tu@correo.com"
        data-testid="newsletter-email" class="st-newsletter__input"
      />
      <button type="submit" :disabled="state === 'sending'" data-testid="newsletter-submit" class="st-newsletter__button">
        {{ propsData?.buttonLabel ?? 'Suscribirme' }}
      </button>
      <p v-if="state === 'error'" class="st-newsletter__error">No se pudo completar. Inténtalo de nuevo.</p>
    </form>
  </section>
</template>

<style>
.st-newsletter {
  max-width: 32rem;
  margin: 0 auto;
  text-align: center;
}
.st-newsletter__heading {
  font-size: var(--st-font-size-xl, 1.5rem);
  font-weight: 700;
  color: var(--st-color-text, #111);
  margin: 0 0 0.5rem;
}
.st-newsletter__desc {
  color: var(--st-color-text-muted, #6b7280);
  margin: 0 0 1rem;
}
.st-newsletter__form {
  display: flex;
  gap: 0.5rem;
  justify-content: center;
  flex-wrap: wrap;
}
.st-newsletter__input {
  flex: 1 1 16rem;
  padding: 0.6rem 0.8rem;
  border: 1px solid var(--st-color-secondary, #d1d5db);
  border-radius: var(--st-radius-md, 0.375rem);
  font: inherit;
}
.st-newsletter__button {
  padding: 0.6rem 1.2rem;
  border: 0;
  border-radius: var(--st-radius-md, 0.375rem);
  background: var(--st-color-primary, #2563eb);
  color: #fff;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}
.st-newsletter__button:disabled {
  opacity: 0.5;
}
.st-newsletter__success {
  color: var(--st-color-primary, #2563eb);
  font-weight: 600;
}
.st-newsletter__error {
  flex-basis: 100%;
  color: #dc2626;
  font-size: 0.875rem;
}
</style>
