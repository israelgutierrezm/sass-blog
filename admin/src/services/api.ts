import type {
  ApiCollection,
  ApiItem,
  AuthorDto,
  CategoryDto,
  CollectionDto,
  EntryDto,
  EntrySummaryDto,
  PageDto,
  PageSchema,
  PageSummaryDto,
  SiteDto,
  WorkspaceDto,
} from '@sass-blog/shared-types'
import { http } from './http'

export const workspacesApi = {
  list: () => http.get<ApiCollection<WorkspaceDto>>('/workspaces'),
  create: (name: string) => http.post<ApiItem<WorkspaceDto>>('/workspaces', { name }),
}

export const sitesApi = {
  list: (ws: string) => http.get<ApiCollection<SiteDto>>(`/workspaces/${ws}/sites`),
  create: (ws: string, name: string, slug: string) =>
    http.post<ApiItem<SiteDto>>(`/workspaces/${ws}/sites`, { name, slug }),
}

function pagesBase(ws: string, site: string): string {
  return `/workspaces/${ws}/sites/${site}/pages`
}

export const pagesApi = {
  list: (ws: string, site: string) =>
    http.get<ApiCollection<PageSummaryDto>>(pagesBase(ws, site)),
  create: (ws: string, site: string, title: string, path: string) =>
    http.post<ApiItem<PageDto>>(pagesBase(ws, site), { title, path }),
  get: (ws: string, site: string, page: string) =>
    http.get<ApiItem<PageDto>>(`${pagesBase(ws, site)}/${page}`),
  updateSchema: (ws: string, site: string, page: string, schema: PageSchema) =>
    http.patch<ApiItem<PageDto>>(`${pagesBase(ws, site)}/${page}`, { schema }),
  publish: (ws: string, site: string, page: string) =>
    http.post<ApiItem<PageDto>>(`${pagesBase(ws, site)}/${page}/publish`),
  previewLink: (ws: string, site: string, page: string) =>
    http.post<{ url: string; expires_at: string }>(`${pagesBase(ws, site)}/${page}/preview-link`),
}

// --- CMS (FASE 3) -----------------------------------------------------------

function siteBase(ws: string, site: string): string {
  return `/workspaces/${ws}/sites/${site}`
}

export interface EntryInput {
  title?: string
  slug?: string | null
  author?: string | null
  category_ids?: string[]
  values?: Record<string, unknown>
}

export const collectionsApi = {
  list: (ws: string, site: string) =>
    http.get<ApiCollection<CollectionDto>>(`${siteBase(ws, site)}/collections`),
  get: (ws: string, site: string, collection: string) =>
    http.get<ApiItem<CollectionDto>>(`${siteBase(ws, site)}/collections/${collection}`),
}

function entriesBase(ws: string, site: string, collection: string): string {
  return `${siteBase(ws, site)}/collections/${collection}/entries`
}

export const entriesApi = {
  list: (ws: string, site: string, collection: string) =>
    http.get<ApiCollection<EntrySummaryDto>>(entriesBase(ws, site, collection)),
  get: (ws: string, site: string, collection: string, entry: string) =>
    http.get<ApiItem<EntryDto>>(`${entriesBase(ws, site, collection)}/${entry}`),
  create: (ws: string, site: string, collection: string, input: EntryInput) =>
    http.post<ApiItem<EntryDto>>(entriesBase(ws, site, collection), input),
  update: (ws: string, site: string, collection: string, entry: string, input: EntryInput) =>
    http.patch<ApiItem<EntryDto>>(`${entriesBase(ws, site, collection)}/${entry}`, input),
  publish: (ws: string, site: string, collection: string, entry: string) =>
    http.post<ApiItem<EntryDto>>(`${entriesBase(ws, site, collection)}/${entry}/publish`),
}

export const categoriesApi = {
  list: (ws: string, site: string, collection: string) =>
    http.get<ApiCollection<CategoryDto>>(`${siteBase(ws, site)}/collections/${collection}/categories`),
}

export const authorsApi = {
  list: (ws: string, site: string) =>
    http.get<ApiCollection<AuthorDto>>(`${siteBase(ws, site)}/authors`),
}
