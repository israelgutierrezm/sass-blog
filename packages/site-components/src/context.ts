import type { InjectionKey } from 'vue'

/**
 * Contexto público del sitio que el renderer inyecta en el árbol de componentes (ADR-022).
 * Lo consumen los componentes interactivos (p.ej. NewsletterForm) para saber a dónde postear.
 * Ausente en el preview del Builder → esos componentes quedan inertes.
 */
export interface SiteContext {
  /** ULID público del sitio. */
  siteId?: string
  /** Base de la API pública (cliente), p.ej. http://host/api/v1. */
  publicBase?: string
}

export const SITE_CONTEXT: InjectionKey<SiteContext> = Symbol('sassblog.site-context')
