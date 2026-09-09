<script setup lang="ts">
import { PageRenderer, type ResolvedSections } from '@sass-blog/site-components'
import type { PageSchema } from '@sass-blog/site-schema'

interface RenderedPayload {
  site: { id: string; name: string }
  page: {
    id: string
    path: string
    kind?: string
    version_id: string
    schema_version: number
    sections: PageSchema['sections']
  }
  // Sidecar de secciones dinámicas (CollectionGrid). Vacío = {} (ADR-013).
  resolved?: ResolvedSections
  // Presente sólo en el detalle dinámico de colección (artículo).
  entry?: { id: string; title: string; slug: string; collection: string }
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

// Prefijo de sitio para los enlaces de los grids: /_site/{ulid} + card.path.
const linkBase = `/${config.public.reservedPrefix}/${siteId}`

useHead({
  title: payload.seo.title,
  link: [{ rel: 'canonical', href: payload.seo.canonical }],
  meta: [{ name: 'robots', content: payload.seo.robots }],
})
</script>

<template>
  <PageRenderer :schema="schema" :resolved="payload.resolved" :link-base="linkBase" />
</template>
