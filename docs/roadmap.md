# Roadmap — sass-blog

Se trabaja por **vertical slices**. Cada fase se completa, prueba y documenta antes de
expandir. "Completada" significa flujo real funcionando, no sólo tests verdes.

## Fase 0 — Auditoría y arquitectura  ✅
Monorepo, documentación (`architecture`, `database`, `multitenancy`, `builder`, `publishing`,
`roadmap`) y ADRs 001–003. Toolchain verificado (PHP 8.3, Node 22, MySQL 8; colas `database`).

## Fase 1 — Foundation  ✅
Autenticación por tokens (Sanctum), Users, Workspaces, WorkspaceMembers, Sites, RBAC
(Spatie teams=workspace, roles por workspace), Capabilities (enum + resolver + servicio),
tenant isolation (WorkspaceContext/Scope/BelongsToWorkspace), Audit base. Billing como
esquema (Plan/Capability/Subscription) + suscripción free automática.
Efectos cruzados por evento `WorkspaceCreated` (RBAC e suscripción). API `/api/v1`:
register/login/logout/me, workspaces, sites (anidados, resolución de workspace en ruta).
**Entregado:** 8 migrations con constraints, factories, seeders (permisos/capabilities/planes),
Form Requests, Resources, Policies, y **25 pruebas verdes** (aislamiento, autorización,
capabilities, unicidad de slug, auth) verificadas por mutación.
**Pendiente menor:** middleware de capability-gating reutilizable, endpoints update/delete de
Site, y candado estructural de fronteras de módulos (ModuleBoundaries) — se suman al entrar
en uso en Fase 2.

## Fase 2 — Primer vertical slice del Builder  ✅
Flujo real **funcionando de punta a punta** (verificado por E2E Playwright y por mutación):
User → Workspace → Site → Page → Add Hero → Edit → Save Draft → Publish → View Public Site.
**Diseño y decisiones (D1–D13):** [`fase2-builder-design.md`](fase2-builder-design.md), ADRs 004–008.
**Entregado (13 sub-slices):**
- Paquetes `@sass-blog/*`: design-tokens, site-schema (registry único con zod + JSON Schema),
  site-components (Hero/Text; test de render compartido Vite↔Nuxt), shared-types.
- Backend módulo Builder: `pages`/`page_versions` (versión publicada inmutable, promover-y-bifurcar),
  validación con opis, API admin (CRUD/publish/preview), superficie pública + preview firmado.
- Admin (Vue): auth, capa HTTP central, Builder tri-panel derivado del manifest.
- Renderer (Nuxt SSR): vista pública por `/_site/{ulid}` con los mismos site-components.
- E2E Playwright del flujo completo (backend+admin+renderer reales).
**Pruebas:** backend 58 (Pest) + JS 27 (Vitest) + 1 E2E. Deuda MVP declarada en el doc de diseño.

## Fase 3 — CMS vertical slice  ✅
Collection, CollectionFields, Entry, Article preset, Category, Author básico.
Flujo: Create Article → Publish → CollectionGrid → Homepage → Article Page.
**Diseño y decisiones (D1–D12):** [`fase3-content-design.md`](fase3-content-design.md), ADRs 009–015.
Módulo `Content` (motor híbrido, validación dinámica, CollectionGrid embebido, template-como-Page
con bindings seguros). Implementado en 15 sub-slices, cada uno verificado por mutación.
Vertical **verificado en navegador** (admin → publicar → renderer SSR con bindings) y en **E2E
Playwright**. Incluye 13b: pantallas CRUD de taxonomía (autores/categorías) y el panel del
CollectionGrid en el Builder (dynamic-select por API + control numérico).

## Fase 4 — Media + Menus + SEO  ✅
Media library (metadata + transformaciones por job), Menus jerárquicos, SEO de primera clase
(sitemap, robots, redirects, slug history).

**Diseño aprobado** (`docs/fase4-design.md`, ADRs 016–018). Tres sub-fases en orden
**Media → SEO → Menús**. Decisiones: media por-sitio; campo `media` = URL + selector; menús como
sección `navigation` (sidecar `resolved`). SEO servido dinámicamente (los archivos estáticos
sitemap/robots quedan en Fase 5). Suite E2E: 5 verticales (builder, content, media, seo, menus) verde.

- **4A Media** ✅ — librería por-sitio, dedup por checksum, variantes por job, picker en el admin, E2E.
- **4B SEO** ✅ — `seo` en el page schema, redirects (API manual + render 301 con anti-open-redirect
  y anti-bucle), slug-history por evento de kernel, sitemap.xml/robots.txt dinámicos, admin (panel
  SEO + gestión de redirects), E2E (slug→301 + sitemap).
- **4C Menús** ✅ — módulo `Navigation` (menús+ítems jerárquicos), API + policies (`menu.manage`),
  `MenuResolver` (contrato de kernel, paths en vivo), componente `navigation` + `Navigation.vue`
  (canal `resolved`), admin (editor de menús + sección en el Builder), E2E (menú→sección→navegar).

## Fase 5 — Static Publishing
Site → Publish Static → Generate → Export Artifact, reutilizando page schemas y
`site-components`.

## Futuro (contemplado, no implementado)
Dominios/SSL automáticos, analytics, multilenguaje completo, editorial avanzado, paywall,
memberships, newsletter, marketplace, plugins, headless API, white-label / agency mode,
billing real (Stripe/Mercado Pago vía adapters).
