# ADR-007 — Preview del draft por URL firmada temporal

- Estado: Aceptada
- Fecha: 2026-09-07
- Contexto de fase: FASE 2 (Builder)

## Contexto

`publishing.md` exige "URLs/tokens de preview seguros": ver el **draft** sin publicar,
renderizado por el camino SSR real (preview honesto), y de forma compartible pero no pública.

## Decisión

Se ofrecen **dos previews**:

1. **In-admin instantáneo**: el admin monta el mismo `PageRenderer` con el draft en memoria,
   para el bucle rápido de edición.
2. **SSR firmado**: el endpoint admin `preview-link` acuña una **URL firmada temporal**
   (`URL::temporarySignedRoute`, TTL ~15 min) hacia el endpoint público de preview, que valida
   la firma (`signed`), deriva el workspace del sitio (ADR-006), lee el **draft** dentro de
   `runFor`, y responde **sólo lectura** con `X-Robots-Tag: noindex,nofollow` y
   `Cache-Control: no-store`.

Es **stateless** (sin tabla, sin criptografía casera).

## Alternativas consideradas

- **Token HMAC propio con `{workspace,site,page,version,jti}`**: permite scope por-versión y
  revocación por denylist, pero exige diseño/almacenamiento cripto. **Diferido** hasta
  necesitar revocación anticipada o scope por-versión.
- **Sólo in-admin**: no honra "tokens de preview seguros" ni valida el SSR real del draft.

## Consecuencias

- (+) Idiomático Laravel, sin estado, sin cripto propia; valida el camino SSR del draft.
- (+) `noindex/no-store` evita indexación/caché del borrador.
- (−) Sin revocación inmediata: si el link se filtra, expone el draft hasta que expira
  (mitigado por TTL corto + sólo lectura). Deuda documentada.
