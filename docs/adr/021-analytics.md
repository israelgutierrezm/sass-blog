# ADR-021 — Analítica de tráfico: captura server-side privacy-first + rollups diarios

- Estado: Aceptada
- Fecha: 2026-09-13
- Contexto de fase: FASE 7 (Analítica)
- Complementa: ADR-003 (renderer Nuxt), ADR-006 (superficie pública), multitenancy.md

## Contexto

La plataforma ya publica y aloja sitios (FASE 2–6). El siguiente valor natural es **medir**
su tráfico: cuántas visitas recibe cada sitio, qué páginas y qué referrers, en el tiempo. Ya
existe la capability `analytics.advanced` (Pro) en el catálogo, sin implementación detrás.

Dos fuerzas en tensión: (1) **privacidad** — no queremos cookies, banners de consentimiento ni
almacenar PII (IP), ni construir un perfil de usuario entre sitios; (2) **fidelidad** — saber
cuántas personas reales visitan. No se pueden maximizar ambas; elegimos privacidad primero y una
estimación honesta de visitantes, evolucionable.

## Decisión

- **Módulo `Analytics`** (`domain`, `depends_on: ['Sites']`). No emite eventos de dominio (la
  ingesta no dispara efectos cruzados); evita sobre-ingeniería.

- **Captura server-side en el renderer.** En cada render público exitoso el renderer dispara un
  evento *fire-and-forget* (no bloquea la respuesta, fallo tragado) a un endpoint de ingesta del
  backend `POST /public/analytics/collect`. Sin JS de cliente, sin cookies, sin consentimiento, a
  prueba de adblockers. Contra: cuenta bots → se filtran por heurística de User-Agent. (Un beacon
  de cliente para precisión "humano post-JS" queda como evolución encima, no MVP.)

- **Modelo raw + rollup.** `analytics_events` (inmutable, append-only) recibe cada visita; un job
  idempotente `RollUpDailyStats` agrega el día anterior en `analytics_daily_stats`
  (unique `site_id, stat_date, path`). El dashboard lee **rollups** (pequeños y permanentes); el
  raw tiene **retención** configurable (job `PruneRawEvents`, por defecto 90 días). El rollup corre
  por scheduler diario y por comando `analytics:rollup {date?}` (manual / tests).

- **Visitantes únicos sin PII.** `visitor_hash = HMAC(APP_KEY + site_id + fecha, IP + UA)`: rota
  cada día, irreversible, no cross-site ni cross-day. Estima únicos diarios sin guardar IP ni
  cookies — no es *tracking*. MVP cuenta únicos por día; no hay recurrencia entre días.

- **RBAC + plan.** Permiso `analytics.view` (owner/admin/editor) verificado por `AnalyticsPolicy`.
  Analítica **básica** (últimos 30 días: views, visitantes, top páginas) para **todos los planes**;
  `analytics.advanced` (Pro) desbloquea **referrers**, **rango de fechas sin límite** y **export
  CSV**.

- **Ingesta como superficie pública** (ADR-006): `collect` va en `public.php`, sin auth, con
  `throttle`, y valida que el `site` (ULID) exista y esté publicado. Lo llama el renderer
  server-to-server; en prod se restringe a la red del edge/renderer por infraestructura (igual que
  `resolve`/`tls-check`).

## Alternativas consideradas

- **Beacon de cliente (`navigator.sendBeacon`):** mide humanos tras JS y capta referrer/pantalla,
  pero exige JS en la página, banner de consentimiento y lo tumban los adblockers. Se deja como
  evolución opcional sobre la captura server-side.
- **Solo rollup (upsert en ingesta, sin raw):** más simple y sin retención que gestionar, pero
  pierde el detalle por-evento y complica el conteo de únicos. El raw + rollup es evolucionable.
- **Cookies / identificador persistente de visitante:** daría recurrencia y sesiones, pero es
  exactamente el *tracking* con PII que queremos evitar; obliga a consentimiento.
- **Almacén analítico dedicado (ClickHouse/Elasticsearch, warehouse):** prohibido por CLAUDE.md
  sin ADR y sobredimensionado para el volumen del MVP; MySQL con rollups basta y es evolucionable.
- **Contar en el edge (logs de Caddy):** desacopla del render pero ata la analítica a la infra de
  hosting propio y no cubre el modo preview `_site/{ulid}`.

## Consecuencias

- (+) Sin cookies ni PII: nada de banners de consentimiento; cumplimiento sencillo por diseño.
- (+) Captura a prueba de adblockers y sin JS; funciona igual en dominio propio y en preview.
- (+) Rollups pequeños y permanentes → dashboard barato; raw acotado por retención.
- (+) Reutiliza la capability `analytics.advanced` ya existente para monetizar.
- (−) Cuenta bots (mitigado por heurística UA, imperfecta); no distingue "humano post-JS" hasta el
  beacon.
- (−) "Hoy" se ve al día siguiente (dashboard por rollups); la agregación live de hoy es evolución.
- (−) Únicos son una estimación por hash diario, sin recurrencia entre días.
- (−) La ingesta pública necesita restringirse a la red del edge en prod (infra), como el resto de
  la superficie pública.
