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

## Fase 2 — Primer vertical slice del Builder  ⬅ diseño aprobado, en implementación
Flujo real: User → Workspace → Site → Page → Add Hero → Edit Hero → Save Draft → Preview →
Publish → View Public Site. Page/PageVersion, component registry mínimo (Hero + Text),
`site-components` compartido preview/render, publish dinámico + preview SSR firmado.
**Diseño completo y decisiones (D1–D13): [`fase2-builder-design.md`](fase2-builder-design.md)**
y ADRs 004–008. Implementación en 13 sub-slices verificables.

## Fase 3 — CMS vertical slice
Collection, CollectionFields, Entry, Article preset, Category, Author básico.
Flujo: Create Article → Publish → CollectionGrid → Homepage → Article Page.

## Fase 4 — Media + Menus + SEO
Media library (metadata + transformaciones por job), Menus jerárquicos, SEO de primera clase
(sitemap, robots, redirects, slug history).

## Fase 5 — Static Publishing
Site → Publish Static → Generate → Export Artifact, reutilizando page schemas y
`site-components`.

## Futuro (contemplado, no implementado)
Dominios/SSL automáticos, analytics, multilenguaje completo, editorial avanzado, paywall,
memberships, newsletter, marketplace, plugins, headless API, white-label / agency mode,
billing real (Stripe/Mercado Pago vía adapters).
