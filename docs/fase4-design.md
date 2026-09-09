# FASE 4 — Diseño (Media · SEO · Menús)

- Estado: **Aprobado** (decisiones D1–D4).
- Fecha: 2026-09-09
- ADRs: 016 (media storage + variantes), 017 (menús: referencias + resolución en render),
  018 (SEO en el schema + redirects/slug-history unificados).

## Objetivo y marco

Tres subdominios que acercan la plataforma a “sitios reales”: **librería de medios**,
**SEO de primera clase** y **menús jerárquicos**. Se implementan como **tres sub-fases
independientes** (4A → 4B → 4C), cada una un vertical slice con su diseño ya cubierto aquí.

Reutiliza los patrones de FASE 3: `workspace_id`+`site_id` NOT NULL + WorkspaceScope,
`ScopedToSite`, ULID público, Form Requests/Resources/Policies, **capabilities** por plan,
**eventos** para efectos cruzados y **contratos de kernel** para resolver datos en render sin
acoplar módulos. El **build estático** (sitemap/robots como archivos, export ZIP/CDN) sigue
siendo **Fase 5**; aquí el SEO se **sirve dinámicamente** (SSR), con la misma generación que
reusará el build.

## Decisiones aprobadas

- **D1 — Orden:** Media → SEO → Menús.
- **D2 — Alcance de media:** por **sitio** (`site_id` NOT NULL). Deuda: sin reutilizar un
  asset entre sitios del workspace.
- **D3 — Campo `media`:** sigue guardando **URL**; el admin gana un **selector** que inserta
  la URL pública del asset. Backward-compatible (no rompe SafeUrl). Deuda: sin integridad
  referencial (borrar un asset no avisa a las entries).
- **D4 — Menús en render:** componente **`navigation`** en el page schema, resuelto en el
  sidecar `resolved` (reusa la maquinaria del CollectionGrid). Sin header/footer global (deuda).

---

## 4A · Librería de medios — módulo `Media` (ADR-016)

`layer: domain`, `depends_on: ['Sites']`. Assets por-sitio.

| Tabla | Columnas núcleo · índices |
|---|---|
| `media_assets` (softDeletes) | `disk, path, original_filename, mime_type, size_bytes, width?, height?, alt?, title?, checksum(char64 sha256), status(ready\|processing), created_by`. `index(ws,site,created_at)`, `unique(ws,site,checksum)` (dedup por-sitio) |
| `media_variants` | `media_asset_id(FK cascadeOnDelete), variant(thumb\|medium\|large), disk, path, width, height`. `unique(media_asset_id,variant)` |

