# Publishing Engine — sass-blog

## Editar ≠ publicar

Flujo explícito: **Draft → Preview → Publish → Build → Deployment → Production**.

- **Draft**: se edita el `draft_version` de la página.
- **Preview**: render del draft sin publicar, con los componentes reales de
  `site-components` (nunca HTML falso). URLs/tokens de preview seguros.
- **Publish**: se congela una `PageVersion` (inmutable) como `published_version`.
- **Build/Deployment**: proceso en cola que materializa el sitio.

## Deployment

Entidad `Deployment` con estados: `pending`, `building`, `success`, `failed`, `rolled_back`.
Campos previstos: `site_id`, `target`, `status`, `triggered_by`, `artifact_ref`, timestamps.

Targets (conceptuales):

- **DYNAMIC**: el sitio se sirve con el renderer Nuxt (SSR/SSG) leyendo el published schema.
- **STATIC**: generación estática (HTML/CSS/JS/assets/sitemap/robots) y, a futuro,
  export ZIP / subida a CDN.

El static renderer usa **exactamente** los mismos page schemas y `site-components` que el
dinámico. No hay un constructor separado para páginas estáticas.

## Colas

Build/publicación/export corren en **jobs** idempotentes (llave por documento origen + tipo;
re-despachar nunca duplica). Hoy sobre driver `database`; Redis + Horizon cuando el volumen
lo exija. Nunca en el request HTTP.

## SEO en publicación

El published schema incluye SEO por página (meta title/description, canonical, robots, OG,
social image, schema.org/JSON-LD). Debe funcionar igual en SSR y en generación estática.
`sitemap.xml` y `robots.txt` se generan en el build. Redirects y slug history se respetan.

## Dominios (futuro)

`SiteDomain` con estados `pending/verifying/active/failed` y `ssl_status`. El dominio no se
acopla a la Page. Verificación DNS/SSL automatizada llega en su fase.

## Alcance por fase

- Fase 2: Publish dinámico de una página con Hero (vertical slice real).
- Fase 5: Publish estático + export artifact.
