import { getComponent, type PageSchema, type Section } from '@sass-blog/site-schema'
import { newUlid } from './ulid'

function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T
}

/** Operaciones PURAS sobre el page schema (inmutables, testeables sin Pinia). */

export function addSection(schema: PageSchema, type: string, variant: string): PageSchema {
  const component = getComponent(type)
  const defaults = component?.defaults[variant] ?? { props: {}, settings: {} }
  const section: Section = {
    id: newUlid(),
    type,
    variant,
    visible: true,
    props: clone(defaults.props),
    settings: clone(defaults.settings),
  }

  return { ...schema, sections: [...schema.sections, section] }
}

export function removeSection(schema: PageSchema, id: string): PageSchema {
  return { ...schema, sections: schema.sections.filter((section) => section.id !== id) }
}

export function moveSection(schema: PageSchema, id: string, direction: -1 | 1): PageSchema {
  const index = schema.sections.findIndex((section) => section.id === id)
  const target = index + direction
  if (index < 0 || target < 0 || target >= schema.sections.length) {
    return schema
  }

  const sections = [...schema.sections]
  const moved = sections[index]!
  sections[index] = sections[target]!
  sections[target] = moved

  return { ...schema, sections }
}

export function toggleVisible(schema: PageSchema, id: string): PageSchema {
  return {
    ...schema,
    sections: schema.sections.map((section) =>
      section.id === id ? { ...section, visible: !section.visible } : section,
    ),
  }
}

export function updateSectionProps(
  schema: PageSchema,
  id: string,
  patch: Record<string, unknown>,
): PageSchema {
  return {
    ...schema,
    sections: schema.sections.map((section) =>
      section.id === id ? { ...section, props: { ...section.props, ...patch } } : section,
    ),
  }
}

export function findSection(schema: PageSchema, id: string | null): Section | undefined {
  return id ? schema.sections.find((section) => section.id === id) : undefined
}
