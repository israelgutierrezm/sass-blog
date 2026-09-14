# FASE 10 — Portadas / Frontpage · Diseño

> Fuente de verdad de la iteración. Decisiones en **ADR-024**. Docs base: `builder.md`,
> `architecture.md`, ADR-013 (canal `resolved`).

## Objetivo

Curar una portada tipo periódico: elegir y **ordenar a mano** artículos destacados en un bloque,
en cualquier página. Complementa el `CollectionGrid` (automático) con selección manual.

## Decisiones (detalle en ADR-024)

- Componente `featured` en el page-schema (reutiliza resolver + canal `resolved` + `EntryCard`).
- Curación **drag-and-drop** (HTML5 nativo, sin dependencia).
- Gating `publisher.frontpages` (Pro), **validado al guardar** el schema (backend).

## Componente `featured` (site-schema)

Props: `collection` (handle; default `articles`), `items` (lista ORDENADA de ULIDs), `heading?`,
`showExcerpt`/`showImage`/`showDate`. Categoría `dynamic`. Variantes `featured-lead`, `featured-list`.

## `FeaturedResolver` (backend, Content)

`supports('featured')`. Lee `items` (ULIDs); resuelve en UN `whereIn` los EntryCards **publicados**
y los reordena en memoria según `items` (anti-N+1); salta los no-publicados/borrados. Tagged en el
composite. Corre en `WorkspaceContext`.

## Gating al guardar

Al guardar/publicar una página, si el schema contiene una sección `type: featured` y el plan no
incluye `publisher.frontpages` → `CapabilityDeniedException` (403). Chequeo server-side en el flujo
de guardado del Builder (fuente de verdad).

## Admin — control `entry-picker`

Nuevo control en el Builder (FieldControl): lista los artículos publicados de la `collection` (por
API nueva, sólo lectura), permite añadir/quitar y **reordenar con drag-and-drop**. Persiste
`items` = ULIDs en orden.

## Sub-slices

1. `featured` + `FeaturedResolver` (whereIn + reorden) + tag + gating al guardar + build:schema ·
   tests (orden, sólo publicados, gating).
2. `Featured.vue` (site-components) + tests de render SSR.
3. Admin: control `entry-picker` (drag-and-drop) + endpoint de artículos publicados.
4. E2E (curar portada → artículos en orden en el sitio).

## Deuda MVP

Layout fijo por variante (sin slots de tamaños); una lista/colección por bloque; sin
previsualización en vivo del reorden; drag-and-drop HTML5 (E2E verifica también el orden de alta).
