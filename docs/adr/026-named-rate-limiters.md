# ADR-026 — Rate limiting: limiters CON NOMBRE y clave adecuada a cada superficie

- Estado: Aceptada
- Fecha: 2026-10-06
- Contexto de fase: revisión posterior a FASE 10
- Complementa: ADR-006 (superficie pública), ADR-020, ADR-021, ADR-022

## Contexto

Las rutas usaban el middleware anónimo `throttle:N,M`. Sin usuario autenticado, Laravel calcula
la clave como `sha1(dominio de la ruta | IP)`: **todas** las rutas con `throttle:` anónimo
comparten **un único contador por IP**, aunque sus límites sean distintos.

Esto tenía dos efectos reales:

1. **E2E «flaky».** Todo el tráfico de E2E sale de 127.0.0.1. Las llamadas del renderer
   (`analytics/collect` por cada render, `domains/resolve`) agotaban el cupo de 10/min del
   `login`. Los fallos intermitentes de login en la suite completa (FASE 9/10) que se atribuyeron
   a la carga de la máquina eran esto.
2. **Producción.** El renderer y el edge llaman al backend desde POCAS IPs (las suyas, no las del
   visitante). Con un contador por IP compartido, el tráfico de un sitio popular agotaba el cupo
   de todos: un dominio propio daba 404 a partir de ~1 visita por segundo.

## Decisión

- **Prohibido el `throttle:N,M` anónimo.** Cada superficie limitada declara un limiter con nombre
  (`RateLimiter::for`) en el ServiceProvider de su módulo. El nombre entra en la clave, así que
  ninguna superficie consume el cupo de otra.
- **La clave depende de quién llama**, no siempre de la IP:

| Limiter | Superficie | Clave | Límite |
| --- | --- | --- | --- |
| `auth-login` | `POST login` | email + IP | 10/min |
| `auth-register` | `POST register` | IP | 10/min |
| `analytics-collect` | `POST public/analytics/collect` (renderer, server-to-server) | ULID del sitio | 3000/min |
| `domains-public` | `GET public/domains/resolve` · `tls-check` (renderer / edge) | hostname consultado | 600/min |
| `seo-public` | `GET public/sites/{site}/sitemap.xml` · `robots.txt` (renderer) | sitio | 120/min |
| `newsletter-subscribe` | `POST …/newsletter/subscribe` (navegador del visitante) | IP | 30/min |
| `newsletter-token` | `confirm` · `unsubscribe` | token | 30/min |

- Los topes de **negocio** (p. ej. cuántos correos de confirmación puede recibir una dirección)
  no son rate limiting HTTP: viven en el caso de uso (ver ADR-027).
- Test de regresión: `tests/Feature/RateLimitIsolationTest.php` (una ráfaga de analítica desde
  una IP no agota ni el login ni el `resolve`).

## Alternativas consideradas

- **Subir los límites anónimos:** tapa el síntoma; el contador seguiría compartido entre rutas.
- **Excluir las IPs internas del throttle:** frágil (las IPs del edge cambian) y deja sin
  protección los endpoints públicos frente a un abuso que pase por el renderer.

## Consecuencias

- (+) Cada superficie tiene su propio cupo; el tráfico del renderer ya no tumba el login ni el
  enrutado de dominios.
- (+) La clave por sitio/hostname reparte el cupo por tenant en vez de por IP del proxy.
- (−) Más limiters que mantener; un endpoint nuevo debe declarar el suyo (revisión de código).
- (−) `analytics-collect` y `domains-public` por sitio/host permiten a un atacante gastar el
  cupo de UN sitio concreto (no el de todos); aceptado para el MVP.
