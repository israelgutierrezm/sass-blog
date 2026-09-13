# Edge de dominios propios + SSL (Caddy) — ADR-020

Caddy es el edge que termina TLS para los **dominios propios de los tenants** y proxya al
renderer Nuxt. El TLS es **automático y on-demand**: no se gestiona ningún certificado a
mano ni por tenant.

## Cómo funciona

1. El tenant conecta `blog.acme.com` en el admin y apunta su DNS a nuestro ingress
   (**CNAME** para subdominios, **A/ALIAS** para apex). Un job del backend lo verifica →
   estado `active`.
2. Primer handshake TLS a `blog.acme.com`: Caddy no conoce el SNI y **pregunta** al backend
   (`on_demand_tls { ask … }`): `GET {TLS_ASK_URL}?domain=blog.acme.com`.
3. El backend responde **200 sólo si el dominio está `active`**; si no, 404 y Caddy **no**
   emite nada. Este gate cierra el abuso de emisión y protege los rate-limits de Let's Encrypt.
4. Con 2xx, Caddy obtiene un cert Let's Encrypt (TLS-ALPN-01/HTTP-01), lo cachea/renueva solo
   y proxya al renderer **preservando el Host**. El renderer resuelve el sitio por Host.

## Variables de entorno

| Var | Ejemplo | Uso |
|---|---|---|
| `ACME_EMAIL` | `ops@sassblog.com` | Cuenta ACME de Let's Encrypt. |
| `TLS_ASK_URL` | `http://backend:8000/api/v1/public/domains/tls-check` | Ask-endpoint del backend. |
| `RENDERER_UPSTREAM` | `renderer:3000` | Renderer Nuxt (SSR). |
| `PLATFORM_HOST` | `sites.sassblog.com` | (Opcional) host propio de la plataforma. |

## Despliegue

```bash
ACME_EMAIL=ops@sassblog.com \
TLS_ASK_URL=http://backend:8000/api/v1/public/domains/tls-check \
RENDERER_UPSTREAM=renderer:3000 \
caddy run --config infrastructure/caddy/Caddyfile
```

El ingress (esta instancia de Caddy) debe ser el destino DNS que se muestra a los tenants
(`DOMAINS_INGRESS_CNAME` / `DOMAINS_INGRESS_IP` en el backend). Es decir: el CNAME/A del
tenant apunta aquí.

## Seguridad / operación

- **Restringir el ask-endpoint** (`/api/v1/public/domains/tls-check`) a la red del edge por
  firewall/red: aunque es sólo-lectura, no debería recibir tráfico externo. Idem se recomienda
  para `/api/v1/public/domains/resolve` (lo consume el renderer, no el público).
- **Rate-limit** de `on_demand_tls` (`interval`/`burst`) para acotar emisiones ante picos.
- Certificados y renovación los gestiona Caddy; el `ssl_status` del backend es best-effort por
  poll (MVP; un webhook de Caddy → autoritativo, evolución).

## Validación

No se prueba en local (no hay DNS público ni Let's Encrypt): la máquina de estados, la
verificación (DNS mockeado), el `resolve` y el ask-endpoint se cubren con tests del backend, y
el enrutado por Host con E2E. La emisión REAL del certificado se valida en **staging** con un
dominio de prueba apuntado a un ingress Caddy real.
