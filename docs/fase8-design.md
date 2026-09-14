# FASE 8 — Newsletter · Diseño

> Fuente de verdad de la iteración. Decisiones en **ADR-022**. Docs base: `architecture.md`,
> `database.md`, `multitenancy.md`, `builder.md` (componentes).

## Objetivo

Por-sitio: capturar suscriptores desde el sitio publicado (formulario + **doble opt-in**) y
enviar **campañas** de correo a los confirmados, con baja fácil en cada envío.

## Decisiones (resumen; detalle y alternativas en ADR-022)

- **D1 · Doble opt-in.** `pending` → correo de confirmación → `confirmed`. Baja por token.
- **D2 · Captura EN el sitio.** Componente `newsletter` (site-schema + site-components + renderer).
- **D3 · Gating.** Captura/gestión para todos; **enviar** requiere `newsletter.send` (Pro).
- **Transporte:** Laravel Mail (abstracción). Dev/E2E: `array`/`log` + `Mail::fake`. Prod: config.
- **RBAC:** `newsletter.manage` (owner/admin/editor).

## Modelo de datos

Todas: `workspace_id` NOT NULL (global scope), `ScopedToSite`, FK a `sites`.

### `newsletter_subscribers`
| col | tipo | notas |
|---|---|---|
| id | BIGINT PK | |
| workspace_id / site_id | FK | tenencia + sitio |
| email | string | correo del suscriptor |
| status | string(20) | pending · confirmed · unsubscribed |
| confirmation_token | char(64) | link de confirmación (doble opt-in) |
| unsubscribe_token | char(64) | link de baja (en cada envío) |
| confirmed_at / unsubscribed_at | timestamp null | |

- **Unique `(site_id, email)`** — un email una vez por sitio. Índice `(workspace_id, site_id, status)`.

### `newsletter_campaigns` (ULID público)
| col | tipo | notas |
|---|---|---|
| subject | string | asunto |
| body | text | cuerpo (HTML) |
| status | string(20) | draft · sending · sent · failed |
| recipients_count / sent_count / failed_count | unsigned int | contadores |
| sent_at | timestamp null | |
| created_by | FK users null | |

- Índice `(workspace_id, site_id, status)`.

### `newsletter_campaign_sends`
| col | tipo | notas |
|---|---|---|
| campaign_id / subscriber_id | FK | |
| status | string(20) | sent · failed |
| sent_at | timestamp | |

- **Unique `(campaign_id, subscriber_id)`** → idempotencia del envío (salta a quien ya recibió).

## Superficie pública (ADR-006, `public.php`, sin auth, throttle)

- `POST public/sites/{site}/newsletter/subscribe` — body `{ email }`. Resuelve el sitio en el
  servidor; crea `pending` (o reactiva) + `ConfirmationMail`. 202/204 siempre (no filtra existencia).
- `GET public/newsletter/confirm?token=` — `confirmed` (idempotente).
- `GET public/newsletter/unsubscribe?token=` — `unsubscribed`.

## Módulo `Newsletter` (`domain`, `depends_on: ['Sites']`)

- **Application:** `SubscribeToNewsletter`, `ConfirmSubscription`, `Unsubscribe`,
  `SendCampaign` (job idempotente, por lotes).
- **Mail:** `ConfirmationMail`, `CampaignMail` (incluye link de baja).
- **Http:** `PublicNewsletterController` (subscribe/confirm/unsubscribe) + `SubscriberController`
  y `CampaignController` (auth + workspace + `newsletter.manage`; enviar además
  `capability:newsletter.send`). Resources.
- **Policies:** `NewsletterPolicy` (`newsletter.manage`).

## Componente de captura

- `site-schema`: `components/newsletter.ts` (campos: título, texto, etiqueta del botón, mensaje
  de éxito) + registro.
- `site-components`: `NewsletterForm.vue` (input email + submit → POST público; estados
  enviando/éxito/error). Mismo componente en preview y en producción.

## Sub-slices (vertical)

1. Módulo + entidades (subscribers, campaigns, campaign_sends) + `newsletter.manage` +
   capability `newsletter.send` + policy · tests estructurales/tenant + unit.
2. Público subscribe/confirm/unsubscribe + doble opt-in + `ConfirmationMail` · feature + `Mail::fake`.
3. `SendCampaign` job + `CampaignMail` + idempotencia + contadores + gating Pro · feature + capability.
4. Admin (suscriptores + compositor de campañas).
5. Componente `newsletter` (site-schema + site-components + renderer).
6. E2E (suscribir en el sitio → confirmar → enviar campaña → verificar con `Mail::fake`/log).

## Simplificaciones / deuda (MVP)

- Dev/E2E no envían correo real (`array`/`log` + `Mail::fake`); el envío real se valida en staging.
- Sin tracking de aperturas/clics (sólo enviado/fallido por `campaign_send`).
- Una sola lista por sitio (sin segmentación ni listas múltiples).
- Plantillas de correo básicas (sin editor visual del email).
- Tokens de opt-in sin expiración configurable.
- Entregabilidad real (SPF/DKIM/proveedor) es configuración de prod (infra).
