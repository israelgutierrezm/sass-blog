# ADR-024 — Portadas: componente `featured` de artículos curados a mano

- Estado: Aceptada
- Fecha: 2026-09-14
- Contexto de fase: FASE 10 (Portadas / Frontpage)
- Complementa: ADR-004 (registro de componentes), ADR-013 (canal `resolved`), ADR-023 (editorial)

## Contexto

El `CollectionGrid` (FASE 3) lista entradas de una colección de forma AUTOMÁTICA (query + orden).
Para una portada tipo periódico hace falta lo contrario: que un editor **elija y ordene a mano**
los artículos destacados. Existe la capability `publisher.frontpages` sin implementación detrás.

## Decisión

- **Componente `featured` en el page-schema**, no una entidad nueva. Reutiliza la infra del
  CollectionGrid: `SectionDataResolver` + canal `resolved` (ADR-013) + `EntryCard`. Es una sección
  más de la página → portable y versionada con la página. Los props son **referencias** (ULIDs de
  artículos elegidos, ordenados); los DATOS los resuelve el backend en render, nunca el cliente.
  Props: `collection` (de dónde elegir), `items` (ULIDs ordenados), `heading?`, presentación.
  Variantes `featured-lead` (destacado + secundarias) y `featured-list`.

- **`FeaturedResolver` (Content):** `supports('featured')`; resuelve cada ULID de `items` a un
  `EntryCard` **publicado**, EN ORDEN, saltando los no-publicados/borrados. Se añade al composite
  de resolvers; corre dentro de `WorkspaceContext` (scopeado por tenant/site).

- **Curación por arrastrar y soltar** en el Builder: un control `entry-picker` lista los
  artículos publicados de la `collection` (por API), permite añadir/quitar y **reordenar con
  drag-and-drop** (HTML5 nativo, sin dependencia nueva). Guarda `items` = ULIDs ordenados.

- **Gating `publisher.frontpages` (Pro), validado AL GUARDAR.** El backend rechaza guardar/publicar
  una página cuyo schema contenga una sección `featured` si el plan no incluye la capability
  (`CapabilityDeniedException` → 403). El backend es la fuente de verdad (el Builder puede
  mostrarlo, pero el servidor decide). La publicación directa y el resto del builder no cambian.

- **Artefactos JSON Schema** del backend se regeneran con `pnpm build:schema` (el backend valida
  el page-schema contra ellos, ADR-004).

## Alternativas consideradas

- **Entidad `Frontpage` dedicada** (lista curada con su propio editor y render): más piezas y un
  segundo modelo de contenido; el componente en el page-schema reutiliza todo (builder, versionado,
  render) y encaja en "la página es JSON de secciones".
- **Extender `CollectionGrid` con modo manual** (ya tiene `mode: manual` diferido): mezclaría dos
  responsabilidades (query automática vs curación) en un componente; `featured` es explícito.
- **Reorden por botones ↑↓:** más simple y robusto en E2E, pero peor UX; se elige drag-and-drop
  (decisión de producto) con HTML5 nativo para no añadir dependencia.
- **Gating en el Builder (ocultar del palette):** requiere que el admin conozca las capabilities
  del workspace; el gating server-side al guardar es autoritativo y no depende del cliente.

## Consecuencias

- (+) Portadas curadas reutilizando el motor de componentes, el canal `resolved` y `EntryCard`.
- (+) Referencias por ULID → el render siempre muestra el estado publicado actual del artículo.
- (+) Gating honesto en el servidor; monetiza `publisher.frontpages`.
- (−) Drag-and-drop HTML5 es más frágil en E2E (se mitiga verificando también el orden de alta).
- (−) El resolver hace N búsquedas por ULID (mitigable con un `whereIn` + reordenar en memoria).
- (−) Layout fijo por variante (sin "slots" de tamaños) en el MVP.
