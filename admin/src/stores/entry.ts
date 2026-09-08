import type { EntrySummaryDto } from '@sass-blog/shared-types'
import { defineStore } from 'pinia'
import { entriesApi } from '../services/api'

/** Entradas de una colección (listado). El editor usa la API directamente. */
export const useEntryStore = defineStore('entry', {
  state: () => ({
    entries: [] as EntrySummaryDto[],
    loading: false,
  }),

  actions: {
    async load(ws: string, site: string, collection: string): Promise<void> {
      this.loading = true
      try {
        this.entries = (await entriesApi.list(ws, site, collection)).data
      } finally {
        this.loading = false
      }
    },
  },
})
