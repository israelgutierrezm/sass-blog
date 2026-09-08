// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-01-01',
  ssr: true,
  devtools: { enabled: false },

  // Los paquetes del workspace se consumen por SOURCE (SFC/TS): Nuxt los transpila.
  build: {
    transpile: ['@sass-blog/site-components', '@sass-blog/site-schema', '@sass-blog/design-tokens'],
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
    },
  },
})
