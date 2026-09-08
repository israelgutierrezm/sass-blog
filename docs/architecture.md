# Arquitectura — sass-blog

## 1. Visión

Plataforma SaaS multitenant y multisitio para construir, publicar y monetizar sitios web
con un único núcleo. El mismo motor debe servir sitios corporativos, landing pages, blogs,
revistas, periódicos digitales, portafolios, directorios y sitios de colecciones dinámicas,
y crecer hacia ecommerce, membresías, paywalls, newsletters, CRM, marketplace y headless.

## 2. Principios rectores

1. **Modular Monolith, no microservicios.** Separación por dominios dentro de un solo
   despliegue Laravel. Los microservicios llegarían sólo con una ADR que lo justifique.
2. **Vertical slices.** Se construye funcionalidad de punta a punta (DB → API → UI →
   render) y se prueba antes de expandir. No se crean esqueletos de módulos "por si acaso".
3. **El backend decide, el frontend previsualiza.** Ninguna regla crítica vive en Vue/Nuxt.
4. **Un solo sistema de componentes.** `site-components` se consume igual en el preview del
   Builder (Vite) y en el renderer público (Nuxt). Nunca dos versiones visuales.
5. **Fuente de verdad estructurada, no HTML.** Las páginas se guardan como *page schema*
   JSON versionado (ver `builder.md` y ADR-002).
6. **Aislamiento de tenant en el backend, siempre.** Nunca confiar en el cliente.
7. **Extensible sin sobre-ingeniería.** Preparar el terreno para features futuras sin
   implementarlas antes de tiempo.

## 3. Topología del monorepo

```
backend/     Laravel 13 — API REST /api/v1, dominios modulares, jobs, publishing
admin/       Vue 3 + TS — panel de administración (SPA vía Sanctum)
renderer/    Nuxt 4 + TS — render público SSR/SSG de los sitios
packages/
  site-components/  Componentes Vue de sitio (Hero, Header, ...) — preview + público
  site-schema/      Tipos + validadores del page schema y del component registry
  design-tokens/    Tokens de diseño (colores, tipografía, radios, spacing...)
  shared-types/     Tipos TS compartidos de la API (DTOs de respuesta)
infrastructure/  Docker, nginx, scripts de despliegue
docs/        Documentación viva + ADRs
```

- El **backend** no es un workspace de pnpm; usa Composer.
- `admin` y `renderer` dependen de `packages/*` vía workspace de pnpm.
- El **contrato** entre backend y frontends es `shared-types` + el page schema de
  `site-schema`. El backend valida el schema; los frontends lo renderizan.

## 4. Dominios backend (se activan por fase)

| Dominio | Responsabilidad | Fase |
|---|---|---|
| Shared (shared kernel) | Base de módulos, ULID, WorkspaceScope/Context, capabilities | 1 |
| Tenancy | Workspaces, members, resolución de contexto de workspace | 1 |
| Identity | Users, auth (Sanctum), RBAC (Spatie teams=workspace) | 1 |
| Sites | Site, settings, branding, dominios (modelo) | 1 |
| Audit | Bitácora inmutable de operaciones relevantes | 1 |
| Billing | Plan, Capability, Subscription (sólo modelo) | 1 |
| Platform | Super admin del SaaS (agrega entre workspaces) | futuro |
| Builder | Page, PageVersion, secciones, component registry | 2 |
| Content | Collections, fields, entries, templates, bindings | 3 |
| Editorial | Articles preset, authors, categories, workflow | 3 |
| Media | Media library, transformaciones por job | 4 |
| Navigation | Menus, menu items jerárquicos | 4 |
| Seo | Meta, canonical, sitemap, robots, redirects, slug history | 4 |
| Publishing | Deployment, targets dynamic/static, build jobs | 2 / 5 |
| Domains | SiteDomain, verificación DNS/SSL | futuro |
| Analytics | Page views, sessions, conversions | futuro |
| Integrations | Webhooks, forms actions, terceros | futuro |

> El shared kernel (Platform) puede ser dependido por todos; él no depende de nadie.

## 5. Contrato de componentes compartidos (la apuesta técnica central)

- Cada sección de una página es `{ type, variant, props, settings }` validado contra el
  **component registry** (`site-schema`).
- `site-components` expone, por `type`, un componente Vue que acepta esos `props`/`settings`
  y consume `design-tokens`. Ese mismo componente se monta en:
  - **admin** (preview del Builder, Vite) — modo edición/preview.
  - **renderer** (Nuxt) — SSR/SSG de producción.
- Riesgo gestionado: compatibilidad de build Vite↔Nuxt. Mitigación: componentes SFC puros,
  sin dependencias específicas de framework de app, y un test de render compartido.

## 6. API

- Versionada bajo `/api/v1`. Autenticación con Sanctum (SPA admin) y tokens (headless futuro).
- Laravel API Resources para toda salida. Formato de error y paginación consistentes.
- Nunca se retornan modelos Eloquent crudos.

## 7. Preocupaciones transversales

- **Colas:** driver `database` hoy; Redis + Horizon cuando entre el primer job pesado.
  Procesos pesados (publishing, media, indexación, webhooks) siempre en cola, nunca en el request.
- **Búsqueda:** Laravel Scout con driver database sobre MySQL; conmutación futura a
  Meilisearch/Typesense sin acoplar el código.
- **Archivos:** Laravel Filesystem; disco local en dev, compatible con S3 en prod.
- **Auditoría:** toda operación relevante (publicar, borrar, invitar, cambiar rol/dominio)
  genera un registro inmutable.

## 8. Qué NO se hace todavía

Billing real, dominios/SSL automáticos, analytics avanzado, multilenguaje completo,
static export, editorial workflow avanzado, paywall, marketplace. La arquitectura los
contempla (ver `roadmap.md` y las notas "futuro"), pero no se implementan hasta su fase.
