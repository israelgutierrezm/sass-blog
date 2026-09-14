# ADR-022 — Newsletter: captura con doble opt-in + envío de campañas por Laravel Mail

- Estado: Aceptada
- Fecha: 2026-09-14
- Contexto de fase: FASE 8 (Newsletter)
- Complementa: ADR-006 (superficie pública), ADR-014 (capabilities), multitenancy.md

## Contexto

La plataforma publica, aloja y mide sitios (FASE 2–7). El siguiente valor es **retener
audiencia**: capturar el correo de los visitantes y enviarles campañas. Es la base de la
monetización futura (membresías, paywall). Hoy no se envía ningún correo (no hay Mailables ni
mailer configurado más allá del default de Laravel).

Dos problemas separados: **capturar+gestionar** una lista de suscriptores por sitio, y
**enviar** correos a esa lista. Van en ese orden. Y dos exigencias transversales: privacidad /
anti-spam (doble opt-in, baja fácil) y no acoplarse a un proveedor de correo concreto.

## Decisión

- **Módulo `Newsletter`** (`domain`, `depends_on: ['Sites']`). Sin eventos de dominio en el MVP.

- **Doble opt-in.** El visitante deja su email en el sitio → `pending` + correo de confirmación
  (link con `confirmation_token`) → al confirmar, `confirmed`. Sólo los `confirmed` reciben
  campañas. Cada correo lleva un link de baja (`unsubscribe_token`) → `unsubscribed`. Estándar
  anti-spam y alineado con GDPR/CAN-SPAM; evita altas basura y protege la entregabilidad.

- **Entidades.** `newsletter_subscribers` (email, estado, tokens; **unique `(site_id, email)`**),
  `newsletter_campaigns` (asunto, cuerpo, estado draft|sending|sent|failed, contadores; ULID
  público) y `newsletter_campaign_sends` (**unique `(campaign_id, subscriber_id)`**) que da
  **idempotencia** por-destinatario: re-enviar una campaña salta a quien ya recibió.

- **Envío por `SendCampaign` job idempotente y por lotes.** `draft → sending`, recorre los
  confirmados no dados de baja en chunks, crea el `campaign_send` (salta si existe) y
  `Mail::to()->send(CampaignMail)`, actualiza contadores, `sent`. Correr el job dos veces no
  duplica envíos.

- **Transporte por Laravel Mail (abstracción, sin lock-in).** El código usa `Mail::send`; el
  proveedor es config. En dev/E2E, mailer `array`/`log` (no envía de verdad; los tests usan
  `Mail::fake`). En prod, SMTP/Mailgun/SES por `config/mail.php` — como el Filesystem con los
  discos: un puerto, varios adaptadores.

- **Captura EN el sitio.** Componente `newsletter` en `site-schema` + `NewsletterForm.vue` en
  `site-components` (mismo componente en preview del Builder y en el renderer, sin dobles
  versiones). El submit hace POST al endpoint público `subscribe`.

- **Superficie pública (ADR-006).** `subscribe` (POST), `confirm` (GET, idempotente) y
  `unsubscribe` (GET) en `public.php`, sin auth, con `throttle` y validación del sitio en el
  servidor. El `email` sí llega del cliente (es su propio dato); `workspace_id`/`site_id`, jamás.

- **RBAC + plan.** Permiso `newsletter.manage` (owner/admin/editor). **Capability
  `newsletter.send` (Pro):** construir la lista (captura + gestión de suscriptores) está en todos
  los planes; **ENVIAR** campañas requiere Pro. Monetiza el valor sin frenar la captación.

## Alternativas consideradas

- **Simple opt-in (activo al enviar el formulario):** menos fricción, pero peor entregabilidad y
  expuesto a altas basura; el doble opt-in es el estándar y el coste (un correo) es bajo.
- **Acoplar a un proveedor (SDK de Mailgun/SendGrid):** rápido pero lock-in; la abstracción Mail
  de Laravel cubre SMTP y APIs por driver sin tocar el dominio.
- **Cola de envío propia / ESP externo como fuente de verdad de la lista:** sobredimensionado
  para el MVP; la lista vive en nuestra BD (multitenant) y el envío usa la cola `database`.
- **Tracking por-destinatario (aperturas/clics con pixel + redirects):** valioso pero es otra
  fase; el MVP registra sólo enviado/fallido por `campaign_send`.
- **Endpoint sin componente en el sitio:** deja la captación coja (sin dónde suscribirse); el
  componente es el mecanismo natural y se incluye.

## Consecuencias

- (+) Lista propia y multitenant; captación desde el propio sitio con el motor único de componentes.
- (+) Doble opt-in + baja fácil: entregabilidad y cumplimiento por diseño.
- (+) Envío idempotente y por lotes; sin lock-in de proveedor.
- (+) Reutiliza el patrón de superficie pública, capabilities y policies ya probado.
- (−) En dev/E2E no se envía correo real (sólo `Mail::fake`/log); el envío real se valida en staging.
- (−) Sin tracking de aperturas/clics ni segmentación en el MVP (deuda declarada).
- (−) La entregabilidad real depende de configurar SPF/DKIM/proveedor en prod (infra).
