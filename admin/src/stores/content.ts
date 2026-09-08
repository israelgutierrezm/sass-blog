import type { CollectionDto } from '@sass-blog/shared-types'
import { defineStore } from 'pinia'
import { collectionsApi } from '../services/api'

/** Colecciones del site activo (para listarlas y resolver sus campos). */
export const useContentStore = defineStore('content', {
  state: () => ({
    collections: [] as CollectionDto[],
    loading: false,
  }),

  actions: {
    async loadCollections(ws: string, site: string): Promise<void> {
      this.loading = true
      try {
        this.collections = (await collectionsApi.list(ws, site)).data
      } finally {
        this.loading = false
      }
    },
  },
})
