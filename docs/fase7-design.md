# FASE 7 — Analítica · Diseño

> Fuente de verdad de la iteración. Decisiones en **ADR-021**. Docs base: `architecture.md`,
> `database.md`, `multitenancy.md`, `publishing.md`.

## Objetivo

Capturar pageviews de los sitios **publicados** y mostrar métricas agregadas por sitio en el
admin (serie temporal diaria, top páginas, top referrers, totales), **sin cookies ni PII**.

## Decisiones (resumen; detalle y alternativas en ADR-021)

- **D1 · Captura server-side en el renderer.** Evento *fire-and-forget* por render → ingesta
  backend. Sin JS/cookies/consentimiento; bots filtrados por UA.
- **D2 · Raw + rollup diario.** `analytics_events` (inmutable) → job idempotente →
  `analytics_daily_stats`. Dashboard lee rollups. Raw con retención (90 días).
- **D3 · Únicos sin PII.** `visitor_hash = HMAC(APP_KEY + site + fecha, IP + UA)`: rota a diario,
  irreversible, no cross-site/cross-day.
- **D-plan.** Básica (30 días: views/visitantes/top páginas) para todos; `analytics.advanced`
  (Pro) → referrers + rango libre + export CSV.
- **D-RBAC.** `analytics.view` → owner/admin/editor.

## Modelo de datos

Ambas tablas: `workspace_id` NOT NULL (global scope), `ScopedToSite`, FK a `sites`.

### `analytics_events` (inmutable, append-only)
| col | tipo | notas |
|---|---|---|
| id | BIGINT PK | interno |
| workspace_id | FK | tenencia |
| site_id | FK | sitio |
| path | string(2048) | ruta pública normalizada (con `/`) |
| occurred_at | timestamp | UTC |
| referrer_host | string(255) null | solo host del referrer (sin query) |
| visitor_hash | char(64) | HMAC hex; sin PII |
| is_bot | bool | heurística UA |

- Índice `(workspace_id, site_id, occurred_at)` — barrido del rollup y poda por rango.
- Retención: `PruneRawEvents` borra `occurred_at < now - retention_days`.

### `analytics_daily_stats` (rollup)
| col | tipo | notas |
|---|---|---|
| id | BIGINT PK | |
| workspace_id | FK | |
| site_id | FK | |
| stat_date | date | día UTC |
| path | string(2048) | ruta |
| views | unsigned int | total pageviews |
| visitors | unsigned int | únicos por hash/día |

- **Unique `(site_id, stat_date, path)`** → upsert idempotente del rollup.
- Índice `(workspace_id, site_id, stat_date)` — consulta por rango en el dashboard.

## Módulo `Analytics` (`domain`, `depends_on: ['Sites']`)

- **Domain:** `VisitorHasher` (HMAC), `BotDetector` (heurística UA).
- **Application:** `RecordPageView` (valida sitio publicado, filtra bot, append),
  `RollUpDailyStats` (job idempotente por fecha), `PruneRawEvents` (retención),
  `MetricsQuery` (lee rollups por rango: serie, top páginas, top referrers, totales).
- **Http:** `PublicAnalyticsController.collect` (público, throttle) en `public.php`;
  `AnalyticsController.summary` (auth + workspace + `analytics.view`) para el admin;
  `AnalyticsResource`.
- **Console:** `analytics:rollup {date?}` + registro en scheduler (`daily`).
- **Policies:** `AnalyticsPolicy` (`viewAny` ⇐ `analytics.view`).

## Superficie pública / ingesta

`POST /api/v1/public/analytics/collect` (throttle) — body: `{ site, path, referrer? }`. El backend
resuelve el sitio por ULID (debe existir y estar publicado), calcula `visitor_hash` desde IP+UA de
la request, detecta bot, y hace append. Lo invoca el renderer server-to-server tras un render
exitoso; en prod se restringe a la red del edge (infra).

## Config (`config/sassblog.php` → `analytics`)

```
'analytics' => [
    'enabled' => (bool) env('ANALYTICS_ENABLED', true),
    'retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 90),
    'bot_pattern' => env('ANALYTICS_BOT_PATTERN', '<regex UA por defecto>'),
],
```

Gating de plan: `basic_range_days = 30`; referrers/rango-libre/export ⇐ `analytics.advanced`.

## Sub-slices (vertical)

1. Módulo + migraciones (events + daily_stats) + modelos + `analytics.view` + `AnalyticsPolicy`
   + `analytics` en config · tests estructurales/tenant + unit (`VisitorHasher`, `BotDetector`).
2. Ingesta `collect` + `RecordPageView` + bot filter + throttle · feature + aislamiento.
3. Rollup `RollUpDailyStats` + comando `analytics:rollup` + scheduler + `PruneRawEvents` ·
   idempotencia.
4. Query + API admin (`summary` por rango) + Resource + gating `analytics.advanced` · feature +
   capability.
5. Renderer: disparo server-side no bloqueante de `collect` tras el render.
6. Admin `AnalyticsView` (serie temporal + top páginas/referrers + totales + selector de rango).
7. E2E: publicar → visitar (renderer) → rollup → el dashboard muestra la visita.

## Simplificaciones / deuda (MVP)

- "Hoy" se ve al día siguiente (dashboard por rollups). → Evolución: agregación live de hoy.
- Bots por heurística UA (imperfecta); captura = render, no "humano post-JS". → Evolución: beacon.
- Únicos = hash diario (estimación, sin recurrencia entre días).
- Rollup por scheduler/comando (sin warehouse; prohibido Elasticsearch/Kafka por CLAUDE.md).
- Ingesta pública: restringir a la red del edge en prod (infra), como `resolve`/`tls-check`.
