# FASE 3 — Diseño del vertical CMS (Collection Engine + Articles)

- Estado: **Aprobado** (decisiones D1–D12; D1 fila mutable, D8 texto plano, D12 preview diferido).
- Fecha: 2026-09-08
- ADRs: 009 (motor híbrido), 010 (validación dinámica), 011 (ruteo/plantillas), 012 (bindings),
  013 (datos de secciones dinámicas), 014 (gating por capability), 015 (diferir SiteScope).

## Objetivo

Vertical real: **Crear Artículo → Publicar → CollectionGrid → Home → Página de Artículo**. Un
módulo nuevo `Content` (`layer: domain`, `depends_on: ['Sites','Builder']`) reutilizando los
patrones de FASE 1/2. Sin sobre-ingeniería; deuda MVP declarada.

## Modelo de datos (`app/Modules/Content/database/migrations`, `2026_09_10_0003xx`)

Reglas: `workspace_id` NOT NULL (WorkspaceScope) **y** `site_id` NOT NULL (denormalizado);
BIGINT + `ulid`; trait `ScopedToSite`; FKs reales; `final`+`strict_types`+`newFactory()`.
Orden (pages ya existe): authors → collections → collection_fields → categories → entries →
category_entry → ALTER pages.

| Tabla | Columnas núcleo · índices |
|---|---|
| `collections` (softDeletes) | `handle, name, kind(generic\|article), route_prefix?, template_page_id?(FK→pages nullOnDelete)`. `unique(ws,site,handle)`, `unique(ws,site,route_prefix)` |
| `collection_fields` | `key, label, type(FieldType), required, config(JSON), related_collection_id?(FK restrict, solo relation), position`. `unique(collection_id,key)` |
| `authors` (softDeletes) | `user_id?, name, slug, bio?, avatar_url?, email?, links?(JSON)`. `unique(ws,site,slug)` |
| `categories` | `collection_id(FK), name, slug, description?, position`. `unique(ws,site,collection_id,slug)` |
| `entries` (softDeletes) | `collection_id(FK), title, slug, status(draft\|published\|archived), published_at?(no fillable), author_id?(FK→authors), data(JSON), created_by/updated_by`. `unique(ws,site,collection_id,slug)`, `index(ws,site,collection_id,status,published_at)` |
| `category_entry` (pivote, sin modelo) | `category_id, entry_id, ws, site`. `unique(category_id,entry_id)` |
| ALTER `pages` | `+kind(standard\|collection_template)`, `path` nullable + CHECK, `index(ws,site,kind)` |

**Versionado (D1):** entries **mutables** (fila única + `status`); sin `entry_versions`. Deuda:
editar un publicado va en vivo; evoluciona con `cms.advanced_workflow`.

**Preset Article** (`ArticleCollectionPreset`, catálogo cerrado, sembrado al crear el sitio vía
listener `SiteCreated`): colección `kind=article`, `route_prefix='blog'`, campos
`excerpt/body(richtext, texto plano)/featured_image/tags/reading_time/featured`, un
`collection_template` Page publicado con bindings, autor y categoría por defecto.

## Tres mecanismos dinámicos (resueltos en el BACKEND; site-components PUROS)

1. **Validación dinámica (ADR-010):** `EntryDataValidator` (Laravel Validator dinámico desde
   `collection_fields`, perfiles draft/publish) + Rules referenciales (RelationExistsInSite,
   MediaReference/url-scheme, InFieldOptions). Catálogo de tipos cerrado autoríado en TS →
   `field-types.v1.json` + lock → enum PHP `FieldType`. Fixture de conformidad Pest+Vitest.
2. **CollectionGrid (ADR-013):** el `/render` embebe tarjetas resueltas en el sidecar `resolved`
   (hermano de `page`) vía contrato de kernel `SectionDataResolver` (Content registra
   `CollectionGridResolver`; **Builder no depende de Content**). Sólo `published`, batch
   anti-N+1, `EntryCard{id,title,path,excerpt,image,date,author,category}`. ETag compuesto.
