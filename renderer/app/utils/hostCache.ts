/**
 * Caché en memoria host → ULID del sitio (ADR-020). Cada render en un dominio propio necesita
 * resolver el sitio por Host; sin caché, cada visita (y cada navegación en el cliente) haría una
 * llamada a `/public/domains/resolve`, cuyo cupo es por host (ADR-026): un sitio popular lo
 * agotaría y sus visitas verían 404. El backend ya declara la respuesta cacheable 60 s.
 *
 * Vive a nivel de módulo: en el servidor se comparte entre peticiones (el mapeo es público, no
 * depende del usuario) y en el navegador dura la sesión. Función PURA (el fetch se inyecta).
 */

/** Respuesta del fetcher: ULID, `null` (no es un dominio activo) o `undefined` (error transitorio). */
export type HostLookup = string | null | undefined

export const HOST_CACHE_TTL_MS = 60_000

/** Tope de entradas: los Host los elige el cliente; sin tope, Hosts aleatorios llenarían la memoria. */
export const HOST_CACHE_MAX = 5_000

const cache = new Map<string, { siteId: string | null; expires: number }>()

export async function resolveHostCached(
  host: string,
  fetcher: (host: string) => Promise<HostLookup>,
  now: number = Date.now(),
): Promise<string | null> {
  const key = host.toLowerCase()
  const hit = cache.get(key)
  if (hit && hit.expires > now) {
    return hit.siteId
  }

  const siteId = await fetcher(key)
  if (siteId === undefined) {
    // 429/5xx/red: no se cachea (si no, un fallo puntual daría 60 s de 404).
    return null
  }

  if (!cache.has(key) && cache.size >= HOST_CACHE_MAX) {
    const oldest = cache.keys().next().value
    if (oldest !== undefined) {
      cache.delete(oldest)
    }
  }
  cache.set(key, { siteId, expires: now + HOST_CACHE_TTL_MS })

  return siteId
}

/** Sólo para tests. */
export function clearHostCache(): void {
  cache.clear()
}
