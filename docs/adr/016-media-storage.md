# ADR-016 — Librería de medios: storage por-sitio y variantes por job

- Estado: Aceptada
- Fecha: 2026-09-09
- Contexto de fase: FASE 4 (Media)

## Contexto

El producto necesita una librería de medios (imágenes, PDFs) para el CMS y el Builder. Hoy los
campos `media`/`media_array` y el Hero image guardan una **URL** cruda escrita a mano. Falta
almacenamiento gestionado, metadatos y derivados (thumbnails).

## Decisión

Módulo `Media` con assets **por-sitio** (`site_id` NOT NULL, consistente con la multitenancy
del resto). Almacenamiento vía **Laravel Filesystem** (`Storage::disk`): disco local en dev,
S3-compatible en prod, **sin acoplar** a un proveedor. Subida **multipart** al backend
(`POST .../media`), con **dedup por checksum sha256** dentro del sitio.

Las **transformaciones** (thumb 320 / medium 768 / large 1440) se generan en un **job
idempotente** (`GenerateMediaVariants`, cola `database`, llave = asset id) usando **Intervention
Image**. El asset nace `status=processing` y pasa a `ready` al terminar. Otros mime se guardan
sin variantes.

El **campo `media` sigue guardando la URL pública** del asset (no una referencia): el admin
gana un selector que inserta esa URL. Backward-compatible con SafeUrl (ADR-010).

## Alternativas consideradas

- **Librería compartida por workspace**: reutilizable entre sitios, pero añade scoping
  cross-site, cuotas y complejidad; se descarta para el MVP.
- **Referencia al asset por ULID en el campo `media`**: da integridad referencial (bloquear
  borrado de assets en uso) pero cambia semántica, validación y el render (Hero/EntryCard);
  se difiere.
- **Subida presignada directa a S3**: optimización de prod; se difiere.
- **Transformación on-demand (URL de imagen con parámetros)**: acopla a un servicio de imágenes;
  el job pre-genera un set fijo, más simple y portable.

## Consecuencias

- (+) Assets gestionados con metadatos + derivados, portables entre discos.
- (+) Dedup evita duplicados por-sitio; jobs idempotentes no re-duplican.
- (−) Sin reutilización de un asset entre sitios del workspace (deuda D2).
- (−) El campo `media` (URL) no tiene integridad referencial: borrar un asset no actualiza las
  entries que lo referencian (deuda D3; el picker mitiga el flujo feliz).
- (−) Nueva dependencia (Intervention Image), aislada en el job.
