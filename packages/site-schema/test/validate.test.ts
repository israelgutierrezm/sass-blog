import { describe, expect, it } from 'vitest'
import { buildManifest, componentTypes, validatePageSchema } from '../src'

const ULID = '01ARZ3NDEKTSV4RRFFQ69G5FAV'
const ULID_2 = '01BX5ZZKBKACTAV9WEVGEMMVRZ'

function heroSection(id = ULID) {
  return {
    id,
    type: 'hero',
    variant: 'hero-centered',
    visible: true,
    props: { heading: 'Hola' },
    settings: {},
  }
}

describe('validatePageSchema', () => {
  it('acepta un draft válido con una sección hero', () => {
    const result = validatePageSchema({ schema_version: 1, sections: [heroSection()] }, 'draft')
    expect(result.valid).toBe(true)
  })

  it('acepta un draft vacío pero RECHAZA un publish vacío', () => {
    expect(validatePageSchema({ schema_version: 1, sections: [] }, 'draft').valid).toBe(true)
    expect(validatePageSchema({ schema_version: 1, sections: [] }, 'publish').valid).toBe(false)
  })

  it('rechaza un tipo desconocido', () => {
    const bad = { schema_version: 1, sections: [{ ...heroSection(), type: 'carousel' }] }
    expect(validatePageSchema(bad).valid).toBe(false)
  })

  it('rechaza props inválidas (heading requerido faltante)', () => {
    const bad = { schema_version: 1, sections: [{ ...heroSection(), props: {} }] }
    expect(validatePageSchema(bad).valid).toBe(false)
  })

  it('rechaza claves extra (strict)', () => {
    const bad = { schema_version: 1, sections: [{ ...heroSection(), extra: true }] }
    expect(validatePageSchema(bad).valid).toBe(false)
  })

  it('rechaza un id que no es ULID', () => {
    expect(validatePageSchema({ schema_version: 1, sections: [heroSection('no-ulid')] }).valid).toBe(false)
  })

  it('rechaza ids de sección duplicados', () => {
    const result = validatePageSchema({
      schema_version: 1,
      sections: [heroSection(ULID), heroSection(ULID)],
    })
    expect(result.valid).toBe(false)
    expect(result.errors.some((e) => e.message.includes('duplicado'))).toBe(true)
  })

  it('acepta dos secciones con ids distintos', () => {
    const result = validatePageSchema({
      schema_version: 1,
      sections: [heroSection(ULID), heroSection(ULID_2)],
    })
    expect(result.valid).toBe(true)
  })
})

function gridSection(props: Record<string, unknown> = {}, id = ULID) {
  return {
    id,
    type: 'collection-grid',
    variant: 'collection-grid-cards',
    visible: true,
    props: { collection: 'articles', ...props },
    settings: {},
  }
}

describe('CollectionGrid', () => {
  it('acepta una sección con la query mínima (collection)', () => {
    expect(validatePageSchema({ schema_version: 1, sections: [gridSection()] }, 'publish').valid).toBe(true)
  })

  it('exige collection', () => {
    const bad = { schema_version: 1, sections: [{ ...gridSection(), props: {} }] }
    expect(validatePageSchema(bad).valid).toBe(false)
  })

  it('rechaza un order fuera de la lista blanca', () => {
    expect(validatePageSchema({ schema_version: 1, sections: [gridSection({ order: 'random' })] }).valid).toBe(false)
  })

  it('rechaza limit fuera de rango y columns > 4', () => {
    expect(validatePageSchema({ schema_version: 1, sections: [gridSection({ limit: 0 })] }).valid).toBe(false)
    expect(validatePageSchema({ schema_version: 1, sections: [gridSection({ columns: 5 })] }).valid).toBe(false)
  })

  it('rechaza props extra (strict)', () => {
    expect(validatePageSchema({ schema_version: 1, sections: [gridSection({ nope: 1 })] }).valid).toBe(false)
  })
})

function navSection(props: Record<string, unknown> = {}, id = ULID) {
  return {
    id,
    type: 'navigation',
    variant: 'navigation-horizontal',
    visible: true,
    props: { menu: 'primary', ...props },
    settings: {},
  }
}

describe('Navigation', () => {
  it('acepta una sección con un menú (handle)', () => {
    expect(validatePageSchema({ schema_version: 1, sections: [navSection()] }, 'publish').valid).toBe(true)
  })

  it('exige menu', () => {
    expect(validatePageSchema({ schema_version: 1, sections: [{ ...navSection(), props: {} }] }).valid).toBe(false)
  })

  it('rechaza una variante desconocida', () => {
    expect(validatePageSchema({ schema_version: 1, sections: [{ ...navSection(), variant: 'navigation-mega' }] }).valid).toBe(false)
  })

  it('rechaza props extra (strict)', () => {
    expect(validatePageSchema({ schema_version: 1, sections: [navSection({ nope: 1 })] }).valid).toBe(false)
  })
})

describe('SEO por página', () => {
  const withSeo = (seo: unknown) => ({ schema_version: 1, seo, sections: [heroSection()] })

  it('acepta un objeto seo válido', () => {
    const result = validatePageSchema(
      withSeo({ meta_title: 'Título', meta_description: 'Desc', robots: 'noindex,follow', og_image: 'https://x/y.png' }),
      'publish',
    )
    expect(result.valid).toBe(true)
  })

  it('acepta un schema sin seo (opcional)', () => {
    expect(validatePageSchema({ schema_version: 1, sections: [heroSection()] }, 'publish').valid).toBe(true)
  })

  it('rechaza un robots fuera de la lista blanca', () => {
    expect(validatePageSchema(withSeo({ robots: 'index' })).valid).toBe(false)
  })

  it('rechaza claves extra en seo (strict)', () => {
    expect(validatePageSchema(withSeo({ meta_title: 'X', nope: true })).valid).toBe(false)
  })

  it('rechaza una canonical/og_image que no es URL', () => {
    expect(validatePageSchema(withSeo({ canonical: 'no-url' })).valid).toBe(false)
    expect(validatePageSchema(withSeo({ og_image: 'no-url' })).valid).toBe(false)
  })
})

describe('registry & manifest', () => {
  it('expone los tipos hero, text, collection-grid y navigation', () => {
    expect(componentTypes()).toEqual(expect.arrayContaining(['hero', 'text', 'collection-grid', 'navigation']))
  })

  it('el manifest lista componentes con variantes y defaults', () => {
    const manifest = buildManifest()
    const heroEntry = manifest.components.find((c) => c.type === 'hero')
    expect(heroEntry).toBeDefined()
    expect(heroEntry?.variants.map((v) => v.type)).toContain('hero-split')
    expect(heroEntry?.defaults['hero-centered']).toBeDefined()
  })
})
