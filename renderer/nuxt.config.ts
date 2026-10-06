// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-01-01',
  ssr: true,
  devtools: { enabled: false },
  devServer: { host: '127.0.0.1', port: 3000 },

  // Los paquetes del workspace se consumen por SOURCE (SFC/TS): Nuxt los transpila.
  build: {
    transpile: ['@sass-blog/site-components', '@sass-blog/site-schema', '@sass-blog/design-tokens'],
  },

  // El renderer es host-routed por diseño (ADR-020): sirve sitios por el header Host,
  // incluidos dominios propios de tenants. El dev server de Vite bloquea por defecto los Host
  // desconocidos (anti DNS-rebinding). En dev/E2E los dominios propios se simulan bajo `.test`
  // (p.ej. `e2e-….example.test`), así que sólo se abren `.test` y `.localhost`: con `true`
  // cualquier web podría leer el dev server por DNS rebinding. Sólo afecta al dev server: el
  // build de producción corre en Nitro (sin lista de Hosts), que responde a cualquier Host.
  vite: {
    server: {
      allowedHosts: ['.test', '.localhost'],
    },
  },

  // CSS base de tokens (--st-* sobre .st-site-root).
  css: ['@sass-blog/design-tokens/tokens.css'],

  runtimeConfig: {
    // SSR (server -> API). Override con NUXT_API_INTERNAL_BASE.
    apiInternalBase: 'http://127.0.0.1:8000/api/v1',
    public: {
      // Cliente. Override con NUXT_PUBLIC_API_BASE.
      apiBase: 'http://127.0.0.1:8000/api/v1',
      // Prefijo de ruta reservado para resolver el sitio en dev (ADR-006).
      reservedPrefix: '_site',
      // Hosts propios de la plataforma; cualquier otro Host se trata como dominio de
      // tenant y se resuelve por Host (ADR-020). Override con NUXT_PUBLIC_APP_HOSTS (CSV).
      appHosts: ['localhost:3000', '127.0.0.1:3000'],
    },
  },
})
