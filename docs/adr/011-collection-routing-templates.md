# ADR-011 — Ruteo y plantillas de colección (template-como-Page)

- Estado: Aceptada
- Fecha: 2026-09-08
- Contexto de fase: FASE 3 (Content)
- Extiende: ADR-005, ADR-006

## Contexto

Un artículo necesita una URL pública (`/blog/{slug}`) y una plantilla de detalle reutilizable
que se rellene con los datos de la Entry. Hay que evitar duplicar la maquinaria de páginas.

## Decisión

- **La plantilla ES una `Page`** con `kind='collection_template'` (`path` NULL). Reutiliza SIN
  código nuevo `SaveDraft`, `PublishPage` (promover-y-bifurcar), la guarda de inmutabilidad, el
  preview firmado (ADR-007) y el Builder tri-panel. Requiere ALTER `pages` (`+kind`, `path`
  nullable, CHECK: standard⇒path, template⇒null).
- La Collection declara `route_prefix` (p.ej. `blog`; kebab multi-segmento, sin token; el
  último segmento de la URL es el `{slug}`) y `template_page_id`.
- **Resolución pública unificada** en el `/render?path=` existente (el renderer no cambia de
  firma): **estático-primero → dinámico**. Una página estática con path exacto GANA; en miss,
  el backend llama al contrato de kernel **`DynamicRouteResolver`** que Content enlaza (parte
  `path` en prefijo + slug, exige ≥2 segmentos, resuelve la Collection por `route_prefix` con
  template publicado, gate por `cms.collections`, resuelve la Entry `published` por slug, resuelve
  bindings ADR-012). Sin Content/capability enlazados → **sólo-estático** (degradación elegante).
  Ruteo por Host en producción: diferido.

`Builder` NO depende de `Content`: la costura es el contrato de kernel en `Shared`.

## Alternativas consideradas

- **Tabla `collection_templates` paralela**: duplica versionado/publish/inmutabilidad/preview.
- **Columna `template_schema` JSON en `collections`**: pierde versionado, inmutabilidad,
  rollback y el editor visual (Builder).

## Consecuencias

- (+) Toda la maquinaria de FASE 2 se reutiliza; un único endpoint público de render.
- (+) El renderer y los site-components no cambian (reciben secciones concretas).
- (−) ALTER `pages` con CHECK + guarda de modelo/Request; una query pública ahora ramifica.
- (−) Riesgo de colisión de rutas página-vs-colección → estático-exacto-primero +
  `unique(route_prefix)` por sitio + dinámico exige ≥2 segmentos + aviso (no bloqueo) en el admin.
