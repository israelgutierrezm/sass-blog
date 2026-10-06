// /robots.txt en la RAÍZ de un dominio propio (ADR-018 + ADR-020): resuelve el sitio por Host;
// su línea `Sitemap:` apunta al sitemap de ese mismo dominio. En los hosts de la app → 404.
export default defineEventHandler(async (event) => {
  const custom = await siteForCustomHost(event)
  if (!custom) {
    throw createError({ statusCode: 404, statusMessage: 'Sitio no encontrado' })
  }

  return proxySeoFile(event, custom.siteId, 'robots.txt', custom.host)
})
