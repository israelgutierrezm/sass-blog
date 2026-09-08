# ADR-002 — Páginas como page schema JSON versionado (no HTML)

- Estado: Aceptada
- Fecha: 2026-09-07

## Contexto

El Builder debe permitir editar, previsualizar, versionar, publicar, revertir y —a futuro—
exportar estáticamente y servir headless. Se necesita una representación de página que sea
editable estructuralmente y renderizable por dos consumidores (preview en Vite y render en
Nuxt) con salida idéntica.

## Decisión

La **fuente de verdad de una página es un page schema JSON estructurado y versionado**
(`{ schema_version, sections: [{ id, type, variant, visible, props, settings }] }`), nunca
HTML generado. `type`/`variant`/`props`/`settings` se **validan** contra un component
registry tipado. Cada cambio importante crea una `PageVersion`; la versión publicada es
inmutable. El mismo schema alimenta preview, render dinámico y export estático.

## Alternativas consideradas

- **Guardar HTML generado**: imposible de re-editar de forma estructurada, difícil de
  versionar y de re-tematizar; acopla contenido a presentación.
- **Modelo relacional puro de secciones/props (EAV)**: rígido y verboso para props anidados
  y variantes; alto costo por cada nuevo componente.
- **Markdown/portable text**: bueno para artículos, insuficiente para layout de secciones
  con variantes y bindings dinámicos.

## Consecuencias

- (+) Edición estructurada, versionado/rollback, un solo pipeline de render, base para
  export estático y headless.
- (+) Preview y producción comparten componentes: cero divergencia visual.
- (−) Se usa JSON como dato de dominio en este subsistema, en tensión con "preferir columnas
  relacionales". Es una excepción **justificada**: es estructura de contenido, no dato
  transaccional. Los datos claramente relacionales siguen en columnas con constraints.
- (−) Requiere validación estricta del schema en el backend (no confiar en el editor).
