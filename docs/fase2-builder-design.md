# FASE 2 — Diseño del primer vertical slice del Builder

- Estado: **Aprobado** (decisiones D1–D13 resueltas; ver más abajo).
- Fecha: 2026-09-07
- ADRs: 004 (registry), 005 (scoping por site), 006 (superficie pública), 007 (preview firmado), 008 (render compartido).

## Objetivo

Flujo real de punta a punta: `Page → Add Hero → Edit → Save Draft → Preview → Publish →
View Public Site`. Lo mínimo que ejercita el vertical, con la disciplina de FASE 1. **No** es
un Webflow.

## Módulo nuevo

`Builder` (layer `domain`, `depends_on: ['Sites']`) registrado en `config/sassblog.php`.
`Publishing` NO se crea: su costura es el evento `PagePublished` (la tabla `deployments` es
Fase 5).

## Modelo de datos

Ambas tablas: `workspace_id` NOT NULL (WorkspaceScope + BelongsToWorkspace) **y** `site_id`
NOT NULL (denormalización deliberada, como `audit_logs`). BIGINT interno + `ulid` público.
Migraciones en `app/Modules/Builder/database/migrations`, timestamps `2026_09_09_0002xx`.

### `pages`
`id, ulid(unique), workspace_id(FK→workspaces cascade), site_id(FK→sites cascade), title,
path, status(draft|published|archived, default draft), draft_version_id?(FK→page_versions
nullOnDelete), published_version_id?(FK→page_versions nullOnDelete), published_at?,
created_by?(FK→users nullOnDelete), timestamps, softDeletes`.
Índices: `unique(ulid)`, **`unique(workspace_id, site_id, path)`** (home = `path='/'`, sin
`is_home`), `index(workspace_id, site_id, status)`.

### `page_versions` (append-only; publicada inmutable)
`id, ulid(unique), workspace_id, site_id, page_id(FK→pages cascade), version_number,
status(draft|published, default draft), schema_version(smallint, default 1), schema(JSON),
label?, created_by?, published_at?, published_by?, timestamps`. Sin softDeletes.
Índices: `unique(ulid)`, **`unique(workspace_id, page_id, version_number)`**.

**FK circular** `pages↔page_versions` → **3 migraciones**: (1) `create_pages` con punteros
`unsignedBigInteger` nullable SIN FK; (2) `create_page_versions`; (3) `add_version_pointers_
to_pages` añade las 2 FK `nullOnDelete` (su `down()` hace `dropForeign` primero).

### Modelos
`App\Modules\Builder\Infrastructure\Models\{Page,PageVersion}` (`final`, `strict_types`,
`newFactory()` explícito).
- **Page**: `BelongsToWorkspace, HasFactory, HasPublicUlid, SoftDeletes`; `STATUS_*`;
  `$fillable=['site_id','title','path','status','created_by']` (punteros NO fillable);
  relaciones `site()`, `draftVersion()`, `publishedVersion()`, `versions()`.
- **PageVersion**: `BelongsToWorkspace, HasFactory, HasPublicUlid` (sin SoftDeletes); cast
  `schema=>array`, `published_at=>datetime`. **Guarda de inmutabilidad condicional al estado
  ORIGINAL** en `booted()`: `updating`/`deleting` lanzan si `getOriginal('status')===
  STATUS_PUBLISHED` (permite la transición draft→published).

### Versionado — "promover y bifurcar" (D2)
- **CreatePage**: crea `Page` + `PageVersion` v1 (draft, `{"schema_version":1,"sections":[]}`),
  fija `draft_version_id`.
- **SaveDraft**: UPDATE del `schema` del draft in situ.
- **PublishPage** (transacción): congela el draft (draft→published, `published_at/by`) [ya
  inmutable]; crea un **nuevo** draft `v(max+1)` copiando el schema; rota
  `published_version_id`→congelada, `draft_version_id`→nuevo, `pages.status=published`; emite
  `PagePublished`; escribe auditoría. La versión publicada son los bytes exactos
  previsualizados.

## API `/api/v1`

Binding SIEMPRE con `findByUlid` DESPUÉS de fijar contexto (patrón `SiteController::show`).

