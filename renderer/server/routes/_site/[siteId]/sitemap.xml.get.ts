// Sitemap del sitio por el prefijo de dev/preview /_site/{id}/ (ADR-018): proxy al endpoint
// público del backend, que enumera páginas + entries publicadas. En un dominio propio se sirve
// en la raíz (server/routes/sitemap.xml.get.ts).
export default defineEventHandler((event) => {
  return proxySeoFile(event, getRouterParam(event, 'siteId') ?? '', 'sitemap.xml')
})
