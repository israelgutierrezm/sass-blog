// /sitemap.xml en la RAÍZ de un dominio propio (ADR-018 + ADR-020): resuelve el sitio por Host
// y pide el sitemap con ese dominio como base de las <loc>. En los hosts de la app no hay sitio
// en la raíz (se usa /_site/{id}/sitemap.xml) → 404.
export default defineEventHandler(async (event) => {
  const custom = await siteForCustomHost(event)
  if (!custom) {
    throw createError({ statusCode: 404, statusMessage: 'Sitio no encontrado' })
  }

  return proxySeoFile(event, custom.siteId, 'sitemap.xml', custom.host)
})
