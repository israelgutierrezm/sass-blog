# ADR-009 — Collection Engine: almacenamiento híbrido y reparto relacional

- Estado: Aceptada
- Fecha: 2026-09-08
- Contexto de fase: FASE 3 (Content)
- Complementa: ADR-002

## Contexto

El CMS necesita tipos de contenido definibles por el usuario (colecciones) con campos
personalizados, y un preset de artículos. Hay que evitar tanto un EAV puro (verboso, sin
constraints) como meter datos claramente relacionales dentro de JSON.

## Decisión

Modelo **híbrido**. El `Entry` guarda **columnas universales reales** (`id, ulid,
workspace_id, site_id, collection_id, title, slug, status, published_at, author_id,
created_by, updated_by`) y una columna **`data` JSON** con los campos personalizados,
**validada dinámicamente** (ADR-010) contra el schema de la Collection (`collection_fields`).

**Reparto relacional:** los ejes editoriales de alto valor de consulta van RELACIONALES —
autor = columna FK `entries.author_id` → tabla `authors`; categoría = pivote `category_entry`.
El resto de campos custom (excerpt, body, featured_image, tags, reading_time, featured) viven
en `data`. La `config` de un `collection_field` es JSON (descriptor/schema, sancionado igual
que `props/settings` de secciones), pero el único eje verdaderamente relacional —el target de
un campo `relation`— va en columna FK real `related_collection_id` (restrictOnDelete).

**Un solo módulo `Content`.** `Articles` es un **preset/capability** sobre el motor (no un
módulo `Editorial` aparte); reconcilia la tabla exploratoria de `architecture.md §4` con
`builder.md` ("Articles… no un módulo rígido aparte").

**Entry sin versionado (D1):** una sola fila mutable con `status` (draft|published|archived)
+ `published_at`. La superficie pública sólo lee `published`.

## Alternativas consideradas

- **EAV puro**: rígido y sin constraints; rechazado por `builder.md`.
- **Todo en `data` (incl. autor/categoría)**: no indexable, guarda referencias relacionales en
  JSON (desaconsejado por `database.md`), e internamente inconsistente (los authors no son
  entries de una Collection, así que un field `relation` no aplica).
- **`entry_versions` (espejo de `page_versions`)**: máxima seguridad editorial, pero el
  CollectionGrid tendría que unir a versiones y añade inmutabilidad/bifurcación innecesarias
  para MVP.

## Consecuencias

- (+) `CollectionGrid` automático consulta UNA tabla (`status=published ORDER BY published_at`).
- (+) Autor/categoría queryables e indexables; campos custom flexibles sin EAV.
- (−) **Deuda declarada**: editar una entry publicada va EN VIVO (sin aislamiento
  draft-de-publicada ni rollback). Mitigado con revalidación al publicar + auditoría; evoluciona
  a `entry_versions` bajo la capability `cms.advanced_workflow`.
- (−) La DB no valida el `data` JSON → depende de ADR-010 + tests + mutación.
