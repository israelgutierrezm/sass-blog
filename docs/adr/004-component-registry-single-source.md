# ADR-004 — Fuente única del component registry (autoría TS, validación backend por artefacto generado)

- Estado: Aceptada
- Fecha: 2026-09-07
- Contexto de fase: FASE 2 (Builder)

## Contexto

El page schema (ADR-002) se compone de secciones `{type, variant, props, settings}`
validadas contra un **component registry**. Ese registry lo consumen tres lugares: el
**admin** (deriva la paleta "Add section" y los controles del panel de props), el
**renderer** (mapea type→componente) y el **backend** (valida el schema entrante al guardar
draft y al publicar). Si cada lado define sus propias reglas, divergen: el editor permite lo
que el backend rechaza, o peor, el backend acepta lo que el editor no sabe pintar.

## Decisión

**Autoría única en TypeScript en `packages/site-schema`.** Cada componente se define una
sola vez con `defineComponent()` (usando **zod** como lenguaje de autoría de `propsSchema`/
`settingsSchema`). Un paso de build (`build-artifacts.ts`) emite artefactos versionados y
commiteados:

- `registry.v1.manifest.json` → para el **admin**: type/variantes/categorías/defaults y, por
  campo, `{label ES, control, options}` para derivar los controles.
- `registry.v1.draft.schema.json` / `registry.v1.publish.schema.json` → **JSON Schema
  2020-12** (perfil relajado para draft / estricto para publicar, `sections >= 1`).
- `registry.v1.lock` → checksum anti-drift.

El build copia los `.json` a `backend/resources/site-schema/`. El **backend valida con
`opis/json-schema`** contra esos artefactos — **nunca reescribe reglas ni mantiene un
registry paralelo en PHP**. Un test PHP de frescura (checksum) + regeneración-y-diff en CI
impiden el drift. `validatePageSchema()` en TS replica el veredicto para previsualizar; el
backend decide.

## Alternativas consideradas

- **Registry espejo en PHP** (doble autoría): garantiza divergencia; rechazada.
- **JSON Schema escrito a mano**: sin tipos TS inferidos ni metadatos de UI; frágil.
- **A2 — field-descriptors + validador PHP propio**: un solo `registry.json` con metadatos de
  UI + constraints ligeras y un validador PHP pequeño. Menos piezas y sin dependencia nueva,
  pero validación menos expresiva y lógica de interpretación duplicada en PHP. Fallback
  legítimo para el alcance chico de FASE 2; se descartó por no escalar al catálogo mayor de
  Fase 3.

## Consecuencias

- (+) Una sola verdad; el backend no puede aceptar/rechazar algo distinto del editor.
- (+) Tipos TS inferidos de zod; validación rica (Draft 2020-12).
- (+) Escala al catálogo grande de Fase 3 sin reescrituras.
- (−) Maquinaria de build + lock + regen-diff en CI, y una **dependencia PHP nueva
  (`opis/json-schema`)**. Justificada: evita reescribir reglas de validación en dos lenguajes.
- (−) La cobertura zod→JSON Schema se limita al subset que mapea 1:1 (refinements/transforms
  no mapean); las reglas semánticas extra (unicidad de `id` de sección) se validan aparte.
