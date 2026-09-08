import { createHash } from 'node:crypto'
import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { zodToJsonSchema } from 'zod-to-json-schema'
import { buildManifest } from '../src/manifest'
import { pageSchemaForProfile } from '../src/validate'

const here = dirname(fileURLToPath(import.meta.url))
const distDir = resolve(here, '../dist')
const backendDir = resolve(here, '../../../backend/resources/site-schema')

mkdirSync(distDir, { recursive: true })
mkdirSync(backendDir, { recursive: true })

const manifest = JSON.stringify(buildManifest(), null, 2)
const draft = JSON.stringify(zodToJsonSchema(pageSchemaForProfile('draft'), 'PageSchemaDraft'), null, 2)
const publish = JSON.stringify(zodToJsonSchema(pageSchemaForProfile('publish'), 'PageSchemaPublish'), null, 2)

const artifacts: Record<string, string> = {
  'registry.v1.manifest.json': manifest,
  'registry.v1.draft.schema.json': draft,
  'registry.v1.publish.schema.json': publish,
}

for (const [name, content] of Object.entries(artifacts)) {
  writeFileSync(resolve(distDir, name), content)
}

// El backend valida contra el JSON Schema generado (ADR-004). Contrato commiteado.
writeFileSync(resolve(backendDir, 'registry.v1.draft.schema.json'), draft)
writeFileSync(resolve(backendDir, 'registry.v1.publish.schema.json'), publish)

const lock = createHash('sha256').update(Object.values(artifacts).join('\n')).digest('hex')
writeFileSync(resolve(distDir, 'registry.v1.lock'), `${lock}\n`)

// eslint-disable-next-line no-console
console.log('site-schema: artefactos generados. lock =', lock.slice(0, 16))
