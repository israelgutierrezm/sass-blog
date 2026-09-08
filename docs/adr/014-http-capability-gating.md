# ADR-014 — Gating por capability en HTTP

- Estado: Aceptada
- Fecha: 2026-09-08
- Contexto de fase: FASE 3 (Content) — aterriza un pendiente de FASE 1

## Contexto

FASE 1 dejó el servicio `Capabilities` (`allows`/`authorize`) pero sin aplicación en HTTP.
`Content` es la primera funcionalidad gateada por plan (`cms.collections`). Además,
`CapabilityDeniedException` hoy es `RuntimeException` → se renderizaría como **500**.

## Decisión

- **Middleware reutilizable `capability`** (alias), que corre **después** de `workspace`
  (necesita contexto): `capability:cms.collections`. Defensa en profundidad: los servicios
  también llaman `Capabilities::authorize()`.
- **Mapear `CapabilityDeniedException` → 403** en `bootstrap/app.php` (`withExceptions`).
- **Las lecturas públicas de contenido publicado NO se gatean por plan.** Bloquear contenido ya
  publicado si el plan baja rompería sitios en vivo y es hostil (consistente con páginas). Sólo
  se gatea la escritura/admin.
- Comando **`content:reprovision-rbac`** para sincronizar los permisos nuevos en workspaces
  **existentes** (`ProvisionWorkspaceRbac` sólo cubre los nuevos).

## Alternativas consideradas

- **Gatear también los reads públicos por plan**: rompe sitios publicados al bajar de plan.
- **Sólo verificación en el servicio (sin middleware)**: pierde la frontera declarativa en la
  ruta y la respuesta 403 temprana.

## Consecuencias

- (+) Gating declarativo y consistente; 403 correcto en vez de 500.
- (+) Los sitios publicados siguen sirviéndose aunque cambie el plan.
- (−) Hay que re-provisionar RBAC de workspaces existentes (comando + test).
