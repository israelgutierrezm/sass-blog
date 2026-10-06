import type { HostLookup } from './hostCache'

/**
 * Consulta al backend qué sitio sirve un dominio propio (`/public/domains/resolve`, ADR-020).
 * Distingue «no es un dominio activo» (404 → null, cacheable) de un fallo transitorio
 * (429/5xx/red → undefined, no cacheable). Úsese a través de `resolveHostCached`.
 */
export async function lookupHost(apiBase: string, host: string): Promise<HostLookup> {
  try {
    const res = await $fetch<{ data: { site: string } }>(`${apiBase}/public/domains/resolve`, { query: { host } })

    return res.data.site
  }
  catch (error) {
    return (error as { statusCode?: number }).statusCode === 404 ? null : undefined
  }
}
