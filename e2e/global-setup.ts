import { execSync } from 'node:child_process'
import { resolve } from 'node:path'

/**
 * Setup global del E2E: construye el CLI de render estático (ADR-019) una vez, para que el
 * export estático (NodeStaticRenderer → node render-static.mjs) tenga su bundle + CSS.
 */
export default function globalSetup(): void {
  execSync('pnpm --filter renderer build:static', {
    cwd: resolve(import.meta.dirname, '..'),
    stdio: 'inherit',
  })
}
