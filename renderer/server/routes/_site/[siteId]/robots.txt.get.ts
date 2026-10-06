// robots.txt del sitio por el prefijo de dev/preview /_site/{id}/ (ADR-018): proxy al endpoint
// público del backend, que referencia el sitemap. En un dominio propio se sirve en la raíz
// (server/routes/robots.txt.get.ts).
export default defineEventHandler((event) => {
  return proxySeoFile(event, getRouterParam(event, 'siteId') ?? '', 'robots.txt')
})
