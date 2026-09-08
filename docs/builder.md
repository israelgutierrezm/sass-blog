# Page Builder & Content — sass-blog

## Page schema (fuente de verdad)

Una página **no** guarda HTML. Guarda un schema estructurado y versionado (ADR-002):

```json
{
  "schema_version": 1,
  "sections": [
    {
      "id": "01J...",
      "type": "hero",
      "variant": "split-image",
      "visible": true,
      "props": {},
      "settings": {}
    }
  ]
}
```

- `id` de sección: ULID.
- `type` + `variant`: resueltos contra el **component registry**.
- `props`: contenido/configuración de la sección (validado contra el props schema del componente).
- `settings`: presentación (spacing, background, container...).

## Component registry

Cada componente declara (en `packages/site-schema`):

```
type              hero
name              Hero
category          header|content|media|cta|contact|footer|dynamic
variants          [hero-centered, hero-split, hero-fullscreen, hero-video, hero-minimal]
propsSchema       (validación de props)
settingsSchema    (validación de settings)
defaults          (props/settings por defecto por variante)
responsive        (capacidades responsive)
```

El admin **deriva** parte de sus controles del schema del componente; no se hardcodea un
formulario distinto por variante si puede derivarse. **Variante ≠ componente**: `hero-split`
es una variante de `Hero`, no un componente aparte.

### Componentes iniciales (suficientes para un sitio corporativo)

`Header · Hero · Text · ImageText · Features · Services · CollectionGrid · CTA · Contact · Footer`.

La arquitectura permite agregar más después sin refactor.

## Versionado de páginas

- `Page` referencia `draft_version_id` y `published_version_id`.
- Cada cambio importante genera una `PageVersion`. La versión **publicada es inmutable**.
- Estados: `draft`, `published`, `archived`. Soporte futuro: rollback y *scheduled publishing*.

## Global sections / symbols (modelo contemplado)

Una sección global (p.ej. footer corporativo) tiene una fuente única, se reutiliza en varias
páginas, se actualiza globalmente y una instancia puede **detach**earse a local. No hace falta
toda la UI al inicio, pero el modelo lo contempla.

## Colecciones dinámicas (Content Engine)

Modelo **híbrido**: campos universales del `Entry` como columnas reales
(`id, site_id, collection_id, title, slug, status, published_at, created_by, updated_by`) +
campos personalizados en `data` JSON **validado** contra el schema de la Collection. No EAV puro.

Tipos de campo: text, textarea, richtext, integer, decimal, boolean, date, datetime, money,
email, url, slug, select, multiselect, media, media_array, relation, json. Estrategia futura
para indexar campos filtrables/ordenables si el volumen lo exige.

`Articles` es una **colección especializada / capability** sobre este motor (authors,
categories, tags, featured, reading time, related, editorial workflow, scheduled) — no un
módulo rígido aparte.

## Contenido dinámico en páginas

Una sección puede consumir una colección. Modos:

- **manual**: el editor elige entradas.
- **automatic**: por query (`collection`, filtros, `limit`, `order`).
- **mixed**: algunas posiciones manuales, el resto por query. (Esencial para periódicos.)

## Templates de colección + bindings

Una Collection puede tener template y ruta (p.ej. `programas/{slug}` → `program-detail`).
Los componentes del template vinculan props a la Entry mediante bindings declarativos y
**seguros** (lista blanca de campos), p.ej. `title = entry.title`,
`duration = entry.data.duration`. **No** un motor de expresiones arbitrario.
