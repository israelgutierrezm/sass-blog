// Sirve el robots.txt del sitio (ADR-018): proxy al endpoint público del backend, que
// referencia el sitemap. En producción, mapeado a /robots.txt en la raíz del dominio.
export default defineEventHandler(async (event) => {
  const siteId = getRouterParam(event, 'siteId')
  const base = useRuntimeConfig().apiInternalBase

  try {
    const body = await $fetch<string>(`${base}/public/sites/${siteId}/robots.txt`, {
      responseType: 'text',
    })

    setHeader(event, 'Content-Type', 'text/plain; charset=UTF-8')
    setHeader(event, 'Cache-Control', 'public, max-age=3600')

    return body
  }
  catch {
    throw createError({ statusCode: 404, statusMessage: 'Sitio no encontrado' })
  }
})
