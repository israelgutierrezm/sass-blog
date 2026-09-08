# CLAUDE.md — Reglas del proyecto sass-blog

## Qué es este proyecto

Plataforma **SaaS multitenant y multisitio** para crear, administrar, publicar, alojar,
exportar y monetizar sitios web. Con un único núcleo debe poder construir sitios
corporativos, landing pages, blogs, revistas, periódicos digitales, portafolios,
directorios y sitios de colecciones dinámicas. Diseñada para crecer hacia ecommerce,
membresías, paywalls, newsletters, CRM, marketplace, plugins y headless.

**NO es Comandia.** Comandia (POS de restaurantes, en `C:\Dev\comandia`) es un producto
distinto y sin relación. No copies sus reglas de negocio ni su código aquí.

## Documentos fuente de verdad (LEER ANTES DE CUALQUIER TAREA)

1. `docs/architecture.md` — estilo arquitectónico, módulos, contrato de componentes.
2. `docs/database.md` — modelo de datos y convenciones.
3. `docs/multitenancy.md` — aislamiento de tenant.
4. `docs/builder.md` — page schema, component registry, colecciones dinámicas.
5. `docs/publishing.md` — publicación dinámica y export estático.
6. `docs/roadmap.md` — fases de implementación.
7. `docs/adr/` — decisiones arquitectónicas (ADR).

Si una instrucción contradice una ADR vigente: **DETENTE**, señálalo y redacta una ADR
nueva que la reemplace. Nunca un cambio silencioso.

## Rol esperado

Senior Architect + Tech Lead, no sólo generador de código. Si una petición tiene
problemas: dilo, explica por qué, propón alternativa, compara y recomienda. No seas
complaciente. No inventes reglas de negocio críticas: si falta información, pregunta.

## Entorno de desarrollo (decidido)

- Windows + WampServer. **PHP 8.3** (misma versión que la otra máquina de trabajo);
  `require php: ^8.3` en composer. Migración a 8.4 es futura, no bloqueante.
- Node 22, npm 10, **pnpm** para los workspaces JS.
- MySQL 8 (forzar **InnoDB**). Redis **aún no disponible** en la máquina.
- **Colas: driver `database`** por ahora. Redis + Horizon se cablean cuando llegue el
  primer job pesado (publishing / media, Fase 2+). No asumir Redis en Fase 1.

## Stack obligatorio (no cambiar sin ADR)

Backend: Laravel 13, PHP 8.3, MySQL, Redis (futuro), Horizon (futuro), Sanctum, Scout, Pest.
Admin: Vue 3, TypeScript, Composition API + `<script setup>`, Pinia, Vue Router, Vite,
Tailwind, Vitest. Renderer: Nuxt 4, TypeScript, SSR/SSG. E2E: Playwright.
Archivos: Laravel Filesystem (local en dev, compatible con S3 en prod; sin acoplar a un
proveedor cloud).

## Estructura del repositorio

Monorepo: `/backend` (Laravel) · `/admin` (Vue) · `/renderer` (Nuxt) ·
`/packages/{site-components,site-schema,design-tokens,shared-types}` · `/infrastructure` ·
`/docs`. `site-components` se usa **igual** en preview del Builder y en el renderer público:
un solo componente y una sola variante para preview y producción. Prohibido mantener dos
versiones visuales.

## Reglas arquitectónicas NO NEGOCIABLES

### Modular Monolith (sin microservicios)
- Backend por dominios en `backend/app/Modules/{Modulo}/` con Domain, Application,
  Infrastructure, Http, Events, Listeners, Jobs, database.
- Efectos cruzados entre módulos SOLO por eventos de dominio. Ningún módulo escribe
  directamente en las tablas de otro.
- **Crear módulos por vertical slice, no todos de golpe.** Prohibido llenar el repo de
  carpetas vacías "por si acaso".

### Multitenancy (shared DB / shared schema)
- Raíz de tenencia = **Workspace**. Datos de sitio scopeados por **Site**.
- `workspace_id` (y `site_id` donde aplique) NOT NULL en toda tabla de dominio.
- Global scope de workspace en todo modelo de dominio. Test estructural que falla si falta.
- El `workspace_id` se resuelve de la sesión/token por middleware; **JAMÁS** llega como
  parámetro del cliente. Prohibido confiar en IDs del frontend para validar ownership.
- Prohibida cualquier query cross-tenant en código de dominio (sólo super admin/plataforma).
- Índices compuestos de tablas transaccionales inician por `workspace_id`.

### RBAC ≠ Capabilities
- **RBAC** (¿puede el usuario?): Spatie con teams = workspace. Verificar por Policies.
- **Capabilities** (¿lo permite el plan?): servicio central. **Prohibido** `if ($plan === 'pro')`
  regado en el código. Las capabilities se declaran en catálogo versionado.

### Datos
- PK autoincrement BIGINT interno + **ULID público** en entidades expuestas por API.
  Nunca exponer IDs secuenciales.
- Tablas en plural inglés (convención Laravel). Dinero: DECIMAL(12,2).
- Timestamps en UTC; presentación con zona horaria del sitio.
- **Page schema**: la fuente de verdad de una página es **JSON de schema versionado**
  (ADR-002), nunca HTML generado. El JSON estructural del builder/colecciones SÍ es válido;
  no confundir con datos claramente relacionales (esos van en columnas con constraints).
- `audit_logs`, versiones publicadas de página y pagos son **inmutables**.
- Ningún índice sin justificación escrita en el diseño de la iteración.

## Seguridad
- Form Requests para toda entrada; API Resources para toda salida; whitelist de filtros.
- Policies + tenant isolation + authorization en **todas** las acciones. Rate limiting donde aplique.
- Sin mass assignment inseguro. Secretos de integraciones cifrados, nunca en texto plano.
- No exponer modelos Eloquent crudos por API ni detalles internos innecesarios.

## Definition of Done (por entrega)
1. Tests: unit de dominio + feature de API + **test de aislamiento de tenant** + tests de
   autorización + de capabilities + idempotencia de jobs si aplica.
2. Migration con índices justificados y constraints reales (FKs, unique, NOT NULL).
3. Form Requests (entrada) y Resources (salida).
4. Eventos emitidos documentados en el módulo.
5. Sin lógica crítica de negocio en el frontend (previsualiza; el backend decide).
6. Documentación de la iteración actualizada (decisión nueva → doc; contradicción con ADR → ADR nueva).

## Flujo de trabajo
- Vertical slices funcionales completos (roadmap en `docs/roadmap.md`). Cada slice:
  ANÁLISIS → PROPUESTA → DECISIÓN → DISEÑO → IMPLEMENTACIÓN → PRUEBAS → REVISIÓN.
- **No escribas migrations ni código de una fase cuyo diseño no esté aprobado.** Presenta
  primero el diseño (entidades, relaciones, FKs, índices, constraints, estados, permisos).
- Simplificaciones/MVP: identifícalas explícitamente, di qué deuda generan y cómo evolucionan.
  Nada de mocks haciéndose pasar por funcionalidad terminada.
- No marcar una fase como completada si el flujo real no funciona (verificar, no sólo tests verdes).
- Sin sobre-ingeniería: prohibido event sourcing, CQRS formal, Elasticsearch, Kafka,
  Kubernetes, microservicios sin ADR aprobada. Antes de instalar una dependencia: justifícala.

## Idioma
- Documentación, comentarios de negocio, mensajes de UI y validación: **español** con
  acentuación correcta y completa.
- Código (clases, tablas, variables, rutas): **inglés**.
