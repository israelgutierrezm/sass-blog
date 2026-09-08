# Base de datos — sass-blog

MySQL 8, motor **InnoDB** forzado. Charset `utf8mb4`.

## Convenciones

- **PK** autoincrement `BIGINT UNSIGNED` interna. Además, **ULID público** (`ulid`,
  `char(26)`, unique) en toda entidad expuesta por API. Nunca se expone el id secuencial.
- **Multitenancy**: `workspace_id` NOT NULL en toda tabla de dominio; `site_id` NOT NULL en
  datos scopeados por sitio. Índices compuestos inician por `workspace_id`.
- **Dinero**: `DECIMAL(12,2)`. **Timestamps** en UTC (`timestamps()`), presentación con TZ del sitio.
- **Slugs**: indexados; unicidad por su ámbito (workspace o site).
- **JSON**: permitido para *page schema*, `settings`/`props` de secciones, `data` de entries
  y `metadata` de auditoría. Prohibido para datos claramente relacionales (esos van en
  columnas con FKs, unique y NOT NULL).
- **Inmutables** (sin UPDATE/DELETE): `audit_logs`, versiones publicadas de página, pagos.
- **Soft deletes** sólo donde aporta (p.ej. `sites`), no por defecto.
- Ningún índice sin justificación escrita en el diseño de la iteración.

## Modelo Fase 1 (Foundation)

### users
`id`, `ulid`, `name`, `email` (unique), `email_verified_at`, `password`, `remember_token`,
timestamps.

### workspaces
`id`, `ulid`, `name`, `slug` (unique), `owner_id` → users, `personal` (bool),
timestamps, softDeletes.
Índices: `slug` unique, `owner_id`.

### workspace_members
`id`, `workspace_id` → workspaces, `user_id` → users, `role` (owner/admin/editor/viewer),
`joined_at`, timestamps.
Índices: unique(`workspace_id`,`user_id`); `user_id`.

### sites
`id`, `ulid`, `workspace_id` → workspaces, `name`, `slug`, `status`
(draft/published/archived), `primary_domain` (nullable), `settings` (JSON, nullable),
timestamps, softDeletes.
Índices: unique(`workspace_id`,`slug`); `workspace_id`,`status`.

### RBAC (Spatie, teams = workspace)
Tablas estándar de `spatie/laravel-permission` con `team_foreign_key = workspace_id`:
`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.

### plans / capabilities / plan_capabilities / subscriptions  (billing diferido — sólo esquema)
- `plans`: `id`, `ulid`, `key` (unique), `name`, `active`, timestamps.
- `capabilities`: `id`, `key` (unique), `name`, `description`. Catálogo cerrado y versionado.
- `plan_capabilities`: `plan_id`, `capability_id`, `limit` (nullable int para cuotas),
  unique(`plan_id`,`capability_id`).
- `subscriptions`: `id`, `ulid`, `workspace_id`, `plan_id`, `status`
  (trialing/active/past_due/canceled), `current_period_end`, timestamps.

### audit_logs (inmutable)
`id`, `ulid`, `workspace_id` (nullable para acciones de plataforma), `site_id` (nullable),
`actor_id` → users (nullable para sistema), `action`, `entity_type`, `entity_id`,
`metadata` (JSON), `ip`, `created_at`.
Índices: `workspace_id`,`created_at`; `entity_type`,`entity_id`.

## Modelo Fase 2+ (referencia, se detalla en su fase)

- `pages` (draft_version_id, published_version_id) / `page_versions` (schema JSON, inmutable al publicar).
- `collections` / `collection_fields` / `entries` (columnas universales + `data` JSON).
- `menus` / `menu_items` (jerárquicos).
- `media` (metadata desacoplada del filesystem).
- `forms` / `form_fields` / `form_submissions`.
- `deployments` (target dynamic/static, estados).
- `site_domains`, `redirects`, `slug_history`.

Ver `builder.md` y `publishing.md` para su forma exacta.
