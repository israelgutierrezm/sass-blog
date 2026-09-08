import { describe, expect, it } from 'vitest'
import { resolveSite } from '../app/utils/resolveSite'

describe('resolveSite', () => {
  it('resuelve el sitio y el home (path /)', () => {
    expect(resolveSite(['_site', '01ABC'], '_site')).toEqual({ siteId: '01ABC', path: '/' })
  })

  it('resuelve rutas anidadas', () => {
    expect(resolveSite(['_site', '01ABC', 'about'], '_site')).toEqual({ siteId: '01ABC', path: '/about' })
    expect(resolveSite(['_site', '01ABC', 'services', 'consulting'], '_site'))
      .toEqual({ siteId: '01ABC', path: '/services/consulting' })
  })

  it('sin prefijo reservado, siteId es null', () => {
    expect(resolveSite(['about'], '_site').siteId).toBeNull()
    expect(resolveSite([], '_site').siteId).toBeNull()
  })
})