### Admin (`auth:sanctum` + `workspace`)
| Verbo | Ruta | Permiso |
|---|---|---|
| GET | `/workspaces/{workspace}/sites/{site}/pages` | `page.view` |
| POST | `/workspaces/{workspace}/sites/{site}/pages` | `page.create` |
| GET | `/workspaces/{workspace}/sites/{site}/pages/{page}` | `page.view` |
| PATCH | `/workspaces/{workspace}/sites/{site}/pages/{page}` (meta y/o `schema`) | `page.update` |
| POST | `…/pages/{page}/publish` | `page.publish` |
| POST | `…/pages/{page}/preview-link` | `page.update` |

El controlador resuelve `Site::findByUlid` y `Page::findByUlid` verificando
`page->site_id === $site->id` (404 si no; cierra fuga cross-site dentro del workspace).

### Público (sin auth, sin `workspace`; lo consume Nuxt) — ADR-006
| Verbo | Ruta | Guard |
|---|---|---|
| GET | `/public/sites/{site}/render?path=/about` | 404 si no publicada |
| GET | `/public/sites/{site}/pages/{page}/preview` | `signed` (draft; noindex/no-store) |

Patrón: `Site::withoutGlobalScopes()->where('ulid',…)->where('status','published')` → derivar
`workspace_id` en el servidor → leer dentro de `WorkspaceContext::runFor(...)`. `RenderedPage
Resource`: `{site:{id,name,base_url,theme}, page:{id,path,version_id,schema_version,
sections[]}, seo:{title,canonical,robots}, published_at}` con ETag = ULID de la PageVersion.

### Permisos, eventos, capabilities
Nuevos permisos en `RoleCatalog::ROLES`: `page.view/create/update/publish`. Reparto:
owner/admin = todos; **editor = view/create/update (SIN publish)**; viewer = view.
`ProvisionWorkspaceRbac` y el seeder los provisionan sin cambios. Evento
`Builder\Events\PagePublished{workspaceId,siteId,pageId,pageVersionId,publishedBy}` (dentro
del contexto, en la transacción) → `Listeners\RecordPagePublished` (AuditRecorder). Es la
costura para el módulo Publishing de Fase 5.

### Carga de rutas y CORS
Registrar `Builder` en `config/sassblog.php`. **Extender `ModuleServiceProvider`** para cargar
`Http/Routes/public.php` (grupo `api` + prefijo, SIN auth) + test que verifique ausencia de
auth en `/public/*`. Publicar `config/cors.php`: `paths=['api/*']`, orígenes admin `:5173` y
renderer `:3000`.

## Frontend (`@sass-blog/*`)

Consumo por SOURCE en MVP (`exports` a `./src`), project references sólo para `tsc -b`.
Dependencias unidireccionales: `design-tokens` ← `site-schema` (sin Vue) ← `site-components`
(Vue) ← `admin`/`renderer`. `site-components` NO importa APIs de app.

### page schema (forma EXACTA, ADR-002)
`{ schema_version:int, sections: Section[] }`; `Section = { id, type, variant, visible, props,
settings }`, `additionalProperties:false`. `id`=ULID `^[0-9A-HJKMNP-TV-Z]{26}$`, único en la
página. El orden del array ES el orden de render.

### `site-schema` — fuente única del registry (ADR-004, D1=A1)
`defineComponent()` con **zod**. Build emite `registry.v1.manifest.json` (admin) +
`registry.v1.{draft,publish}.schema.json` (JSON Schema 2020-12) + `registry.v1.lock`; copia
los `.json` a `backend/resources/site-schema/`. Backend valida con **`opis/json-schema`**
(D13). Componentes FASE 2: **Hero** (`hero-centered`, `hero-split` req. `image`,
`hero-minimal`) + **Text** (`text-prose`, `paragraphs: string[]` texto plano). `settings`
compartido: `spacing`, `background{role}`, `container`, `theme`.

### `design-tokens` (ADR-008)
CSS vars `--st-*` sobre `.st-site-root`; `tokensToCssVars(override)` (sólo overrides),
`resolveSiteTokens()` (deep-merge). Override por-sitio en `sites.settings.branding.tokens`.

### `site-components` (ADR-008)
`PageRenderer` (entrada única) · `SectionRenderer` (`<component :is>`, `:key=id`, placeholder
para desconocidos) · `SectionShell` · `Hero.vue` · `Text.vue`. Build ESM + CSS extraído
(`cssCodeSplit:false`), `vue` peer instancia única. **Test de render compartido** Vitest
(jsdom mount + `renderToString`).

