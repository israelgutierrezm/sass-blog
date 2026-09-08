# Multitenancy — sass-blog

## Modelo

- Estrategia: **shared database / shared schema** (ADR-001).
- Raíz de tenencia: **Workspace**. Un usuario pertenece a varios workspaces (via
  `workspace_members`, con rol). Un workspace contiene varios sites.
- Datos scopeados por sitio llevan además `site_id`.

## Reglas de aislamiento (no negociables)

1. `workspace_id` NOT NULL en toda tabla de dominio. `site_id` NOT NULL en datos de sitio.
2. Todo modelo de dominio aplica un **global scope** que filtra por el `workspace_id` del
   contexto actual. Los modelos scopeados por sitio filtran también por `site_id` cuando hay
   un site activo en contexto.
3. El `workspace_id` (y el `site_id`) se resuelven del **token/sesión por middleware**.
   **Jamás** llegan como parámetro del cliente. Un id de workspace/site en la URL se valida
   contra la membresía del usuario; no se confía en él para autorizar.
4. Prohibida cualquier query cross-tenant en código de dominio. Sólo un módulo de plataforma
   (super admin) puede agregar entre tenants, con un scope explícito de bypass auditado.
5. Índices compuestos de tablas transaccionales inician por `workspace_id`.

## Resolución de contexto

Servicio `TenantContext` (shared kernel) expone `{ user, workspace, site, role }`. Se puebla
en un middleware a partir de:
- El usuario autenticado (Sanctum).
- El workspace activo (de la ruta/`X-Workspace` validado contra membresía, o el personal por defecto).
- El site activo (opcional, para pantallas de un sitio).

La autorización usa el **rol activo** del contexto, no la suma de roles del usuario.

## Capabilities vs. permisos

- **Permiso** (RBAC/Spatie): ¿este usuario, con su rol en este workspace, puede la acción?
- **Capability** (plan): ¿el plan del workspace permite la funcionalidad? Servicio central,
  nunca `if ($plan === 'pro')`. Una acción sensible verifica **ambos**.

## Test estructural (candado permanente)

Un test recorre todos los modelos de dominio y **falla** si alguno no declara el global scope
de workspace o no tiene `workspace_id`. Se mantiene verde siempre. Además, cada módulo trae su
propio test de aislamiento (un usuario del workspace A jamás ve/afecta datos del workspace B).

## Futuro (no implementado)

Clientes Enterprise con base/infraestructura dedicada. El diseño no lo impide: la resolución
de conexión puede volverse dependiente del workspace sin tocar el código de dominio.
