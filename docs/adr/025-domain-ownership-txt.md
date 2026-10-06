# ADR-025 — Dominios propios: verificación de PROPIEDAD por TXT y unicidad sólo entre activos

- Estado: Aceptada
- Fecha: 2026-10-06
- Contexto de fase: revisión de seguridad posterior a FASE 10
- Reemplaza: la decisión **«Verificación por apuntado DNS»** de ADR-020 (y la alternativa descartada
  «Verificación TXT-primero»). El resto de ADR-020 (enrutado por `Host`, TLS on-demand con
  ask-endpoint sobre `active`, frontera de test) sigue vigente.

## Contexto

ADR-020 activa un dominio cuando su CNAME/A apunta a nuestro ingress, con el argumento de que «el
propio apuntado prueba el control». No lo prueba: el apuntado es **público** y dice a dónde va el
tráfico, no **quién** lo configuró. Consecuencias:

1. **Dominio colgante (subdomain takeover).** Un cliente deja la plataforma (o borra el dominio)
   pero no quita el CNAME. Cualquier otro tenant con plan Pro conecta ese hostname → «apunta a
   nosotros» → `active` → Caddy le emite un certificado válido y sirve SU contenido en el dominio
   de otra organización (phishing con TLS legítimo).
2. **Reclamo adelantado.** Es habitual apuntar el DNS antes de conectarlo en el panel. Quien lo
   conecte primero se lo queda.
3. **Bloqueo.** `hostname` es UNIQUE global desde la creación: una reclamación que nunca se
   verificará impide indefinidamente al dueño real conectar su propio dominio.

La columna `verification_token` existía desde FASE 6, pero no se usaba.

## Decisión

- **Prueba de propiedad por TXT, por reclamación.** Cada `SiteDomain` tiene un
  `verification_token` aleatorio generado por el servidor (nunca lo elige el cliente). El tenant
  publica:

  | Tipo | Nombre | Valor |
  | --- | --- | --- |
  | TXT | `_sassblog-verify.{hostname}` | `sassblog-verify={token}` |

  Sólo quien controla la zona DNS puede publicarlo. Un TXT viejo que quede publicado tras un
  abandono no le sirve a nadie más: otra reclamación tiene **otro** token.

- **Activar = propiedad (TXT) + enrutado (CNAME/A).** `VerifyDomain` comprueba primero el TXT y
  después el apuntado. Con ambos → `active`. Si no, `failed` con un **`failure_reason`**:
  `ownership` (falta el TXT), `routing` (no apunta al ingress) o `taken` (el hostname ya está
  activo en otro sitio). Así el panel dice exactamente qué corregir. El TXT **debe permanecer**:
  cada re-verificación lo vuelve a comprobar.

- **Unicidad sólo entre ACTIVOS.** Se quita el UNIQUE global de `hostname`. Una columna generada
  `active_hostname = IF(status = 'active', hostname, NULL)` con UNIQUE garantiza en la BD «un
  dominio activo → un sitio» (los NULL no colisionan). Varias reclamaciones del mismo hostname
  pueden coexistir, cada una con su token: gana la que demuestra la propiedad. Si al activar ya
  hay otro activo → `failed`/`taken`; hay que desconectarlo allí primero. Esto también permite
  migrar un dominio entre sitios publicando el TXT nuevo antes de desconectar el viejo.

- **Un sitio no reclama dos veces el mismo hostname**: UNIQUE `(workspace_id, site_id, hostname)`,
  validado en el alta (422).

- `resolve` y `tls-check` buscan por `active_hostname`. El gate de emisión de certificados sigue
  siendo «sólo `active`» (ADR-020), pero ahora `active` implica propiedad demostrada.

### Índices (justificación)

| Índice | Columnas | Por qué |
| --- | --- | --- |
| `site_domains_active_hostname_unique` | `active_hostname` | Invariante «un activo por hostname» + búsqueda de `resolve`/`tls-check` (camino caliente del renderer y del edge). |
| `site_domains_site_hostname_unique` | `workspace_id, site_id, hostname` | Sin duplicados por sitio. Su prefijo `(workspace_id, site_id)` sirve el listado scopeado y la FK de `workspace_id`, así que **sustituye** a `site_domains_site_idx`. |

Se elimina `site_domains_hostname_unique` (era la causa del bloqueo).

## Alternativas consideradas

- **Token estable por workspace (HMAC de workspace + hostname).** Evita re-publicar el TXT al
  reconectar en el mismo workspace, pero añade gestión de claves. Por ahora, un token por
  reclamación es más simple y suficiente.
- **Que una reclamación nueva sustituya a las no verificadas.** Sin cambio de esquema, pero un
  atacante podría re-reclamar en bucle e invalidar el token del dueño real.
- **Proveedor gestionado (Cloudflare for SaaS)** que valida la propiedad por nosotros: sigue
  siendo la opción futura de ADR-020.

## Consecuencias

- (+) Cierra el takeover de dominios colgantes y el reclamo adelantado: activar exige controlar el DNS.
- (+) Una reclamación sin verificar ya no bloquea al dueño real.
- (+) El panel explica el fallo exacto (`failure_reason`).
- (−) Un paso más para el tenant: además del CNAME/A, un registro TXT que debe mantener.
- (−) Al reconectar un dominio en otra reclamación hay que actualizar el valor del TXT.
- (−) La columna generada amarra la regla de unicidad a MySQL (stack fijado: MySQL 8, InnoDB).
- Los dominios ya `active` antes de esta ADR siguen activos; la regla aplica desde la siguiente
  verificación (alta o «Re-verificar»).
