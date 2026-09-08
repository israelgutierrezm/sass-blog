import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  server: {
    port: 5173,
    // Permite importar los paquetes del workspace (fuera de admin/).
    fs: { allow: ['..'] },
  },
  // Los paquetes del workspace se consumen por SOURCE (SFC/TS): que Vite los
  // procese en vez de pre-bundlearlos con esbuild (que no entiende .vue).
  optimizeDeps: {
    exclude: ['@sass-blog/site-components', '@sass-blog/site-schema', '@sass-blog/design-tokens'],
  },
})