3. **Artículo (ADR-011/012):** template = Page `kind=collection_template`; `/render?path=`
   unificado (estático-primero → `DynamicRouteResolver`); bindings `{ "$bind": "entry.title" }`
   resueltos en backend con **lista blanca colección-consciente + accesores tipados** (sin eval).

## API

- **Admin** (`auth:sanctum` + `workspace` + **`capability:cms.collections`**), anidada bajo
  `/workspaces/{workspace}/sites/{site}`: `collections` (index/store/show/update),
  `collections/{c}/entries` (index/store/show/update + `/publish`), `categories`, `authors`.
  Binding con `findByUlid` tras contexto; `publish` acción propia. Form Requests con
  `ValidEntryData` (JSON crudo, perfil draft; publish revalida con perfil publish).
- **Público** (`Http/Routes/public.php`, sin auth): el `/public/sites/{site}/render?path=`
  extendido (estático-primero → dinámico) con sidecar `resolved` y bindings resueltos. Payload:
  `{ site, page, resolved:{[sectionId]:{items:EntryCard[],total}}, seo, published_at }`. Feed
  standalone **diferido**.
- **Permisos** (RoleCatalog): `collection.view/create/update`, `entry.view/create/update/publish`,
  `category.manage`, `author.manage`. owner/admin publican; **editor no publica** ni crea/edita
  colecciones (el schema es estructural); viewer sólo lee. `content:reprovision-rbac` para
  workspaces existentes. Evento `EntryPublished` → auditoría.

## Frontend

- **site-schema:** componente `CollectionGrid` (props = SÓLO la query: collection, mode[auto],
  category?, limit, order[whitelist], columns, showX, manualEntries); `FieldControl` gana
  `'number'`/`'dynamic-select'`+`optionsSource`; `bindings.ts` (allow-set + validador +
  placeholder). Regenerar manifest + JSON Schema + lock (`pnpm build:schema`) y **commitear**.
- **site-components (aditivo, ADR-008 intacto):** `CollectionGrid.vue` determinista (cards
  `<a :href="linkBase+item.path">`, placeholder sin datos, `:key=item.id`); `PageRenderer`/
  `SectionRenderer` ganan `resolved?`/`linkBase?` pasados sólo a componentes `category='dynamic'`
  (Hero/Text intactos). Fixture golden en el test de render compartido.
- **admin:** `services/api` (collections/entries/categories/authors), stores `content`/`entry`,
  vistas `Collections/Entries/EntryEditor/Taxonomy`, `EntryFieldControl` derivado de `fields`
  (richtext = textarea MVP; media = URL MVP; relation = dynamic-select), y `CollectionGrid` en el
  Builder vía manifest (PropsPanel resuelve `dynamic-select` por API; CanvasPreview con placeholder).
- **renderer:** `[...slug].vue` extiende `RenderedPayload` con `resolved` + `linkBase=/_site/{ulid}`;
  el artículo NO necesita ruta nueva (render unificado).
- **shared-types:** `CollectionDto/CollectionFieldDto/EntryDto/EntrySummaryDto/CategoryDto/
  AuthorDto/EntryCard`. El backend calcula `path`; el frontend nunca arma paths.

## Plan (15 sub-slices, cada uno con su prueba)

1. Andamio módulo + plataforma (middleware `capability`, `ScopedToSite`, contratos de kernel,
   `CapabilityDeniedException`→403). 2. Datos/migraciones/modelos/factories + ALTER pages.
3. Catálogo de tipos compartido + artefacto/lock. 4. Validación dinámica + Rules. 5.
   CreateCollection + preset Article + SlugGenerator. 6. API categorías/autores. 7. API
   collections/entries (draft). 8. PublishEntry + evento + auditoría. 9. Artículo:
   template-como-Page + DynamicRouteResolver + bindings. 10. Componente CollectionGrid
   (site-schema). 11. SectionDataResolver + CollectionGridResolver (embed). 12. site-components
   (CollectionGrid.vue + canal resolved). 13. Admin CMS. 14. Renderer. 15. **E2E Playwright** del
   artículo + `content:reprovision-rbac` + docs.