### `renderer` (Nuxt 4, ADR-006) y `admin` (Vue 3 + Vite)
Renderer: dev por prefijo `/_site/{siteUlid}`; `pages/[...slug].vue` catch-all; 404 real
(`createError`); `<head>` mínimo; canonical = `base_url + path` (NUNCA la URL dev). Admin:
capa HTTP central única (`services/http.ts`, Bearer, 401→logout, 422→ApiError); auth store
(token en `localStorage['sb_token']`); Builder tri-panel (SectionList add/remove/reorder ↑↓/
hide · CanvasPreview = `PageRenderer` real · PropsPanel derivado del manifest).

## Decisiones (resueltas)

D1=**A1** (zod→JSON Schema + `opis/json-schema`) · D2 promover-y-bifurcar · D3=**ambos**
previews (in-admin + SSR firmado) · D4 `path` con `unique(workspace_id,site_id,path)` · D5
columna `schema` · D6 extender loader para `public.php` · D7 filtro de site explícito · D8 3
migraciones con FK reales · D9 publish sólo owner/admin · D10 Bearer+localStorage · D11
`@sass-blog/*` · D12 sin columna SEO · D13 añadir `opis/json-schema`.

## Plan de implementación (sub-slices verificables)

1. Monorepo + andamios de paquetes (pnpm, tsconfig references). Prueba: `pnpm -r typecheck`.
2. `design-tokens`. Prueba: Vitest (tokensToCssVars/resolveSiteTokens/genera tokens.css).
3. `site-schema` + build de artefactos. Prueba: Vitest (schemas válidos/ inválidos, lock).
4. `site-components` + test de render compartido Vite↔Nuxt.
5. Backend Builder DB/modelos/factories + registro del módulo. Prueba: migrate up/down,
   WorkspaceScopeTest verde, guarda de inmutabilidad (mutación), factory en contexto.
6. Backend validación del schema (SchemaRepository + PageSchemaValidator con opis + regla).
   Prueba: unit por perfil draft/publish, unicidad de id, test de frescura del lock.
7. Backend API admin (index/show/store/update) + Requests + Resources + PagePolicy + permisos.
   Prueba: CRUD, autorización (editor no publica, viewer sólo lee), aislamiento de tenant,
   unicidad de path, rechazo de draft inválido.
8. Backend publish + `PagePublished` + listener de auditoría. Prueba: promover-y-bifurcar,
   inmutabilidad de la publicada, autorización de publish.
9. Backend superficie pública + preview firmado + CORS + loader `public.php`. Prueba: render
   público, 404 cross-workspace/no-publicado (aislamiento anónimo), firma válida/vencida/
   manipulada, `/public/*` sin auth.
10. Admin scaffold + auth (HTTP central, store, guard).
11. Admin UI del Builder (tri-panel derivado del manifest).
12. Renderer scaffold + vista pública (catch-all, 404 real, `<head>`).
13. **E2E Playwright** del flujo completo contra el backend REAL (verificado por mutación).

## Deuda MVP declarada

Sin `SiteScope` global (filtro de site por ruta) · sin `deployments`/build/static-export
(Fase 5; rollback de *contenido* sí funciona vía `published_version_id`) · SEO mínimo
derivado (Fase 4) · Text `paragraphs[]` texto plano (richtext Fase 3) · sin drag&drop/media-
library/autosave · token Bearer en localStorage (XSS aceptado) · sin `site_themes` (override
en `sites.settings`) · variantes Hero extra y resto del catálogo (Fase posterior).

## Riesgos y mitigaciones

FK circular → 3ª migración con `dropForeign` en `down()` · inmutabilidad por guarda de modelo
(no constraint DB) → publicar SIEMPRE por el modelo + prueba por mutación · lecturas anónimas
→ derivar workspace del sitio + test de 404 cross-workspace · drift TS↔backend → lock +
regen-diff en CI · paridad Vite↔Nuxt → CSS extraído + vue única instancia + test de render
compartido · hidratación → SFC deterministas + `:key=ULID` + schema en payload SSR · canonical
= URL de producción, nunca `/_site/` · `schema` (palabra reservada) → backticks de Laravel ·
extender el loader → test de rutas sin auth en `/public/*`.
