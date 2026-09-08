import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { fieldTypes, fieldTypesArtifact, fieldTypeKeys } from '../src'

const here = dirname(fileURLToPath(import.meta.url))

describe('field-types (catálogo cerrado)', () => {
  it('tiene 18 tipos con claves únicas', () => {
    expect(fieldTypes).toHaveLength(18)
    expect(new Set(fieldTypeKeys()).size).toBe(18)
  })

  it('cada tipo declara label y control', () => {
    for (const t of fieldTypes) {
      expect(t.label.length).toBeGreaterThan(0)
      expect(typeof t.control).toBe('string')
    }
  })

  it('el artefacto commiteado en el backend está fresco (== fieldTypesArtifact)', () => {
    const committed = readFileSync(
      resolve(here, '../../../backend/resources/site-schema/field-types.v1.json'),
      'utf8',
    ).replace(/\r\n/g, '\n')
    expect(committed).toBe(fieldTypesArtifact())
  })
})
