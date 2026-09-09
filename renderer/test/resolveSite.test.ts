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

  it('round-trip de linkBase: /_site/{ulid} + card.path resuelve al mismo sitio y path', () => {
    const siteId = '01ABC'
    const linkBase = `/_site/${siteId}`
    const cardPath = '/blog/mi-articulo'
    // El href que arma CollectionGrid.vue: linkBase + card.path.
    const segments = `${linkBase}${cardPath}`.split('/').filter((s) => s.length > 0)

    expect(resolveSite(segments, '_site')).toEqual({ siteId, path: cardPath })
  })
})
