# FASE 6 — Dominios propios + SSL · Diseño

Fuente: `publishing.md` + **ADR-020**. Decisiones (forks aprobados): **Caddy on-demand TLS**
gateado por ask-endpoint; **verificación por apuntado DNS**.

**Objetivo (vertical slice):** un tenant conecta `blog.acme.com` a su sitio, lo verifica
apuntando DNS, y el sitio se sirve por ese dominio con HTTPS automático. El dominio es
**enrutado** (`Host → site`) + una **máquina de estados** de verificación; el TLS lo resuelve el
edge. El dominio NO toca la Page ni el schema.

## Módulo `Domains`

`layer: domain`, `depends_on: ['Sites']`.

| Tabla | Columnas · índices |
|---|---|
| `site_domains` | `ulid, workspace_id, site_id(FK cascade), hostname(UNIQUE global), status('pending'|'verifying'|'active'|'failed'), ssl_status('none'|'provisioning'|'active'|'failed'), verification_token, verified_at?, is_primary(bool), created_by?, timestamps`. `unique(hostname)`, `index(workspace_id, site_id)` |

- **`hostname` único en toda la plataforma** (un dominio → un sitio). Reclamar un host ya `active`
  de otro sitio se bloquea (first-verified-wins).
- **`is_primary`**: el canónico del sitio; los demás redirigen a él (apex↔www).
- **Estados**: `pending → verifying → active | failed`. Sólo el job y las acciones de dominio los
  mueven. `ssl_status`: `none → provisioning → active | failed`, refrescado por poll de
  alcanzabilidad HTTPS (MVP).

## Verificación (apuntado DNS)

Al añadir `blog.acme.com` → `SiteDomain(pending)` + instrucciones:
- **Subdominio**: CNAME `blog.acme.com → ingress.sassblog.com`.
- **Apex**: A/ALIAS `acme.com → <IP ingress>`.

`VerifyDomain` (job, cola `database`, reintentos+backoff) resuelve el hostname por el contrato
**`DnsResolver`** (real: `dns_get_record`; fake en tests) y comprueba que apunta a nuestro
ingress → `active`. Timeout ⇒ `failed`. Acción `recheck` re-encola la verificación.

## Superficies públicas (sin auth, sólo-lectura, rate-limited)

- `GET /api/v1/public/domains/resolve?host=blog.acme.com` → `{ site: <ulid> }` si hay un
  `SiteDomain` `active`; 404 si no. Lo consume el renderer para resolver el sitio por `Host`.
- `GET /api/v1/public/domains/tls-check?domain=blog.acme.com` → 200 sólo si `active`; 404 si no.
  Lo consume Caddy (`on_demand_tls` "ask") antes de emitir cert. **Jamás** 200 para no
  verificados. En prod se **restringe a la red del edge** por infraestructura (firewall).

Ambos exponen sólo el ulid del sitio (ya público); frontera de confianza como ADR-006.

## Renderer (enrutado por Host)

`resolveSite` gana un modo por host: si el `Host` no es la app/preview, consulta `resolve` →
sirve las páginas del sitio a **clean paths en la raíz** (idénticas al export estático). Con Host
propio de plataforma, mantiene el prefijo `_site/{ulid}` (dev/preview). Cachea la resolución
host→site por request/TTL corto.

## Admin

Vista de dominios por sitio: añadir hostname, ver `status`/`ssl_status`, instrucciones DNS
(CNAME/A según apex o subdominio), botón "Re-verificar", marcar primario, quitar.

## Infraestructura (`/infrastructure`, NO testeable en local)

Config de **Caddy** con `on_demand_tls { ask http://backend/internal/tls-check }` y el reverse
proxy al renderer preservando el `Host`. Documentado + versionado; el cert real se valida en
**staging** con un dominio de prueba. En dev se sigue con `_site/{ulid}`.

## Sub-slices 6

1. **Módulo `Domains` + `SiteDomain`** (migración/modelo/estados/factory) + capability
   `site.custom_domain` (gating) + API CRUD (añadir/listar/quitar/primario/recheck) + Policy +
   `DnsResolver` (contrato + fake) + job `VerifyDomain`. Tests: estados, unicidad, aislamiento,
   verificación con DNS fake, capability Free-403/Pro-200.
2. **Endpoints públicos** `resolve` + `tls-check` (rate-limit, sólo-lectura, gate en `active`) +
   tests (incl. que un dominio no-`active` NO resuelve ni pasa el tls-check).
3. **Renderer**: `resolveSite` por `Host` (consulta `resolve`, fallback `_site/{ulid}`) + tests.
4. **Admin**: vista de dominios (añadir + estado + instrucciones + recheck).
5. **Infra**: Caddyfile con `on_demand_tls` + ask-endpoint en `/infrastructure` + README de
   despliegue/staging. (Sin tests automáticos; verificación en staging.)
6. **E2E**: añadir dominio → (DNS fake) verificar `active` → el renderer resuelve un `Host`
   sembrado y sirve el sitio a la raíz. (El cert real no se E2E-a; el enrutado + verificación sí.)

## Capability

`site.custom_domain` (ya en el enum `Capability`; sembrada + otorgada a Pro por los seeders).
Gating HTTP `capability:site.custom_domain`. RBAC: `site.update` (o un `domain.manage`) autoriza.

## Deuda MVP declarada

- `ssl_status` best-effort por poll (webhook de Caddy → autoritativo, después).
- Sin target STATIC/CDN con dominio (ese camino lo gestiona el CDN; futuro).
- Apex vía A/ALIAS (depende del registrador); sin auto-detección de proveedor DNS.
- Sin renovación/expiración monitorizada en la app (Caddy renueva; salud por poll).
- Verificación TXT-primero y multi-región del edge: futuro.

## Pruebas transversales

Aislamiento por-site, unicidad global de hostname, capability Free-403/Pro-200, RBAC, verificación
con `DnsResolver` fake, gate del ask-endpoint (**verificado por mutación**), y el enrutado por
Host en E2E. La emisión TLS real queda fuera (infra/staging), declarado.
