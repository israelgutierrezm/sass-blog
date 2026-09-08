# ADR-010 — Validación dinámica de entries y catálogo cerrado de tipos de campo

- Estado: Aceptada
- Fecha: 2026-09-08
- Contexto de fase: FASE 3 (Content)
- Complementa: ADR-004

## Contexto

El `data` JSON de un `Entry` debe validarse contra el schema de su Collection. Pero, a
diferencia del page schema (ADR-004, cuyo registry es código commiteado), **el schema de una
Collection lo define el usuario por-site en runtime**. No puede haber un artefacto JSON Schema
commiteado por colección.

## Decisión

**El validador de `data` se construye en runtime** desde `collection_fields` con un **Laravel
Validator dinámico** (dos perfiles: `draft` relajado / `publish` estricto — `required` sólo al
publicar). No se usa opis aquí: las reglas referenciales (relation/media/slug) necesitan BD +
tenant y no son expresables en JSON Schema; opis se reserva al page schema commiteado.

El **catálogo de tipos de campo es cerrado** (18 tipos: text, textarea, richtext, integer,
decimal, boolean, date, datetime, money, email, url, slug, select, multiselect, media,
media_array, relation, json) y **compartido/commiteado**: autoría única en TS en
`packages/site-schema`, emitido por el build como `field-types.v1.json` + `.lock` a
`backend/resources/site-schema/`, consumido por el enum PHP `FieldType`. El *mapeo tipo→reglas*
es COMPORTAMIENTO paralelo (PHP en `EntryDataValidator` + espejo TS de preview), cubierto por
un **fixture de conformidad** compartido corrido en Pest **y** Vitest (patrón del test de
render compartido). `url`/`media` se validan a esquema `http(s)` (bloquear `javascript:`/`data:`).

## Alternativas consideradas

- **JSON Schema en memoria con opis**: no expresa las reglas referenciales (relation/media/
  slug único con BD+tenant).
- **Enum `FieldType` autoríado en PHP y espejado a mano en TS**: más ligero pero garantiza
  drift del catálogo; sin lock anti-drift.

## Consecuencias

- (+) Una sola autoría del conjunto cerrado (lock anti-drift, consistente con ADR-004).
- (+) Reglas referenciales reales (existencia same-site, unicidad de slug).
- (−) El frontend sólo previsualiza forma/tipo/enum/required; la integridad referencial es
  "pendiente de validar en el servidor".
- (−) Publicar puede fallar por un `required` nuevo del schema (correcto, con mensaje claro).

## Notas de implementación (sub-slice 4)

Decisiones tomadas al implementar `EntryDataValidator` (validadas por revisión adversarial):

- **Validador agnóstico a la key.** Cada campo se mapea a un atributo sintético (`f0..fN`) y su
  valor se toma con acceso **literal** a la clave (`array_key_exists`), no interpolando la key en
  una ruta con notación de puntos de Laravel. De lo contrario una key con `.` o `*` (p. ej.
  `seo.canonical`) se reinterpretaría como ruta anidada y **evadiría por completo las reglas**
  (SafeUrl incluido) — un bypass de seguridad real. Las rutas de error se traducen de vuelta a la
  key real.
- **Detección de claves fantasma por string.** PHP normaliza las keys numéricas del JSON a `int`;
  la comparación con lo declarado castea ambos lados a string para no rechazar keys numéricas
  legítimas.
- **`date`/`datetime` con `date_format`** (no la regla `date` genérica): `Y-m-d` para `date` e
  ISO-8601 para `datetime`. Distingue los dos tipos y fija formato canónico (contrato UTC).
- **`money` con `decimal:0,2`**: rechaza notación científica y escala > 2 (evita truncamiento
  silencioso frente a `DECIMAL(12,2)`).
- **Cadena vacía `""`**: NO se normaliza en el validador. Laravel ya salta las reglas
  no-implícitas cuando `trim($value) === ''` (gate `presentOrRuleIsImplicit`), de modo que `""`
  se comporta como ausente de forma uniforme en todos los escalares.

**Deuda declarada:** (a) el **formato de la `key`** de un `CollectionField` (rechazar `.`, `*`,
etc.) se validará en la **creación del campo** (Form Request, sub-slice 7); el validador ya es
seguro ante keys arbitrarias, pero conviene la defensa en profundidad. (b) `FieldType::Slug` en
`data` se valida sólo como `string` (es campo de despliegue/binding; la ruta pública sale de la
columna dedicada `entries.slug`, no de `data`). (c) `integer` acepta el booleano `true` (quirk de
la regla nativa de Laravel); impacto nulo con clientes JSON de formulario.