Pruebas transversales: aislamiento por-site (entries, pivote, referencias), anti-N+1,
`$bind` maliciosos → 422, gating Free-403/Pro-200, verificado por mutación.

### Notas de implementación (sub-slices 6–7)

- **Categorías anidadas bajo su colección** (`collections/{collection}/categories`), no
  a nivel-site plano: `collection_id` es intrínseco y su unicidad es por colección.
- **Formato de `key` de campo** validado en `StoreCollectionRequest`
  (`^[a-z][a-z0-9_]*$`): cierra la deuda de ADR-010 (sin puntos ni comodines). `type`
  restringido al catálogo cerrado `FieldType`. La mutación de campos en `update` se
  difiere (sólo metadatos); crear campos `relation` vía API también se difiere.
- **Payload de campos de la entry expuesto como `values`** (la columna es `data`): una
  clave `data` en el Resource colisiona con el envoltorio `data` de Laravel y devuelve
  la respuesta sin envolver. Simétrico en entrada/salida.
- **RBAC**: owner/admin crean/editan colecciones y publican; editor crea/edita entries
  y gestiona autores/categorías pero **no** colecciones; viewer sólo lee. `entry.publish`
  llega en el sub-slice 8.
- **`{}` vs `[]` en `data`**: para MVP se almacena el arreglo validado (un campo `json`
  con objeto vacío se guarda como `[]`); igual que la limitación conocida de PHP. Se
  revisará si algún campo `json` necesita preservar objeto vacío.

### Notas de implementación (sub-slice 9 — artículo dinámico)

- **Plantilla-como-Page**: la crea el servicio de Builder `CreateCollectionTemplate`
  (Content depende de Builder y lo invoca; no toca sus tablas). Page
  `kind=collection_template`, `path=NULL` (invariante del CHECK), publicada. Su schema
  lleva placeholders `{ "$bind": … }`, por eso NO pasa por la validación estricta del
  page schema (es plantilla del sistema, sembrada). El preset la enlaza en
  `collections.template_page_id`.
- **`DynamicRouteResolver`** (contrato de kernel) lo enlaza Content
  (`CollectionRouteResolver`) en `register()`. El render público es **estático-primero**
  (Page por path exacto) y, si no hay, **dinámico** (`/{route_prefix}/{slug}` → colección
  con plantilla + entry **publicada**). Builder consulta el contrato sólo si está
  enlazado (degradación elegante; no depende de Content). Payload con la MISMA forma que
  el estático + bloque `entry`.
- **Bindings (ADR-012)**: `EntryBindings::map` produce un allow-set CERRADO
  colección-consciente (columnas universales seguras + autor + campos `data`
  **bindeables**: escalares; nunca media/relación/multiselect/json). `BindingResolver`
  sustituye sólo nodos EXACTAMENTE `{ "$bind": "ruta" }`; ruta fuera del allow-set → null
  (sin eval, sin reflexión, sin fuga de la ruta cruda). Verificado por mutación.

## Deuda MVP declarada

Entries mutables sin versionado · richtext = texto plano (sin v-html/sanitizador) · media = URL
(library en Fase 4) · Scout diferido (feed por columnas/índices) · SiteScope global diferido
(ADR-015) · sin constructor visual de campos/templates (preset sembrado) · sólo modo `automatic`
en el panel del CollectionGrid · sin archivos de autor/categoría ni jerarquía · sin borrado de
Collection.

## Riesgos clave (mitigaciones)

XSS futuro con richtext-HTML (MVP no renderiza HTML) · inyección vía `$bind` (allow-set +
accesores tipados + pruebas maliciosas) · fuga cross-site sin SiteScope (trait + tests) · fuga de
drafts (`runFor` + `status=published`) · N+1 en el grid (batch whereIn + test de conteo) ·
ciclo Content↔Builder (contratos de kernel + candado de fronteras) · drift catálogo/artefactos
(lock + diff CI) · colisión de rutas (estático-exacto-primero + `unique(route_prefix)`).
