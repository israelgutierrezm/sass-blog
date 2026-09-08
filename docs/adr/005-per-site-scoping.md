# ADR-005 — Estrategia de scoping por site (filtro explícito; SiteScope global diferido)

- Estado: Aceptada
- Fecha: 2026-09-07
- Contexto de fase: FASE 2 (Builder)

## Contexto

ADR-001 aísla por **workspace** con un global scope. FASE 2 introduce datos scopeados
además por **site** (`pages`, `page_versions`). `multitenancy.md` prevé el filtrado por
`site_id`, pero no existe aún un `SiteScope`/`SiteContext` global análogo al de workspace.

## Decisión

En FASE 2, los modelos del Builder llevan `site_id` NOT NULL + FK, y el aislamiento por site
se hace con **filtro EXPLÍCITO por el site resuelto de la ruta** (`/workspaces/{workspace}/
sites/{site}/...`), verificando en el controlador que la entidad pertenece a ese site
(p.ej. `page->site_id === $site->id`, 404 si no). **Se difiere** un `SiteScope` global +
`SiteContext` + puntero de site en `WorkspaceContext`.

## Alternativas consideradas

- **SiteScope global ya**: análogo a WorkspaceScope. Correcto pero prematuro con un solo
  módulo scopeado por site; añade complejidad de contexto (¿qué pasa en rutas sin site?).

## Consecuencias

- (+) Simple; `WorkspaceScope` global sigue impidiendo fugas cross-workspace.
- (−) El aislamiento cross-site DENTRO de un workspace depende de disciplina del controlador,
  no de un scope. **Mitigación obligatoria**: un `scope local forSite($id)` en el módulo y un
  **test de aislamiento por-site** en el DoD.
- (→) Cuando entren varios módulos scopeados por site (Content, Media), se promoverá a
  `SiteScope` global en una ADR que reemplace a ésta.
