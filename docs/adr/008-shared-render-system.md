# ADR-008 — Sistema de render compartido: distribución de site-components y design tokens

- Estado: Aceptada
- Fecha: 2026-09-07
- Contexto de fase: FASE 2 (Builder)
- Extiende: ADR-003

## Contexto

El mismo componente y variante deben renderizar **idéntico** en el preview del admin (Vite) y
en el renderer público (Nuxt SSR). Los riesgos reales: dos instancias de Vue divergen la
hidratación; la inyección de estilos en runtime rompe SSR; APIs de navegador en `setup`
provocan mismatches. Y los sitios necesitan tematización por-site sin duplicar componentes.

## Decisión

**`packages/site-components`** se distribuye como **ESM precompilado con el CSS extraído** a un
único `style.css` (Vite *lib mode*, `cssCodeSplit: false`); `vue` es **peerDependency** de
**instancia única** (dedupe de pnpm; Nuxt `build.transpile`). Entrada de montaje **única**
`PageRenderer.vue` (ninguna app implementa su propio bucle); `SectionRenderer` mapea
`type`→componente con `:key = section.id` (ULID) y placeholder para tipos desconocidos (nunca
lanza); `SectionShell` centraliza `container/spacing/background/align`. SFC **deterministas**
(sin `Date.now`/`Math.random`/DOM en `setup`/scope de módulo). Red de seguridad: **test de
render compartido** (Vitest) que monta en jsdom y hace `renderToString` (SSR) sobre fixtures
golden y falla ante acceso a DOM o divergencia estructural.

**`packages/design-tokens`**: tokens como CSS custom properties con prefijo **`--st-`**
declaradas sobre **`.st-site-root`** (no `:root`, para no contaminar el chrome del admin).
Los componentes leen `var(--st-*, fallback)` siempre con fallback. Override por-sitio desde
`sites.settings.branding.tokens` (JSON parcial), emitido como estilo inline → tematizar es
puro cascade, SSR-safe, idéntico en Vite y Nuxt.

## Alternativas consideradas

- **CSS inyectado en runtime (style-loader)**: rompe/complica SSR; rechazada.
- **Componentes distintos por app**: viola "un solo componente para preview y producción".
- **Tokens sobre `:root`**: contamina el admin; se acota a `.st-site-root`.

## Consecuencias

- (+) Un solo sistema visual; SSR estable; tematización por-site sin duplicar.
- (−) Requiere disciplina (SFC deterministas, vue instancia única) verificada por el test de
  render compartido.
- (−) Deuda: tabla `site_themes` + validación estricta del override de tokens (FASE 2 usa
  `sites.settings.branding.tokens`).
