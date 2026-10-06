import { resolveHostCached } from '../../app/utils/hostCache'
import { lookupHost } from '../../app/utils/lookupHost'
import { isCustomHost } from '../../app/utils/resolveSite'

type Event = Parameters<typeof getRequestHost>[0]
type SeoFile = 'sitemap.xml' | 'robots.txt'

const CONTENT_TYPE: Record<SeoFile, string> = {
  'sitemap.xml': 'application/xml; charset=UTF-8',
  'robots.txt': 'text/plain; charset=UTF-8',
}

/**
 * Sitio del dominio propio que hace la petición (ADR-020), o null si el Host es de la app o el
 * dominio no está activo. Mismo criterio que la resolución de páginas.
 */
export async function siteForCustomHost(event: Event): Promise<{ siteId: string; host: string } | null> {
  const config = useRuntimeConfig()
  const host = (getRequestHost(event) ?? '').toLowerCase()
  if (!isCustomHost(host, config.public.appHosts)) {
    return null
  }

  const siteId = await resolveHostCached(host, (h) => lookupHost(config.apiInternalBase, h))

  return siteId ? { siteId, host } : null
}

/**
 * Proxy del sitemap/robots del sitio al endpoint público del backend (ADR-018). Con `host`
 * (dominio propio) el backend usa ese dominio como base de las URLs si es un dominio activo
 * del sitio.
 */
export async function proxySeoFile(event: Event, siteId: string, file: SeoFile, host?: string): Promise<string> {
  const base = useRuntimeConfig().apiInternalBase

  try {
    const body = await $fetch<string>(`${base}/public/sites/${siteId}/${file}`, {
      responseType: 'text',
      query: host ? { host } : undefined,
    })

    setHeader(event, 'Content-Type', CONTENT_TYPE[file])
    setHeader(event, 'Cache-Control', 'public, max-age=3600')

    return body
  }
  catch {
    throw createError({ statusCode: 404, statusMessage: 'Sitio no encontrado' })
  }
}
