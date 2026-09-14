# ADR-023 — Flujo editorial avanzado: estados de revisión + publicación programada

- Estado: Aceptada
- Fecha: 2026-09-14
- Contexto de fase: FASE 9 (Editorial avanzado)
- Complementa: ADR-009 (motor de contenido), ADR-014 (capabilities)

## Contexto

El módulo `Content` (FASE 3) modela artículos como `Entry` MUTABLE con estados
`draft/published/archived` y `published_at`. `scopePublished` ya exige `status=published` Y
`published_at <= now`, así que la visibilidad respeta una fecha futura. Para el caso
periódico/revista falta el flujo EDITORIAL: que un redactor envíe a revisión, un editor apruebe
(o pida cambios) y se programe la publicación a una fecha futura.

## Decisión

- **Dos estados nuevos** en `Entry.status`: `in_review` (enviado a revisión) y `scheduled`
  (aprobado con fecha futura). Como `status` es una columna string, no hay cambio de esquema por
  los estados; sólo se añade `editorial_note` (nullable) para el feedback de "pedir cambios" y un
  índice `(status, published_at)` para el barrido del job de programación.

- **Máquina de estados con RBAC existente** (no se inventan roles):
  - Redactor (`entry.update`): `draft → in_review` (enviar); `in_review → draft` (retirar).
  - Editor (`entry.publish`, admin/owner): `in_review → published` (aprobar y publicar ya);
    `in_review → scheduled` (aprobar con `published_at` futuro); `in_review → draft` con
    `editorial_note` (pedir cambios).
  - Transiciones inválidas se rechazan en el servicio de dominio (no sólo en la UI).

- **Publicación programada por job.** `PublishScheduledEntries` (scheduler + comando
  `content:publish-scheduled`) pasa `scheduled → published` cuando `published_at <= now` y emite
  `EntryPublished` **en el momento real** de ir en vivo (no al programar). Idempotente: sólo toca
  `scheduled` vencidos. El renderer no sirve un `scheduled` (no es `published`).

- **Se mantiene la publicación directa** actual (`PublishEntry`: `draft → published` por un
  admin). El flujo de revisión NO la reemplaza; convive con ella.

- **Capability `publisher.editorial` (Pro)** gatea los endpoints del flujo avanzado
  (submit/approve/schedule/request-changes). Sin el plan, la publicación directa sigue disponible
  para todos. Monetiza el flujo editorial sin romper el flujo básico.

- **Alcance: entradas** (contenido de colecciones), no páginas. Las páginas son estructura del
  builder y tienen su propio ciclo (versionado); el flujo editorial es de artículos.

## Alternativas consideradas

- **Sólo programación (sin revisión):** más simple, pero no es "editorial avanzado"; el control
  de revisión es justo lo que pide el caso periódico.
- **Visibilidad por sólo `published_at<=now` sin estado `scheduled` ni job:** funciona para
  ocultar, pero no da un estado explícito en el admin ni emite `EntryPublished` al ir en vivo
  (se necesitaría para invalidar caché / notificar). El job da el estado y el evento en su momento.
- **Nuevos roles de redacción (reviewer/editor-in-chief):** el catálogo de roles es CERRADO
  (ADR-001); el flujo se apoya en los permisos existentes (`entry.update` vs `entry.publish`).
  Roles editoriales finos son evolución si hacen falta.
- **Versionar entries para revisar una versión concreta:** las entries son mutables por decisión
  (ADR-009 D1); versionarlas es un cambio mayor fuera de alcance.

## Consecuencias

- (+) Flujo periódico/revista: revisión + calendario editorial, con la seguridad en el dominio.
- (+) Reutiliza estados/`published_at`/`EntryPublished` ya existentes; migración mínima.
- (+) Convive con la publicación directa; el gating por plan monetiza lo avanzado.
- (−) Sin historial de revisiones ni asignación de revisores en el MVP (deuda declarada).
- (−) El job de programación necesita `schedule:run` por cron (como el resto de programados).
- (−) La granularidad del calendario depende de la frecuencia del scheduler (minutos, no segundos).
