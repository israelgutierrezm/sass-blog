# ADR-019 — Publicación estática: Deployment + render Node self-contained

- Estado: Aceptada
- Fecha: 2026-09-12
- Contexto de fase: FASE 5 (Static Publishing)
- Complementa: ADR-002 (page schema), ADR-004 (site-components única fuente), ADR-008
  (tokens), ADR-013 (SectionDataResolver), ADR-018 (SEO/sitemap)

## Contexto

`publishing.md` fija el flujo `Draft→Publish→Build→Deployment→Production`, la entidad
`Deployment` (targets DYNAMIC/STATIC) y una regla dura: **el render estático usa EXACTAMENTE
los mismos page schemas y `site-components` que el dinámico** — no hay un constructor aparte.
Falta materializar el export estático + artefacto (FASE 5, MVP).

## Decisión

- **Módulo `Publishing`** (`domain`, `depends_on: Sites, Builder, Content, Seo`) con la entidad
  **`Deployment`** (INMUTABLE, bitácora de builds): `site_id, target(static), status
  ∈ {pending,building,success,failed}, triggered_by, artifact_ref?, published_hash, error?,
  timestamps`. Un build corre en un **job en cola idempotente** (llave = `site_id` +
  `published_hash`; re-disparar el mismo estado publicado no reconstruye ni duplica). Nunca en
  el request HTTP.

- **Render estático en Node (CLI dedicado), no `nuxt generate`**. El job de Laravel produce un
  **build manifest** (JSON) reutilizando lo ya montado: `SitemapGenerator`/`SitemapUrlSource`
  ENUMERA las URLs públicas (páginas + entries), y el MISMO payload de `/render`
  (`RenderedPage::payload` + `DynamicRouteResolver` + `SectionResolution`) da, por URL,
  `{schema, resolved, seo, tokens, linkBase}`. Un comando Node lee el manifest y emite HTML por
  página con `PageRenderer` + `renderToString` (los mismos site-components) a **clean paths**
  (`/`, `/acerca`, `/blog/{slug}`), sin el prefijo `_site` (que es sólo del serving multisitio
  del renderer). Se elige CLI sobre `nuxt generate` porque el artefacto debe ser **portable y
  self-contained** (rutas raíz, sin la estructura de Nuxt ni un servidor).

- **CSS**: el artefacto incluye `assets/styles.css` = `design-tokens/tokens.css` + un bundle de
  los estilos de `site-components` (SFC `<style>` globales `.st-*`, extraídos por un build lib de
  Vite). Los **overrides de tokens del sitio** ya viajan inline en el HTML (`PageRenderer` los
  pinta sobre `.st-site-root`, ADR-008). Enlazado relativo → abre sin backend.

- **Artefacto self-contained**: los binarios de media usados se **copian** al ZIP (`assets/media/…`)
  y el HTML los referencia con rutas relativas; `sitemap.xml`/`robots.txt` se generan en el build
  (reusando `SitemapGenerator`, con la base del sitio). El ZIP se guarda en el Filesystem
  (`artifact_ref`) y se descarga por **URL firmada** temporal.

- **Capability `site.export`** (feature de plan, p.ej. Pro): gating HTTP como `media.library`
  (Free-403/Pro-200), además del RBAC (`site.publish`/permiso de deploy).

## Alternativas consideradas

- **`nuxt generate` (Nitro prerender)** del renderer actual: rápido de cablear, pero los paths
  llevan `/_site/{id}` y el artefacto arrastra la estructura de Nuxt; menos portable. Se descarta
  para el export (sí es la vía natural del target DYNAMIC/SSG, futuro).
- **Renderizar HTML en PHP** (motor propio): rompería la regla de "misma fuente de componentes"
  (ADR-004); habría dos verdades visuales. Descartado.
- **Referenciar media por URL** (no copiar): ZIP liviano pero no autosuficiente; se prefiere
  self-contained para el MVP (copia diferida sería una regresión de portabilidad).
- **Deployment mutable / sin hash**: reconstruir siempre desperdicia; el `published_hash` da
  idempotencia y evita builds redundantes.

## Consecuencias

- (+) Un solo motor visual: el estático y el dinámico comparten schemas + site-components.
- (+) Reusa FASE 4 casi entera (SitemapGenerator, contratos de render, tokens); el módulo
  Publishing es orquestación + artefacto, no un segundo renderer.
- (+) Artefacto portable (abre local, subible a cualquier hosting/CDN).
- (−) El build cruza a Node (Symfony Process): el job de Laravel invoca el CLI; hay que fijar el
  contrato del manifest y manejar fallos del proceso (status=failed + error).
- (−) MVP sin subida a CDN ni dominios (SiteDomain difervido) ni incremental: se reconstruye el
  sitio completo por deployment.
