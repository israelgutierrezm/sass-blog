# ADR-027 — Newsletter: acciones por POST, baja en un clic, topes anti-abuso y envío como mucho una vez

- Estado: Aceptada
- Fecha: 2026-10-06
- Contexto de fase: revisión posterior a FASE 10
- Reemplaza: de ADR-022, los métodos de la **superficie pública** (`confirm` y `unsubscribe` por
  GET) y el detalle del **envío** (registrar el `campaign_send` después de enviar). El resto de
  ADR-022 (doble opt-in, entidades, Laravel Mail, capability `newsletter.send`) sigue vigente.

## Contexto

La revisión del módulo encontró:

1. **Los escáneres de enlaces mutaban el estado.** Los filtros de correo (Defender Safe Links,
   pasarelas corporativas, algunos webmails) hacen GET a los enlaces al recibir el mensaje. Con
   `confirm`/`unsubscribe` por GET, un escáner confirmaba suscripciones solo (vacía el doble
   opt-in) o daba de baja a suscriptores reales.
2. **Sin baja en un clic.** Gmail y Yahoo exigen a los envíos masivos `List-Unsubscribe` +
   `List-Unsubscribe-Post` (RFC 8058).
3. **Relay de correo.** `subscribe` es público y con CORS abierto: cualquier web podía disparar
   confirmaciones contra una víctima (bombardeo) o usar el asunto (nombre del sitio, lo elige el
   tenant) como spam.
4. **Doble envío.** Dos clics en «Enviar», o un reintento del job, podían mandar la campaña dos
   veces: el `campaign_send` se registraba DESPUÉS de enviar, y el job podía quedarse para
   siempre en `sending` si moría.
5. **El enlace de confirmación viejo reactivaba** a quien se había dado de baja.
6. El formulario del sitio no funcionaba en el **export estático** (sin JS) y el preview simulaba
   un alta que no ocurría.

## Decisión

- **GET muestra, POST actúa.** `GET confirm|unsubscribe?token=` sólo pinta una página con un
  botón; la mutación va por `POST` a la misma URL. El `POST` de `unsubscribe` atiende también el
  one-click de RFC 8058.
- **Baja en un clic:** `CampaignMail` lleva `List-Unsubscribe: <url>` y
  `List-Unsubscribe-Post: List-Unsubscribe=One-Click`.
- **La baja es definitiva** hasta un alta nueva (otro token, otro correo): un `confirm` con un
  token viejo sobre un `unsubscribed` responde 404.
- **Topes de negocio en `SubscribeToNewsletter`** (además del limiter HTTP de ADR-026): como mucho
  3 correos de confirmación por dirección y hora sumando TODOS los sitios, y 200 por sitio y hora.
  Al superarlos no se envía, pero la respuesta es idéntica (no revela nada). Las claves viven en
  la caché; la de destinatario usa un hash (no guarda el email). El correo va **en cola**.
- **Envío como mucho una vez por destinatario:**
  - el controlador hace un *compare-and-swap* atómico `draft|failed → sending`; sólo una
    petición lo gana (las demás, 422) y entonces encola el job;
  - el job **reclama** cada destinatario insertando su `campaign_send` en `pending` ANTES de enviar
    (la unique `(campaign_id, subscriber_id)` hace que sólo un worker gane) y luego lo marca
    `sent`/`failed`;
  - `tries = 1`, `timeout = 900`; `failed()` deja la campaña en `failed` con los contadores
    parciales. **Reenviar una campaña `failed` la reanuda** sin repetir a nadie.
  - Contrapartida aceptada: si el proceso muere entre reclamar y enviar, ese destinatario no
    recibe la campaña (mejor perder uno que duplicar a todos).
- **Formulario con mejora progresiva:** `NewsletterForm` es un `<form method="post">` real hacia
  `subscribe`; sin JS el backend responde una página HTML («Revisa tu correo», con enlace de
  vuelta sólo si el Referer es http/https). El manifest del export estático incluye
  `site.api_base` para que el HTML estático sepa a dónde postear. En el preview del Builder
  (sin contexto) avisa de que es una vista previa en vez de simular el alta.
- **Contadores del panel:** `GET …/newsletter/subscribers/stats` agrega por estado en BD (el
  listado está paginado y no sirve para contar).

## Alternativas consideradas

- **Mantener GET con un token de un solo uso:** el escáner consumiría igualmente el token.
- **Captcha en el formulario:** fricción y dependencia externa; los topes por destinatario y por
  sitio cortan el abuso sin tocar al visitante legítimo.
- **Al menos una vez (reintentar a los `pending`):** duplicaría correos al reanudar; para
  campañas se prefiere como mucho una vez.

## Consecuencias

- (+) El doble opt-in vuelve a significar algo; los escáneres no confirman ni dan de baja.
- (+) Cumple los requisitos de Gmail/Yahoo para envíos masivos.
- (+) El endpoint público ya no sirve para bombardear ni como relay.
- (+) Sin envíos duplicados; una campaña interrumpida se reanuda.
- (−) Confirmar y darse de baja desde el enlace del correo cuesta un clic más (el botón).
- (−) Un destinatario reclamado por un proceso que murió no se reintenta.
- (−) Sin JS, un email inválido devuelve el error JSON de la API (raro: el navegador ya valida
  `type="email"`).
