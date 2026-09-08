# ADR-013 — Resolución de datos de secciones dinámicas (CollectionGrid)

- Estado: Aceptada
- Fecha: 2026-09-08
- Contexto de fase: FASE 3 (Content)
- Extiende: ADR-008

## Contexto

Una sección `CollectionGrid` necesita ENTRIES en tiempo de render. El page schema publicado es
inmutable (estructura), pero los datos del grid son dinámicos. ¿Quién resuelve la query y cómo
llegan los datos al componente sin acoplar Builder a Content ni ensuciar el componente?

## Decisión

El endpoint `/render` **embebe** las tarjetas resueltas server-side en un **sidecar `resolved`**
keyed por `section.id`, **hermano de `page`** (no dentro de `page.props`/`data`): `page` refleja
la estructura versionada intacta; `resolved` es efímero/no versionado.

La costura es un contrato de kernel **`SectionDataResolver { supports(type); resolve(section,
ctx) }`** en `Shared`. `Content` registra `CollectionGridResolver` (tagged); `RenderPageData`
(Builder) recorre las secciones `category='dynamic'` y arma el sidecar. Así **Builder no depende
de Content**. El componente Vue `CollectionGrid` permanece PURO (recibe `data` inyectada;
placeholder en el preview del admin, obligado por ADR-008).

`resolve` sólo devuelve entries `published` (los drafts nunca fugan), con batch `whereIn` de
author/category/imagen (sin N+1) y mapeo a `EntryCard { id, title, path, excerpt, image, date,
author, category }`.

**Cache:** la inmutabilidad de la versión publicada aplica a la ESTRUCTURA (schema=query), no
a los datos embebidos → **ETag compuesto** `"{versionUlid}~{contentFingerprint}"` (hash corto de
`entry.ulid+updated_at`) cuando hay secciones dinámicas; las páginas estáticas puras conservan
`"{versionUlid}"` (comportamiento FASE 2). `Cache-Control: public, max-age=60`.

## Alternativas consideradas

- **El renderer Nuxt orquesta un feed público aparte**: cascada SSR + reparseo de secciones en
  el renderer; cache más limpio no compensa. El feed standalone se DIFIERE para interactividad
  cliente (cargar-más/filtrar-sin-recargar), respaldado por el mismo resolver.

## Consecuencias

- (+) Una sola ida SSR; el componente sigue puro; mismo componente en admin y renderer.
- (+) Sin ciclo Builder→Content (contrato de kernel).
- (−) El contrato de render gana props opcionales `resolved`/`linkBase` (aditivo).
- (−) Invalidación activa de caché diferida (MVP: fingerprint + max-age 60s).
