# FASE 5 — Static Publishing (export artifact) · Diseño

Fuente: `publishing.md` + **ADR-019**. Reutiliza ADR-002/004/008/013/018 y casi toda la
infraestructura de FASE 4 (SitemapGenerator, contratos de render, tokens).

**Objetivo (vertical slice):** exportar un sitio como **estático self-contained**: HTML de
sus páginas + entries publicadas, `sitemap.xml`/`robots.txt`, CSS y media, empaquetado en un
**ZIP descargable**, con historial de **deployments**. Un solo motor visual: los mismos page
schemas y `site-components` que el dinámico.

## Módulo `Publishing`

`layer: domain`, `depends_on: ['Sites','Builder','Content','Seo']`.

| Tabla | Columnas · índices |
|---|---|
| `deployments` | `ulid, workspace_id, site_id(FK cascade), target('static'), status('pending'|'building'|'success'|'failed'), published_hash, artifact_ref?(string), bytes?(int), error?(text), triggered_by?(FK users nullOnDelete), timestamps`. `index(workspace_id, site_id, created_at)` |

- **Inmutable** (bitácora, como `audit_logs`): un deployment no se edita salvo su avance de
  `status`/`artifact_ref`/`error` por el job. No hay borrado por API en el MVP.
- **Estados**: `pending → building → success | failed`. El job los mueve; el status es la
  única mutación permitida tras crear.
- **`published_hash`**: hash del estado publicado del sitio (versión publicada de cada página +
  entries publicadas + redirects/menús que afectan al render). Da **idempotencia**: re-disparar
  con el mismo hash devuelve el último deployment `success` en vez de reconstruir.

## Flujo

`POST /workspaces/{ws}/sites/{site}/deployments` (auth + workspace + `capability:site.export`,
Policy `site.publish`) → crea `Deployment(pending)` → despacha `BuildStaticSite` (cola
`database`, idempotente por `site_id`+`published_hash`) → responde 202 con el deployment.

`BuildStaticSite` (job):
1. `status=building`.
2. **Enumera** las URLs públicas con `SitemapGenerator::forSite` (páginas estándar publicadas +
   entries publicadas enrutables) + la home.
3. Por URL, arma el **payload de render** con la MISMA maquinaria que `/render`
   (`RenderedPage::payload` para páginas; `DynamicRouteResolver` para detalles de colección) →
   `{path, schema, resolved, seo, tokens, linkBase:''}`. Más `sitemap.xml`/`robots.txt`
   (SitemapGenerator con la base del sitio) y la lista de **media** referenciada.
4. Escribe el **build manifest** (JSON) a un dir temporal y **invoca el CLI Node**
   (`Symfony\Process`): `node renderer/… <manifest.json> <outdir>`.
5. El CLI emite `outdir/{path}/index.html` + `assets/styles.css` (tokens + site-components) +
   copia media a `assets/media/…` con rutas relativas.
6. Laravel **empaqueta** `outdir` en un ZIP, lo guarda en el disk (`artifact_ref`), fija
   `bytes`, `status=success`. Ante cualquier fallo (incluido exit≠0 del proceso): `status=failed`
   + `error`.

`GET /workspaces/{ws}/sites/{site}/deployments` (historial), `GET …/deployments/{id}` (estado),
`GET …/deployments/{id}/download` → **URL firmada** temporal al ZIP.

## Render estático (CLI Node)

Comando en `renderer/` (reusa su dep `site-components`): lee el manifest y, por página,
`renderToString(createSSRApp(PageRenderer, {schema, tokens, resolved, linkBase:''}))` envuelto
en un documento HTML con el `<head>` de SEO (title/description/canonical/robots/OG/JSON-LD desde
`seo`) y `<link rel="stylesheet" href="{rel}/assets/styles.css">`. **Clean paths** (`/`→
`index.html`, `/acerca`→`acerca/index.html`, `/blog/x`→`blog/x/index.html`). El CSS bundle sale
de un **build lib de Vite** de site-components (extrae los `<style>` globales) concatenado con
`design-tokens/tokens.css`.

## Sub-slices 5

1. **Módulo `Publishing` + `Deployment`** (migración/modelo/estados/factory) + capability
   `site.export` (enum + seeders) + API disparar/consultar + Policy + tests. El job existe pero
   sólo mueve `pending→success` con un artefacto vacío (andamio, deuda declarada).
2. **Build manifest** en el job: enumeración (SitemapGenerator) + payloads de render + media
   referenciada. Sin Node aún: se valida el JSON del manifest por tests.
3. **CLI Node de render estático** (PageRenderer + CSS lib build) → HTML clean-path + styles.css.
   Tests JS (renderToString a fichero, snapshot ligero).
4. **Ensamblado + artefacto**: invocación del CLI desde el job (Symfony Process), copia de media,
   sitemap/robots, ZIP, `artifact_ref`, descarga firmada, idempotencia por `published_hash`.
5. **Admin**: botón "Exportar sitio estático", historial de deployments, descarga.
6. **E2E**: publicar sitio → exportar → el ZIP contiene el HTML de las páginas + sitemap + media.

## Capability

`site.export` (nueva, en `Shared\Domain\Capabilities\Capability`; sembrada por CapabilitySeeder;
otorgada al plan Pro por PlanSeeder). Gating HTTP `capability:site.export` (Free-403/Pro-200).
RBAC: la acción de deploy la autoriza `site.publish` (Policy de Sites/Deployment).

## Deuda MVP declarada

- Sin subida a CDN ni `SiteDomain`/SSL (fase de dominios).
- Sin build **incremental**: se reconstruye el sitio completo por deployment.
- Sin target DYNAMIC/SSG servido (sólo el export estático); el DYNAMIC vía `nuxt generate`
  queda para su fase.
- Limpieza de artefactos viejos (retención) diferida (job de limpieza, como los binarios de media).
- El CLI Node se invoca por proceso; en prod se cablea con la cola/worker (hoy `database`).

## Pruebas transversales

Aislamiento por-site (deployments, artefacto), gating capability (Free-403/Pro-200), RBAC,
**idempotencia del job** (mismo `published_hash` no reconstruye), **verificado por mutación**, y
**verificación en navegador**/inspección del ZIP del vertical (no sólo tests verdes).