- **Subida:** `POST /workspaces/{ws}/sites/{site}/media` multipart. Form Request valida
  `file` (mime image/*, pdf; `max` por config), calcula sha256 (dedup: si existe, devuelve el
  asset existente), guarda vía `Storage::disk` (local en dev; S3-compat en prod — sin acoplar
  proveedor), lee dimensiones si es imagen, crea el asset `status=processing` y despacha el job.
- **Transformaciones por job** (`GenerateMediaVariants`, cola `database`, **idempotente** por
  `media_asset_id`): para imágenes genera `thumb 320 / medium 768 / large 1440` (no agranda),
  crea las filas `media_variants` y marca `status=ready`. Dependencia nueva **Intervention
  Image** (justificada: resize/encode robusto, no reinventar). Otros mime: sin variantes,
  `status=ready` directo.
- **API:** `index` (paginado, filtro por mime), `store` (upload), `show`, `update` (alt/title),
  `destroy` (soft delete + borra binarios en un job). **Resource** expone `url` (original) +
  `variants{thumb,medium,large}` como URLs públicas (nunca el path/disk interno).
- **URL pública:** en dev, disco `public` (symlink) → `/storage/...`; en prod, URL del disco
  S3. El backend calcula la URL; el cliente nunca arma paths.
- **Capability** `media.library`; **permisos** `media.view` (viewer+) y `media.manage`
  (owner/admin/editor).
- **Admin:** vista `MediaLibrary` (grid de assets, subir, editar alt/title, eliminar) +
  `MediaPickerModal` reutilizable; `EntryFieldControl` de tipo `media`/`media_array` gana un
  botón “Elegir de la librería” que inserta la URL (D3). El `FieldControl` del Builder (Hero
  image) igual.
- **MVP/deuda:** lista plana (sin carpetas/tags); sin cuotas de storage (capability futura);
  sin subida presignada directa a S3; sólo imágenes se transforman; `media_array` como lista
  de URLs (sin galería avanzada).

**Sub-slices 4A:** 1) andamio módulo + migraciones/modelos/factories · 2) upload + dedup +
Resource (sin variantes) · 3) job de variantes + Intervention · 4) API index/show/update/
destroy + capability/policies · 5) admin MediaLibrary + picker + integración en los campos
media · 6) E2E: subir imagen → elegirla en un artículo → verla publicada.

---

## 4B · SEO de primera clase — schema + módulo `Seo` (ADR-018)

- **SEO por página en el page schema** (`publishing.md`): el page schema gana un objeto
  **opcional** `seo` a nivel raíz (hermano de `sections`): `{ meta_title?, meta_description?,
  canonical?, robots(enum index/noindex…), og_image?, jsonld_type? }`, validado por zod
  (regenera manifest/JSON Schema/lock; `schema_version` intacto porque es aditivo-opcional).
  El `RenderedPage`/render dinámico lo emiten en el bloque `seo` (hoy mínimo).
- **SEO de entries:** derivado por convención (title→meta_title, excerpt→meta_description,
  featured_image→og_image) con **overrides opcionales** en `data` (`seo_title`,
  `seo_description`) — sin tabla extra.
- **Redirects + slug history UNIFICADOS** — módulo `Seo` (`depends_on: ['Sites']`):

  | Tabla | Columnas · índices |
  |---|---|
  | `redirects` | `from_path, to_path, status(301\|302), source(manual\|slug_change), is_active`. `unique(ws,site,from_path)`, `index(ws,site,is_active)` |

  Al cambiar el slug de una entry/página se **auto-crea** un redirect `slug_change`
  (`from_path`=viejo, `to_path`=nuevo) vía evento (`EntrySlugChanged`/`PageSlugChanged`). El
  `/render`, **antes del 404**, consulta `redirects` activos por `from_path` → devuelve
  `{ redirect:{ to, status } }`; **Nuxt emite el 301/302** real. Los manuales se gestionan por
  API (`redirect.manage`).
- **sitemap.xml / robots.txt dinámicos:** rutas públicas por sitio
  (`/public/sites/{site}/sitemap.xml`, `/robots.txt`) que enumeran páginas + entries
  **publicadas** (reusa contratos de kernel para listar sin acoplar). El renderer Nuxt mapea
  `/{prefijo}/sitemap.xml` a ese endpoint. La misma generación la reusa el build de Fase 5.
- **MVP/deuda:** JSON-LD básico (Article/WebPage); sin editor visual de OG (campos de texto);
  robots por defecto (allow + link a sitemap) con override simple en settings del sitio; sin
  hreflang/i18n.

**Sub-slices 4B:** 1) `seo` en el page schema (site-schema) + emisión en render · 2) redirects
(tabla, API manual, consulta en `/render` → Nuxt 301) · 3) slug-history por evento
(auto-redirect en cambio de slug de entry/página) · 4) sitemap.xml + robots.txt dinámicos ·
5) admin (panel SEO por página + gestión de redirects) · 6) E2E: cambiar slug → vieja URL 301
a la nueva; sitemap lista lo publicado.

---

## 4C · Menús jerárquicos — módulo `Navigation` (ADR-017)

`layer: domain`, `depends_on: ['Sites','Builder','Content']` (para resolver enlaces a página/
entry/colección).

| Tabla | Columnas · índices |
|---|---|
| `menus` | `handle(primary\|footer\|…), name`. `unique(ws,site,handle)` |
| `menu_items` | `menu_id(FK cascade), parent_id?(self, jerarquía), label, link_type(page\|entry\|collection\|url\|home), target_ulid?, url?, position`. `index(menu_id,parent_id,position)` |

- **Jerarquía:** adjacency list (`parent_id`+`position`), 2–3 niveles. Nested-set sería
  sobre-ingeniería.
- **Enlaces por referencia** (`link_type`+`target_ulid`), resueltos **en render** (respeta
  slug history). `MenuResolver` implementa el contrato de kernel `SectionDataResolver`:
  soporta el tipo `navigation`, devuelve el árbol resuelto `{ items:[{label, url, children[]}] }`.
- **Render:** componente **`navigation`** en `site-schema` (props: `menu` handle, `variant`
  horizontal/vertical); `Navigation.vue` determinista en `site-components` lo pinta desde el
  sidecar `resolved` (D4). Resolución de path: page→su path; entry→`/{route_prefix}/{slug}`;
  collection→índice; url→tal cual; home→`/`.
- **Capability:** core (todos los planes); **permisos** `menu.manage`.
- **Admin:** editor de menús (árbol arrastrable simple) por sitio; el Builder puede insertar la
  sección `navigation` eligiendo el menú por `dynamic-select`.
- **MVP/deuda:** sin mega-menús ni íconos; profundidad limitada; sin header/footer global (la
  sección se coloca donde se quiera); reordenar por posición (drag básico).

**Sub-slices 4C:** 1) andamio + migraciones/modelos · 2) API menús/items + jerarquía + policies
· 3) `MenuResolver` (contrato de kernel) + resolución de paths · 4) componente `navigation`
(site-schema) + `Navigation.vue` (site-components) + canal resolved · 5) admin editor de menús
+ sección en el Builder · 6) E2E: crear menú → añadir sección navigation → navegar en el sitio.

---

## Pruebas transversales (toda sub-fase)

Aislamiento por-site (assets, redirects, menús), gating capability (Free-403/Pro-200 donde
aplique), autorización RBAC, idempotencia de jobs (variantes), **verificado por mutación**, y
**verificación en navegador** del vertical (no sólo tests verdes).

## Deuda MVP declarada (global)

Media por-sitio sin reutilización cross-site · campo media = URL sin integridad referencial ·
sólo imágenes transformadas · sin cuotas de storage · SEO sin editor visual de OG ni i18n/
hreflang · redirects sin analítica de hits · menús sin header/footer global ni mega-menús ·
sitemap/robots dinámicos (los archivos estáticos llegan en Fase 5).

## Riesgos clave (mitigaciones)

Binarios huérfanos al borrar (job de limpieza + soft delete) · storage local vs S3 (Filesystem
abstrae; probar ambos discos) · loops de redirect (validar `from_path`≠`to_path` y cadena
máxima) · N+1 en menús/sitemap (árbol/consultas batch + test de conteo) · drift del page
schema al añadir `seo` (aditivo-opcional + lock + tests) · dependencia Intervention Image
(fijar versión; job aislado).
