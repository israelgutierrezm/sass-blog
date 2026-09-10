# ADR-018 — SEO en el page schema; redirects y slug-history unificados

- Estado: Aceptada
- Fecha: 2026-09-09
- Contexto de fase: FASE 4 (SEO)
- Complementa: ADR-002 (page schema), ADR-006/011 (render)

## Contexto

`publishing.md` fija que el **published schema incluye el SEO por página** y que sitemap/robots
se generan en el build (Fase 5). Faltan: metadatos SEO editables, redirects, slug history y
sitemap/robots servidos dinámicamente (Fase 4, SSR).

## Decisión

- **SEO por página en el page schema:** objeto **opcional** `seo` a nivel raíz (hermano de
  `sections`): `meta_title?, meta_description?, canonical?, robots, og_image?, jsonld_type?`,
  validado por zod. Aditivo-opcional ⇒ `schema_version` intacto. Las **entries** derivan SEO por
  convención (title/excerpt/featured_image) con overrides opcionales en `data`. Sin tabla de
  metadatos SEO.
- **Redirects y slug-history UNIFICADOS** en una tabla `redirects` (`from_path`, `to_path`,
  `status`, `source` = `manual|slug_change`, `is_active`). Un cambio de slug **auto-crea** un
  redirect `slug_change` vía evento de **kernel** `PublicPathChanged(siteId, oldPath, newPath)`:
  Builder (path de página) y Content (slug de entry) lo emiten sin conocer a Seo; Seo lo escucha
  y hace el upsert. Sólo se auto-crea si la URL vieja **era pública** (página con versión
  publicada / entry publicada y enrutable); al re-moverse a una ruta ya usada se **limpian** los
  `slug_change` obsoletos que salían de ella (evita ciclos en round-trips A→B→A). El `/render`,
  **antes del 404**, consulta redirects activos
  → devuelve `{ redirect:{ to, status } }` y **Nuxt emite el 301/302**. El resolver **sigue la
  cadena** en el servidor (A→B→C ⇒ un único 3xx a C, mejor para SEO) con tope de saltos
  (`MAX_HOPS=10`) y detección de ciclo: una cadena cíclica o demasiado larga **no** redirige y
  cae al 404, cerrando el bucle infinito en el navegador. El destino es **interno** (una ruta que
  empieza por `/`, sin `//` ni `\` ⇒ sin open-redirect); `from_path`≠`to_path`.
- **sitemap.xml / robots.txt dinámicos:** endpoints públicos por sitio que enumeran páginas +
  entries publicadas. Cada módulo de dominio aporta sus URLs por el contrato de kernel
  `SitemapUrlSource` (etiquetado en el contenedor con `SitemapUrlSource::TAG`); Seo recolecta
  todas las fuentes sin conocer los módulos. Las `<loc>` son absolutas con la `base_url` del sitio
  (settings) o, en su defecto, el host del request. La **misma** generación (`SitemapGenerator`)
  la reutiliza el build estático de Fase 5. El renderer sirve ambos como proxy en la ruta del sitio.

## Alternativas consideradas

- **Tabla `seo_meta` polimórfica**: normaliza el SEO pero lo separa del contenido y del schema
  que `publishing.md` define como fuente; se descarta para páginas (sí para overrides simples en
  entries vía `data`).
- **`slug_history` como tabla separada de `redirects`**: duplica el mismo “viejo→nuevo path
  301”; unificar con `source` es más simple y consistente.
- **robots/sitemap sólo en el build estático**: dejaría el SSR sin SEO servible hasta Fase 5;
  se sirven dinámicamente ahora y el build los reusa.

## Consecuencias

- (+) SEO viaja con el contenido (schema/entry); funciona igual en SSR y en el build de Fase 5.
- (+) Una sola tabla para redirects manuales y automáticos; menos superficie.
- (−) `seo` en el schema aumenta su superficie de validación (mitigado: aditivo-opcional + lock
  + tests).
- (−) JSON-LD y OG básicos en el MVP (sin editor visual); i18n/hreflang fuera de alcance.
