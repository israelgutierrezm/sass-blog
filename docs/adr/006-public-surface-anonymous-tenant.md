# ADR-006 — Superficie pública del renderer y contexto de tenant en lecturas anónimas

- Estado: Aceptada
- Fecha: 2026-09-07
- Contexto de fase: FASE 2 (Builder / Publishing dinámico)

## Contexto

El renderer Nuxt (ADR-003) sirve sitios públicos SIN autenticación. Debe leer el page schema
publicado. Pero el dominio exige contexto de workspace (ADR-001): un `WorkspaceScope` que
lanza si no hay contexto. ¿Cómo lee un visitante anónimo sin romper el aislamiento ni recibir
`workspace_id`/`site_id` del cliente (prohibido)?

## Decisión

Endpoints públicos bajo `/api/v1/public/*`, sin `auth:sanctum` ni middleware `workspace`. El
servidor:

1. Resuelve el sitio por su **ULID público**: `Site::withoutGlobalScopes()->where('ulid', …)
   ->first()` (404 si no existe o está archivado). **FASE 2**: el gate público es a nivel de
   PÁGINA (se sirve sólo si `published_version_id` está presente); el gating por estado del
   SITIO (`status='published'` para toda la superficie) se difiere a cuando exista el ciclo
   de vida de publicación del sitio.
2. **Deriva `workspace_id` del sitio** (dato autoritativo del servidor, jamás del cliente).
3. Ejecuta la lectura dentro de `WorkspaceContext::runFor($site->workspace_id, fn () => …)`,
   con lo que el global scope queda satisfecho y `pages`/`page_versions` se scopean natural.

Resolución del sitio: por **Host** en producción (diferido a Dominios) y por **prefijo de
ruta `/_site/{siteUlid}`** en desarrollo. La página se direcciona por **path** (el visitante
navega por ruta), y el render devuelve 404 salvo que exista `published_version_id`. El
`ModuleServiceProvider` se extiende para cargar un archivo `Http/Routes/public.php` por
módulo (frontera de confianza física y auditable, sin auth).

## Alternativas consideradas

- **Aceptar `workspace_id`/`site_id` del cliente**: viola ADR-001. Rechazada.
- **Grupo sin auth dentro de `api.php`**: funciona, pero el límite "aquí no hay auth" queda
  implícito; se prefiere el archivo `public.php` explícito y un test que verifique ausencia de
  middleware de auth en `/public/*`.

## Consecuencias

- (+) Lecturas anónimas sin fuga: el tenant se fija desde datos del servidor.
- (+) La frontera pública es un archivo físico, auditable y probado.
- (−) Extender el loader afecta a todos los módulos: un `public.php` colocado por error
  cargaría rutas sin auth → se cubre con test de rutas.
- (−) Sin `SiteScope` global (ADR-005), el endpoint debe filtrar por el site resuelto.
