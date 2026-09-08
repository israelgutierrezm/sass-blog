# ADR-012 — Bindings seguros del template (`$bind`)

- Estado: Aceptada
- Fecha: 2026-09-08
- Contexto de fase: FASE 3 (Content)

## Contexto

Las secciones de la plantilla de artículo deben vincular sus props a campos de la Entry
(p.ej. el título del Hero = `entry.title`, el texto = `entry.data.body`). Debe ser SEGURO: nada
de expresiones arbitrarias, `eval`, reflexión ni traversal.

## Decisión

- **Forma:** un nodo inline `{ "$bind": "entry.title" }` en props **escalares de nivel
  superior** (misma frontera que los FieldDescriptor de FASE 2). El literal doble-sirve de
  placeholder/fallback en el preview del admin.
- **Allow-set colección-consciente:** universales (`entry.title/slug/status/published_at/
  created_at/updated_at`) + `entry.data.{K}` por cada `collection_field` escalar bindeable
  (excluye media/media_array/multiselect/relation/json) + preset Article (`entry.author.
  {name,slug}`, `entry.category.{name,slug}`). **Prohibido explícito**: `entry.id/workspace_id/
  site_id`, `entry.data` (objeto entero), `__proto__`, `constructor`.
- **Resolución en el BACKEND** con **accesores tipados codificados a mano por rama** (jamás
  `eval`/reflexión/`data_get` sobre ruta del cliente). El renderer recibe secciones concretas.
- **Validación del template:** al guardar/publicar, cada `$bind` se reemplaza por un centinela
  del tipo esperado y se corre el JSON Schema NORMAL (draft/publish) de ADR-004 + un type-check
  contra el allow-set — **sin tocar los artefactos de ADR-004**.

## Alternativas consideradas

- **Mapa hermano `section.bindings`**: bookkeeping paralelo que se desincroniza del schema.
- **Tercer JSON Schema 'template'**: cirugía en zod; innecesaria con el resolver-a-centinela.

## Consecuencias

- (+) Un único hogar del valor; el renderer/site-components intactos; generaliza a props
  anidadas y a CollectionGrid.
- (−) Superficie de seguridad crítica → pruebas OBLIGATORIAS con payloads maliciosos
  (`entry.workspace_id`, `__proto__`, `constructor`, `entry.data`, rutas con puntos) →
  422 al publicar e irresolubles al render.
