# ADR-020 — Dominios propios: enrutado por Host + SSL on-demand en el edge

- Estado: Aceptada
- Fecha: 2026-09-13
- Contexto de fase: FASE 6 (Dominios propios + SSL)
- Complementa: ADR-003 (renderer Nuxt), ADR-006 (superficie pública), publishing.md

## Contexto

`publishing.md` anticipa `SiteDomain` (estados `pending/verifying/active/failed` + `ssl_status`)
y fija que **el dominio no se acopla a la Page**. Los sitios ya se renderizan (FASE 2–5); falta
(1) mapear un dominio propio (`blog.acme.com`) a un sitio, (2) verificar que el tenant lo
controla, y (3) emitir/servir el certificado TLS **automáticamente** para miles de dominios de
terceros, sin gestión manual por tenant.

Son dos problemas separados que no hay que mezclar: **verificar+enrutar** el dominio y **emitir
el TLS**. Van en ese orden.

## Decisión

- **Módulo `Domains`** (`domain`, `depends_on: ['Sites']`) con la entidad **`SiteDomain`**:
  `workspace_id, site_id, hostname(UNIQUE global), status(pending|verifying|active|failed),
  ssl_status(none|provisioning|active|failed), verification_token, verified_at, is_primary,
  timestamps`. El `hostname` es único en TODA la plataforma (un dominio → un sitio). Un sitio
  puede tener varios (apex + www) con uno `is_primary` y redirección del otro. Capability de plan
  `site.custom_domain` (Pro).

- **Verificación por apuntado DNS.** El usuario apunta un **CNAME** (subdominio) o **A/ALIAS**
  (apex) hacia nuestro ingress; un job `VerifyDomain` resuelve el hostname por un contrato
  inyectable `DnsResolver` (real en prod, fake en tests) y confirma que apunta a nosotros →
  `verifying → active`. El propio apuntado prueba el control. Reintentos con backoff; timeout →
  `failed`. (Un TXT `sassblog-verify` queda como alternativa futura para pre-verificar sin mover
  tráfico.)

- **Enrutado por `Host`, no por path.** El renderer resuelve el sitio del header `Host`: consulta
  un endpoint público del backend `GET /public/domains/resolve?host=…` → ulid del sitio (o 404) y
  sirve las páginas a **rutas limpias en la raíz** (las mismas del export estático). Si el `Host`
  es un dominio propio de la plataforma, cae al prefijo `_site/{ulid}` (dev/preview). El render ya
  sabe pintar cualquier sitio; sólo cambia CÓMO se elige.

- **TLS automático: `Caddy` con `on_demand_tls` + ASK-ENDPOINT.** Ante un SNI desconocido, Caddy
  pregunta primero a `GET /api/v1/public/domains/tls-check?domain=…` (sólo-lectura, rápido;
  restringido a la red del edge en prod),
  que responde 200 **sólo si el `SiteDomain` está `active`**. Entonces Caddy obtiene un
  certificado **Let's Encrypt** on-demand (TLS-ALPN-01/HTTP-01, funciona porque el dominio ya
  apunta a nosotros), lo cachea y renueva solo. El gate en `active` es la pieza de seguridad:
  **nunca** se emite para un SNI arbitrario (evita hijacking y los rate-limits de Let's Encrypt).
  `ssl_status` se refresca con un poll de alcanzabilidad HTTPS (MVP; un webhook de Caddy es
  evolución).

- **Frontera de test:** la máquina de estados, la verificación (DNS mockeado), el `resolve` y el
  ask-endpoint SÍ se testean (unit/feature/E2E con Host header). La emisión REAL de certificados
  es infraestructura (Caddy en `/infrastructure`), se valida en **staging** con un dominio real,
  no en local.

## Alternativas consideradas

- **Servicio gestionado (Cloudflare for SaaS / Custom Hostnames):** el proveedor emite certs y
  hace SNI-routing por API; menos infra que operar, pero coste por hostname y menos control. Se
  deja como opción/adaptador futuro; el edge propio (Caddy) da control y coste marginal ~nulo.
- **Wildcard único para todos los tenants:** imposible — los tenants usan SU apex, no subdominios
  nuestros.
- **Emitir certificados de forma eager (no on-demand):** pre-emitir para miles de dominios
  dormidos malgasta rate-limits; on-demand es elástico.
- **Acoplar el dominio a Site/Page (columna):** rompe el desacople de publishing.md; el dominio es
  enrutado con ciclo de vida propio (verificación, SSL), va en su tabla.
- **Verificación TXT-primero:** más pasos; el apuntado DNS es un solo paso y estándar.

## Consecuencias

- (+) Certificados automáticos y ~gratis; cero operación de certs por tenant.
- (+) Dominio desacoplado; el render ya resuelve cualquier sitio (sólo cambia la resolución).
- (+) El ask-endpoint sobre `active` cierra hijacking y abuso de emisión.
- (−) Exige operar un edge Caddy (infra); el TLS real no se prueba en local (sólo staging).
- (−) El apex necesita A/ALIAS (depende del registrador; algunos no tienen ALIAS).
- (−) `ssl_status` es best-effort por poll en el MVP (no autoritativo desde Caddy hasta el webhook).
- (−) El target STATIC/CDN gestiona su propio dominio+cert (fuera de alcance de esta fase).
