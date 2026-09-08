import { writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { buildTokensCss } from '../src/css-vars'

const here = dirname(fileURLToPath(import.meta.url))
writeFileSync(resolve(here, '../src/tokens.css'), buildTokensCss())
// eslint-disable-next-line no-console
console.log('src/tokens.css generado')
