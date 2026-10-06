import { beforeEach, describe, expect, it, vi } from 'vitest'
import { clearHostCache, HOST_CACHE_MAX, HOST_CACHE_TTL_MS, resolveHostCached } from '../app/utils/hostCache'

describe('resolveHostCached (host → sitio con caché)', () => {
  beforeEach(() => clearHostCache())

  it('dentro del TTL no vuelve a consultar el backend (ni distingue mayúsculas)', async () => {
    const fetcher = vi.fn(async () => 'SITE1')

    expect(await resolveHostCached('blog.acme.com', fetcher, 0)).toBe('SITE1')
    expect(await resolveHostCached('Blog.ACME.com', fetcher, HOST_CACHE_TTL_MS - 1)).toBe('SITE1')
    expect(fetcher).toHaveBeenCalledTimes(1)
  })

  it('vencido el TTL vuelve a consultar', async () => {
    const fetcher = vi.fn(async () => 'SITE1')

    await resolveHostCached('blog.acme.com', fetcher, 0)
    await resolveHostCached('blog.acme.com', fetcher, HOST_CACHE_TTL_MS + 1)
    expect(fetcher).toHaveBeenCalledTimes(2)
  })

  it('cachea el "no es un dominio activo" (null), pero NO los errores transitorios', async () => {
    const notActive = vi.fn(async () => null)
    expect(await resolveHostCached('nada.com', notActive, 0)).toBeNull()
    await resolveHostCached('nada.com', notActive, 1)
    expect(notActive).toHaveBeenCalledTimes(1)

    const flaky = vi.fn<(h: string) => Promise<string | null | undefined>>()
      .mockResolvedValueOnce(undefined) // p.ej. 429
      .mockResolvedValueOnce('SITE2')
    expect(await resolveHostCached('blog.acme.com', flaky, 0)).toBeNull()
    expect(await resolveHostCached('blog.acme.com', flaky, 1)).toBe('SITE2') // reintenta
  })

  it('acota el tamaño: Hosts aleatorios no hacen crecer la memoria sin límite', async () => {
    const fetcher = vi.fn(async (h: string) => h)
    for (let i = 0; i <= HOST_CACHE_MAX; i++) {
      await resolveHostCached(`h${i}.test`, fetcher, 0)
    }

    // El más antiguo salió de la caché: consultarlo de nuevo llama al backend.
    fetcher.mockClear()
    await resolveHostCached('h0.test', fetcher, 1)
    expect(fetcher).toHaveBeenCalledTimes(1)
  })
})
