import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import {
  buildTokensCss,
  defaultTokens,
  flattenTokens,
  resolveSiteTokens,
  tokensToCssVars,
} from '../src'

const here = dirname(fileURLToPath(import.meta.url))

describe('design-tokens', () => {
  it('tokensToCssVars emite SÓLO las variables sobreescritas', () => {
    expect(tokensToCssVars({ colors: { primary: '#ff0000' } })).toBe('--st-color-primary: #ff0000;')
  })

  it('resolveSiteTokens hace deep-merge sobre los defaults', () => {
    const resolved = resolveSiteTokens({ colors: { primary: '#ff0000' } })
    expect(resolved.colors.primary).toBe('#ff0000')
    expect(resolved.colors.surface).toBe(defaultTokens.colors.surface)
  })

  it('flattenTokens usa prefijo --st- y kebab-case', () => {
    expect(flattenTokens({ colors: { surfaceMuted: '#eee' } })).toEqual({
      '--st-color-surface-muted': '#eee',
    })
  })

  it('tokens.css committeado está fresco (coincide con buildTokensCss)', () => {
    const committed = readFileSync(resolve(here, '../src/tokens.css'), 'utf8').replace(/\r\n/g, '\n')
    expect(committed).toBe(buildTokensCss())
  })
})
