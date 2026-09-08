export interface ResolvedSite {
  siteId: string | null
  path: string
}

/**
 * Resuelve el sitio y el path desde los segmentos de la ruta catch-all. En dev el
 * sitio va en el prefijo reservado: `/_site/{siteUlid}/{...path}` (ADR-006).
 *
 * Función PURA (sin dependencias de Nuxt) para poder probarla aislada.
 */
export function resolveSite(segments: string[], prefix: string): ResolvedSite {
  if (segments[0] !== prefix || !segments[1]) {
    return { siteId: null, path: '/' }
  }

  const rest = segments.slice(2).filter((segment) => segment.length > 0)

  return {
    siteId: segments[1],
    path: rest.length > 0 ? `/${rest.join('/')}` : '/',
  }
}
