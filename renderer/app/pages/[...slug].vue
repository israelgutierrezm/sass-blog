<script setup lang="ts">
import { PageRenderer } from '@sass-blog/site-components'
import type { PageSchema } from '@sass-blog/site-schema'

interface RenderedPayload {
  site: { id: string; name: string }
  page: {
    id: string
    path: string
    version_id: string
    schema_version: number
    sections: PageSchema['sections']
  }
  seo: { title: string; canonical: string; robots: string }
  published_at: string | null
}

const route = useRoute()
const config = useRuntimeConfig()

const segments = ([] as string[]).concat((route.params.slug as string[] | string | undefined) ?? [])
const { siteId, path } = resolveSite(segments, config.public.reservedPrefix)

if (!siteId) {
  throw createError({ statusCode: 404, statusMessage: 'Sitio no encontrado' })
}

// SSR usa la base interna (server -> API); el cliente, la pública.
const base = import.meta.server ? config.apiInternalBase : config.public.apiBase

const { data, error } = await useAsyncData(`render:${siteId}:${path}`, () =>
  $fetch<{ data: RenderedPayload }>(`${base}/public/sites/${siteId}/render`, { query: { path } }),
)

if (error.value || !data.value) {
  // 404 real (nunca soft-200).
  throw createError({ statusCode: 404, statusMessage: 'Página no encontrada' })
}

const payload = data.value.data
const schema: PageSchema = {
  schema_version: payload.page.schema_version,
  sections: payload.page.sections,
}

useHead({
  title: payload.seo.title,
  link: [{ rel: 'canonical', href: payload.seo.canonical }],
  meta: [{ name: 'robots', content: payload.seo.robots }],
})
</script>

<template>
  <PageRenderer :schema="schema" />
</template>
