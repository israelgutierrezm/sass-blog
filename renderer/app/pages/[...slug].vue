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
  seo: {
    title: string
    description?: string | null
    canonical: string
    robots: string
    og_image?: string | null
    jsonld_type?: string | null
  }
  published_at: string | null
}

// El render puede devolver un redirect (SEO, ADR-018) en vez de una página.
interface RedirectPayload {
  redirect: { to: string; status: number }
}

type RenderResponse = RenderedPayload | RedirectPayload

const isRedirect = (p: RenderResponse): p is RedirectPayload => 'redirect' in p

const route = useRoute()
const config = useRuntimeConfig()

const segments = ([] as string[]).concat((route.params.slug as string[] | string | undefined) ?? [])

// SSR usa la base interna (server -> API); el cliente, la pública.
const base = import.meta.server ? config.apiInternalBase : config.public.apiBase

// Host actual: SSR desde el header; cliente desde location.
const host = (import.meta.server ? useRequestHeaders(['host']).host : window.location.host) ?? ''

// Resolución del sitio: por Host (dominio propio, ADR-020) o por prefijo _site/{ulid}.
// Dominio propio → rutas limpias en la raíz (linkBase ''); prefijo → linkBase /_site/{id}.
const { data: resolution } = await useAsyncData(`site:${host}:${segments.join('/')}`, async () => {
  if (isCustomHost(host, config.public.appHosts)) {
    const r = await $fetch<{ data: { site: string } }>(`${base}/public/domains/resolve`, { query: { host } }).catch(() => null)
    return r ? { siteId: r.data.site, path: pathFromSegments(segments), linkBase: '' } : null
  }
  const s = resolveSite(segments, config.public.reservedPrefix)
  return s.siteId ? { siteId: s.siteId, path: s.path, linkBase: `/${config.public.reservedPrefix}/${s.siteId}` } : null
})

if (!resolution.value) {
  throw createError({ statusCode: 404, statusMessage: 'Sitio no encontrado' })
}

const { siteId, path, linkBase } = resolution.value

const { data, error } = await useAsyncData(`render:${siteId}:${path}`, () =>
  $fetch<{ data: RenderResponse }>(`${base}/public/sites/${siteId}/render`, { query: { path } }),
)

if (error.value || !data.value) {
  // 404 real (nunca soft-200).
  throw createError({ statusCode: 404, statusMessage: 'Página no encontrada' })
}

const payload = data.value.data

// Redirect (ADR-018): el backend lo resolvió ANTES del 404; Nuxt emite el 301/302
// real hacia el destino dentro del mismo sitio. En SSR corta la respuesta aquí.
if (isRedirect(payload)) {
  await navigateTo(`${linkBase}${payload.redirect.to}`, {
    redirectCode: payload.redirect.status,
    replace: true,
  })
}

// Sólo hay render normal cuando NO es redirect (narrowed a RenderedPayload).
const content = isRedirect(payload) ? null : payload
const schema: PageSchema | null = content
  ? { schema_version: content.page.schema_version, sections: content.page.sections }
  : null
const resolved = content?.resolved

if (content) {
  const seoMeta = [
    { name: 'robots', content: content.seo.robots },
    { property: 'og:title', content: content.seo.title },
    { property: 'og:url', content: content.seo.canonical },
    ...(content.seo.description ? [{ name: 'description', content: content.seo.description }, { property: 'og:description', content: content.seo.description }] : []),
    ...(content.seo.og_image ? [{ property: 'og:image', content: content.seo.og_image }] : []),
  ]

  useHead({
    title: content.seo.title,
    link: [{ rel: 'canonical', href: content.seo.canonical }],
    meta: seoMeta,
  })
}

// Analítica (ADR-021): captura SERVER-SIDE y fire-and-forget. Sólo en SSR y sólo para un
// render real (no redirect/404). Reenvía IP/UA/referrer del VISITANTE (el backend deriva el
// visitor_hash sin almacenar la IP). No se hace await: no debe bloquear ni romper el render.
if (import.meta.server && content) {
  const reqHeaders = useRequestHeaders(['user-agent', 'x-forwarded-for', 'referer'])
  const xff = reqHeaders['x-forwarded-for'] ?? ''
  const socketIp = useRequestEvent()?.node?.req?.socket?.remoteAddress ?? ''
  const visitorIp = (xff.split(',')[0] || '').trim() || socketIp

  $fetch(`${base}/public/analytics/collect`, {
    method: 'POST',
    body: { site: siteId, path, referrer: reqHeaders.referer ?? null },
    headers: {
      'User-Agent': reqHeaders['user-agent'] ?? '',
      'X-Visitor-Ip': visitorIp,
    },
  }).catch(() => {})
}
</script>

<template>
  <PageRenderer v-if="schema" :schema="schema" :resolved="resolved" :link-base="linkBase" :site-id="siteId" :public-base="config.public.apiBase" />
</template>
