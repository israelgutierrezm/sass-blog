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

/**
 * ¿El Host es un dominio PROPIO de un tenant (no el de la app/preview)? Si lo es, el sitio
 * se resuelve por Host (ADR-020) y las páginas se sirven a la raíz; si no, se usa el prefijo
 * `_site/{ulid}`. Función PURA.
 */
export function isCustomHost(host: string, appHosts: string[]): boolean {
  const h = host.toLowerCase()
  if (h === '') {
    return false
  }

  return !appHosts.map((a) => a.toLowerCase()).includes(h)
}

/**
 * Path público desde los segmentos de la ruta catch-all (modo dominio propio: TODA la ruta
 * es del sitio, sin prefijo). `[]` → `/`, `['a','b']` → `/a/b`. Función PURA.
 */
export function pathFromSegments(segments: string[]): string {
  const rest = segments.filter((segment) => segment.length > 0)

  return rest.length > 0 ? `/${rest.join('/')}` : '/'
}
