import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  server: {
    host: '127.0.0.1',
    port: 5173,
    // Permite importar los paquetes del workspace (fuera de admin/).
    fs: { allow: ['..'] },
  },
  // Los paquetes del workspace se consumen por SOURCE (SFC/TS): que Vite los
  // procese en vez de pre-bundlearlos con esbuild (que no entiende .vue).
  optimizeDeps: {
    exclude: ['@sass-blog/site-components', '@sass-blog/site-schema', '@sass-blog/design-tokens'],
    // Las dependencias de terceros de los paquetes excluidos se pre-empaquetan al ARRANCAR. Si no,
    // Vite las descubre al cargar la ruta diferida del builder, re-optimiza y recarga la página a
    // mitad de la navegación (con la caché fría, p. ej. tras cambiar dependencias).
    include: ['@sass-blog/site-schema > zod'],
  },
})
