# FASE 9 — Editorial avanzado · Diseño

> Fuente de verdad de la iteración. Decisiones en **ADR-023**. Docs base: `architecture.md`,
> `builder.md` (contenido), `multitenancy.md`.

## Objetivo

Flujo editorial para las **entradas** (artículos): revisión (borrador → en revisión →
aprobar/pedir cambios) y **publicación programada** a fecha futura. Caso periódico/revista.

## Decisiones (resumen; detalle en ADR-023)

- **D1 · Revisión + programación** (flujo completo).
- **D2 · Gating a Pro** (`publisher.editorial`); la publicación directa sigue para todos.
- Alcance: entradas, no páginas. Se apoya en el RBAC existente (`entry.update` vs `entry.publish`).

## Estados y transiciones

`Entry.status`: `draft` · `in_review` (nuevo) · `scheduled` (nuevo) · `published` · `archived`.

| Transición | Actor (permiso) | Notas |
|---|---|---|
| draft → in_review | redactor (`entry.update`) | enviar a revisión |
| in_review → draft | redactor (`entry.update`) | retirar |
| in_review → published | editor (`entry.publish`) | aprobar y publicar ya (`published_at=now`) |
| in_review → scheduled | editor (`entry.publish`) | aprobar con `published_at` futuro |
| in_review → draft (+ `editorial_note`) | editor (`entry.publish`) | pedir cambios |
| scheduled → published | job (al vencer) | emite `EntryPublished` en su momento |
| draft → published | admin (`entry.publish`) | publicación directa (compat, hoy) |

Transiciones inválidas → rechazo en el servicio de dominio.

## Modelo de datos (migración mínima)

`entries`: añade `editorial_note` (text, nullable) + índice `(status, published_at)` para el
barrido del job. Los estados NO tocan el esquema (`status` es string). `published_at` (ya existe)
guarda la fecha programada.

## Piezas (extiende `Content`)

- **Application:** `SubmitForReview`, `WithdrawReview`, `ReviewEntry` (aprobar / programar /
  pedir cambios), `PublishScheduledEntries` (job).
- **Console:** `content:publish-scheduled` + scheduler (cada minuto).
- **Http:** endpoints de transición en `EntryController` (o un `EntryWorkflowController`), gated
  por `capability:publisher.editorial`; Policy por `entry.update`/`entry.publish`.
- **Eventos:** reusa `EntryPublished` (al ir en vivo).

## Sub-slices

1. Estados + migración `editorial_note` + servicios de transición (con validación de estado) ·
   tests de dominio (transiciones válidas/ inválidas).
2. API del flujo (submit/withdraw/approve/schedule/request-changes) + gating Pro + policies ·
   feature + capability + aislamiento.
3. Job `PublishScheduledEntries` + comando + scheduler · idempotencia + "sólo vencidos".
4. Admin: badges de estado + botones de transición + selector de fecha de programación.
5. E2E (redactor envía → editor programa → el job publica → visible en el sitio).

## Simplificaciones / deuda (MVP)

- Sin historial de revisiones (más allá de `updated_by`) ni asignación de revisores.
- Una sola nota de feedback (`editorial_note`), no un hilo de comentarios.
- Sin previsualización de la versión programada distinta del borrador (entries mutables).
- El job necesita `schedule:run` por cron; granularidad = frecuencia del scheduler (minutos).
