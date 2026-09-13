import { resolve } from 'node:path'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

/**
 * Build del CLI de render estático (ADR-019): compila render-static-entry.ts (que arrastra
 * PageRenderer + tokens.css) a un bundle ES ejecutable por Node y EXTRAE el CSS (tokens +
 * estilos de site-components) a un único fichero. Vue/Node quedan external.
 */
export default defineConfig({
  plugins: [vue()],
  build: {
    outDir: resolve(import.meta.dirname, 'dist'),
    emptyOutDir: true,
    cssCodeSplit: false,
    lib: {
      entry: resolve(import.meta.dirname, 'render-static-entry.ts'),
      formats: ['es'],
      fileName: () => 'render-static.mjs',
    },
    rollupOptions: {
      external: ['vue', 'vue/server-renderer', /^node:/],
      output: {
        assetFileNames: 'render-static.css',
      },
    },
  },
})
