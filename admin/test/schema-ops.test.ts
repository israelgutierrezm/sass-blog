import { emptyPageSchema } from '@sass-blog/site-schema'
import { describe, expect, it } from 'vitest'
import {
  addSection,
  moveSection,
  removeSection,
  toggleVisible,
  updateSectionProps,
} from '../src/builder/schema-ops'

describe('schema-ops', () => {
  it('addSection agrega una sección con defaults y un ULID válido', () => {
    const schema = addSection(emptyPageSchema(), 'hero', 'hero-centered')
    expect(schema.sections).toHaveLength(1)
    const section = schema.sections[0]!
    expect(section.type).toBe('hero')
    expect(section.variant).toBe('hero-centered')
    expect(section.id).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/)
    expect(section.props.heading).toBeDefined()
    expect(section.visible).toBe(true)
  })

  it('removeSection quita por id', () => {
    const added = addSection(emptyPageSchema(), 'hero', 'hero-centered')
    const removed = removeSection(added, added.sections[0]!.id)
    expect(removed.sections).toHaveLength(0)
  })

  it('moveSection reordena', () => {
    const two = addSection(addSection(emptyPageSchema(), 'hero', 'hero-centered'), 'text', 'text-prose')
    const firstId = two.sections[0]!.id
    const moved = moveSection(two, firstId, 1)
    expect(moved.sections[1]!.id).toBe(firstId)
  })

  it('moveSection no se sale de los límites', () => {
    const one = addSection(emptyPageSchema(), 'hero', 'hero-centered')
    expect(moveSection(one, one.sections[0]!.id, -1)).toEqual(one)
  })

  it('toggleVisible alterna la visibilidad', () => {
    const added = addSection(emptyPageSchema(), 'hero', 'hero-centered')
    const toggled = toggleVisible(added, added.sections[0]!.id)
    expect(toggled.sections[0]!.visible).toBe(false)
  })

  it('updateSectionProps hace merge de props', () => {
    const added = addSection(emptyPageSchema(), 'hero', 'hero-centered')
    const updated = updateSectionProps(added, added.sections[0]!.id, { heading: 'Nuevo título' })
    expect(updated.sections[0]!.props.heading).toBe('Nuevo título')
  })
})
