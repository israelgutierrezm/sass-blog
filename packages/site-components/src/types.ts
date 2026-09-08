/** Sidecar `resolved`: datos por-sección (id de sección → tarjetas) para componentes
 * dinámicos como CollectionGrid (ADR-013). Lo produce el backend en render. */
export type ResolvedSections = Record<string, { items: Array<Record<string, unknown>>; total: number }>
