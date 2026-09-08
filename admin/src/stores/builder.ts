import type { PageDto, PageSchema } from '@sass-blog/shared-types'
import { emptyPageSchema } from '@sass-blog/site-schema'
import { defineStore } from 'pinia'
import {
  addSection,
  findSection,
  moveSection,
  removeSection,
  toggleVisible,
  updateSectionProps,
} from '../builder/schema-ops'
import { pagesApi } from '../services/api'

export const useBuilderStore = defineStore('builder', {
  state: () => ({
    ws: '',
    site: '',
    pageUlid: '',
    page: null as PageDto | null,
    schema: emptyPageSchema() as PageSchema,
    selectedId: null as string | null,
    dirty: false,
    saving: false,
    publishing: false,
  }),

  getters: {
    status: (state): string => state.page?.status ?? 'draft',
    selected: (state) => findSection(state.schema, state.selectedId),
  },

  actions: {
    async load(ws: string, site: string, pageUlid: string): Promise<void> {
      this.ws = ws
      this.site = site
      this.pageUlid = pageUlid
      const { data } = await pagesApi.get(ws, site, pageUlid)
      this.page = data
      this.schema = data.draft_schema
      this.selectedId = this.schema.sections[0]?.id ?? null
      this.dirty = false
    },

    add(type: string, variant: string): void {
      this.schema = addSection(this.schema, type, variant)
      this.selectedId = this.schema.sections.at(-1)?.id ?? null
      this.dirty = true
    },

    remove(id: string): void {
      this.schema = removeSection(this.schema, id)
      if (this.selectedId === id) {
        this.selectedId = this.schema.sections[0]?.id ?? null
      }
      this.dirty = true
    },

    move(id: string, direction: -1 | 1): void {
      this.schema = moveSection(this.schema, id, direction)
      this.dirty = true
    },

    toggle(id: string): void {
      this.schema = toggleVisible(this.schema, id)
      this.dirty = true
    },

    select(id: string): void {
      this.selectedId = id
    },

    updateProps(id: string, patch: Record<string, unknown>): void {
      this.schema = updateSectionProps(this.schema, id, patch)
      this.dirty = true
    },

    async save(): Promise<void> {
      this.saving = true
      try {
        const { data } = await pagesApi.updateSchema(this.ws, this.site, this.pageUlid, this.schema)
        this.page = data
        this.schema = data.draft_schema
        this.dirty = false
      } finally {
        this.saving = false
      }
    },

    async publish(): Promise<void> {
      this.publishing = true
      try {
        if (this.dirty) {
          await this.save()
        }
        const { data } = await pagesApi.publish(this.ws, this.site, this.pageUlid)
        this.page = data
        this.schema = data.draft_schema
        this.dirty = false
      } finally {
        this.publishing = false
      }
    },

    async previewLink(): Promise<string> {
      const { url } = await pagesApi.previewLink(this.ws, this.site, this.pageUlid)
      return url
    },
  },
})
