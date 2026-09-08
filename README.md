# sass-blog

Plataforma SaaS **multitenant y multisitio** para crear, administrar, publicar, alojar,
exportar y monetizar sitios web. No es un simple *website builder* ni sólo un blog: con el
mismo núcleo debe construir sitios corporativos, landing pages, blogs, revistas, periódicos
digitales, portafolios, directorios y sitios basados en colecciones dinámicas.

> Proyecto **independiente**. No tiene relación con Comandia (POS) ni comparte código con él.

## Stack

| Área | Tecnología |
|---|---|
| Backend | Laravel 13, PHP 8.3, MySQL 8, Sanctum, Scout, Horizon (diferido), Pest |
| Panel admin | Vue 3 + TypeScript + Vite + Pinia + Vue Router + Tailwind + Vitest |
| Renderer público | Nuxt 4 + TypeScript (SSR/SSG) |
| Paquetes compartidos | site-components, site-schema, design-tokens, shared-types |
| E2E | Playwright |

## Estructura

```
backend/     Laravel 13 (API + dominios modulares)
admin/       Panel administrativo (Vue 3)
renderer/    Renderer público (Nuxt 4)
packages/    Paquetes TS compartidos (preview + producción)
infrastructure/  Docker, nginx, scripts
docs/        Arquitectura, base de datos, ADRs, roadmap
```

## Documentación

Empieza por [`docs/architecture.md`](docs/architecture.md) y
[`docs/roadmap.md`](docs/roadmap.md). Las decisiones arquitectónicas están en
[`docs/adr/`](docs/adr/).

## Estado

Fase 0 (auditoría y arquitectura) completada. En curso: **Fase 1 — Foundation**
(auth, workspaces, members, sites, RBAC, capabilities, tenant isolation, audit base).
