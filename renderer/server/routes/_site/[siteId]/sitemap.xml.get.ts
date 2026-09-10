// Sirve el sitemap del sitio (ADR-018): proxy al endpoint público del backend, que
// enumera páginas + entries publicadas. El renderer lo expone en la ruta del sitio;
// en producción, mapeado a /sitemap.xml en la raíz del dominio del sitio.
export default defineEventHandler(async (event) => {
  const siteId = getRouterParam(event, 'siteId')
  const base = useRuntimeConfig().apiInternalBase

  try {
    const xml = await $fetch<string>(`${base}/public/sites/${siteId}/sitemap.xml`, {
      responseType: 'text',
    })

    setHeader(event, 'Content-Type', 'application/xml; charset=UTF-8')
    setHeader(event, 'Cache-Control', 'public, max-age=3600')

    return xml
  }
  catch {
    throw createError({ statusCode: 404, statusMessage: 'Sitio no encontrado' })
  }
})
