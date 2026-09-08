# ADR-003 — Renderer público con Nuxt 4 y componentes compartidos

- Estado: Aceptada
- Fecha: 2026-09-07

## Contexto

Los sitios publicados necesitan SSR (SEO, tiempo al primer byte, contenido dinámico) y, a
futuro, SSG/export estático y hybrid rendering. El preview del Builder (en el admin Vue) y el
sitio público deben verse **idénticos**, porque comparten los mismos componentes y schema.

## Decisión

El renderer público es una app **Nuxt 4 + TypeScript** (SSR/SSG) que consume el page schema
publicado vía la API y lo renderiza con `packages/site-components` y `packages/design-tokens`
—los **mismos** paquetes que usa el preview del admin—. El admin (Vue 3 + Vite) monta esos
componentes en modo edición/preview; Nuxt los monta en modo producción. Una única
implementación visual por componente/variante.

## Alternativas consideradas

- **Render público en el propio Laravel (Blade)**: duplicaría los componentes (Vue en admin,
  Blade en público) → dos versiones visuales, justo lo que se prohíbe.
- **SPA Vue pública (sin SSR)**: mal SEO y peor rendimiento inicial para sitios de contenido.
- **Un solo Nuxt para admin y público**: mezcla un editor complejo con el render público y
  complica el aislamiento y el despliegue; se prefiere separar admin (Vite SPA) de renderer.

## Consecuencias

- (+) Un solo sistema de componentes para preview y producción; SSR/SSG desde el diseño.
- (+) Camino natural a export estático (Fase 5) y a hybrid rendering.
- (−) Complejidad de build cross-framework (Vite del admin ↔ Nuxt): los componentes deben ser
  SFC puros sin acoplarse a APIs de app; se cubre con un test de render compartido.
- (−) Dos frontends que desplegar; aceptable y explícito en la topología del monorepo.
