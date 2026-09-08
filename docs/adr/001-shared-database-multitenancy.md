# ADR-001 — Multitenancy con shared database / shared schema

- Estado: Aceptada
- Fecha: 2026-09-07

## Contexto

Plataforma SaaS multitenant. Muchos workspaces, cada uno con varios sites. Se necesita
aislamiento fuerte de datos por tenant con un costo operativo bajo en las primeras fases y
espacio para clientes Enterprise dedicados en el futuro.

## Decisión

Usar **una sola base de datos con un solo esquema compartido**. La raíz de tenencia es el
**Workspace** (`workspace_id` NOT NULL en todo dato de dominio; `site_id` donde aplique). El
aislamiento se garantiza con **global scopes** por workspace y un `TenantContext` resuelto en
el backend desde el token/sesión. El `workspace_id` nunca llega del cliente. Un test
estructural falla si un modelo de dominio carece del scope o de `workspace_id`.

## Alternativas consideradas

- **Base de datos por tenant**: aislamiento máximo, pero costo operativo y de migraciones
  alto desde el día 1; innecesario para el volumen inicial.
- **Esquema por tenant** (MySQL: DB por tenant): similar sobrecarga; complica el pooling.
- **Sólo filtros en queries, sin global scope**: frágil; un olvido filtra datos entre tenants.

## Consecuencias

- (+) Simple de operar, migraciones únicas, joins triviales, buen fit para el MVP.
- (+) Camino claro a conexión por-workspace (Enterprise dedicado) sin tocar el dominio.
- (−) El aislamiento depende de disciplina de código; se mitiga con global scopes + tests
  estructurales + tests de aislamiento por módulo.
- (−) Vecinos ruidosos comparten recursos; aceptable en fases iniciales.
