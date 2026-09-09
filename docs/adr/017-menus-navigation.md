# ADR-017 — Menús: enlaces por referencia y resolución en render

- Estado: Aceptada
- Fecha: 2026-09-09
- Contexto de fase: FASE 4 (Menús)
- Complementa: ADR-013 (SectionDataResolver)

## Contexto

Los sitios necesitan menús de navegación jerárquicos (primary, footer) cuyos ítems enlazan a
páginas, entries, índices de colección o URLs externas. Los paths cambian (slug history), así
que congelar el path en el menú produciría enlaces rotos.

## Decisión

Módulo `Navigation` con `menus` + `menu_items` (jerarquía por adjacency list `parent_id` +
`position`). Cada ítem guarda una **referencia** (`link_type` + `target_ulid`/`url`), **no el
path**. El path se **resuelve en render**: page→su path, entry→`/{route_prefix}/{slug}`,
collection→índice, url→tal cual, home→`/`.

La resolución la hace un `MenuResolver` que **implementa el contrato de kernel
`SectionDataResolver`** (ADR-013): un componente **`navigation`** en el page schema referencia
un menú por handle y el árbol resuelto se embebe en el sidecar `resolved`. Reusa exactamente la
maquinaria del CollectionGrid; `Builder`/render no dependen de `Navigation`.

Los menús se colocan como **sección** donde el usuario quiera (D4); no hay header/footer global
de sitio en el MVP.

## Alternativas consideradas

- **Path denormalizado en el ítem**: simple, pero rompe ante cambios de slug; descartado.
- **Nested set / closure table**: innecesario para 2–3 niveles; sobre-ingeniería (CLAUDE.md).
- **Header/footer como chrome global de sitio**: más “web real”, pero introduce el concepto de
  layout/tema de sitio; se difiere a una fase de temas.
- **Menús inyectados siempre en el payload de render** (no como sección): menos flexible y
  duplica maquinaria; se prefiere reusar `SectionDataResolver`.

## Consecuencias

- (+) Enlaces correctos ante cambios de slug (resueltos en vivo).
- (+) Cero acoplamiento nuevo Builder↔Navigation (contrato de kernel).
- (−) Sin header/footer global: la sección `navigation` se añade por página (deuda D4).
- (−) `Navigation` depende de Builder+Content para resolver referencias (declarado).
