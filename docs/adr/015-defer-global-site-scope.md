# ADR-015 — Aplazar el SiteScope global pese al segundo módulo scopeado por site

- Estado: Aceptada
- Fecha: 2026-09-08
- Contexto de fase: FASE 3 (Content)
- Supersede: la cláusula de disparo de ADR-005

## Contexto

ADR-005 (FASE 2) difirió un `SiteScope` global y fijó como disparador "cuando entren varios
módulos scopeados por site (Content, Media)". `Content` es ahora el 2º módulo scopeado por site.
Promover ahora obligaría a un retrofit de Builder (todos sus controladores y tests) a mitad de
FASE 3.

## Decisión

**Aplazar** el `SiteScope` global + `SiteContext`. Se mantiene el filtro EXPLÍCITO por el site
resuelto de la ruta + un **trait `ScopedToSite`** (`scopeForSite($id)`, `forCollection`, etc.) +
**tests de aislamiento por-site OBLIGATORIOS** para entries, para el pivote `category_entry`
(fuera de `WorkspaceScopeTest`) y para las resoluciones referenciales/bindings/resolver. El
nuevo disparador para promover es **Media / Fase 4** (donde ya se tocan varios módulos
site-scoped a la vez).

## Alternativas consideradas

- **Promover a `SiteScope` global ahora**: retrofit de Builder + Content a mitad de fase; alto
  riesgo por poca ganancia inmediata.

## Consecuencias

- (+) Menos riesgo; misma garantía de aislamiento vía trait + tests.
- (−) El aislamiento cross-site sigue dependiendo de disciplina de controlador; se compensa con
  el trait obligatorio y los tests por-site.
- (→) ADR-015 supersede la cláusula de disparo de ADR-005; se revisa en Media/Fase 4.
