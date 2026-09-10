import type {
  ApiCollection,
  ApiItem,
  AuthorDto,
  CategoryDto,
  CollectionDto,
  EntryDto,
  EntrySummaryDto,
  MediaAssetDto,
  PageDto,
  PageSchema,
  PageSummaryDto,
  RedirectDto,
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

function categoriesBase(ws: string, site: string, collection: string): string {
  return `${siteBase(ws, site)}/collections/${collection}/categories`
}

export const categoriesApi = {
  list: (ws: string, site: string, collection: string) =>
    http.get<ApiCollection<CategoryDto>>(categoriesBase(ws, site, collection)),
  create: (ws: string, site: string, collection: string, input: { name: string; description?: string }) =>
    http.post<ApiItem<CategoryDto>>(categoriesBase(ws, site, collection), input),
  update: (ws: string, site: string, collection: string, category: string, input: { name?: string; description?: string }) =>
    http.patch<ApiItem<CategoryDto>>(`${categoriesBase(ws, site, collection)}/${category}`, input),
  remove: (ws: string, site: string, collection: string, category: string) =>
    http.del<null>(`${categoriesBase(ws, site, collection)}/${category}`),
}

function authorsBase(ws: string, site: string): string {
  return `${siteBase(ws, site)}/authors`
}

export const authorsApi = {
  list: (ws: string, site: string) => http.get<ApiCollection<AuthorDto>>(authorsBase(ws, site)),
  create: (ws: string, site: string, input: { name: string; bio?: string }) =>
    http.post<ApiItem<AuthorDto>>(authorsBase(ws, site), input),
  update: (ws: string, site: string, author: string, input: { name?: string; bio?: string }) =>
    http.patch<ApiItem<AuthorDto>>(`${authorsBase(ws, site)}/${author}`, input),
  remove: (ws: string, site: string, author: string) =>
    http.del<null>(`${authorsBase(ws, site)}/${author}`),
}

export const mediaApi = {
  list: (ws: string, site: string, page = 1, type?: 'image') => {
    const query = new URLSearchParams({ page: String(page) })
    if (type) {
      query.set('type', type)
    }

    return http.get<ApiCollection<MediaAssetDto>>(`${siteBase(ws, site)}/media?${query.toString()}`)
  },
  upload: (ws: string, site: string, file: File) => {
    const form = new FormData()
    form.append('file', file)

    return http.upload<ApiItem<MediaAssetDto>>(`${siteBase(ws, site)}/media`, form)
  },
  update: (ws: string, site: string, id: string, input: { alt?: string | null; title?: string | null }) =>
    http.patch<ApiItem<MediaAssetDto>>(`${siteBase(ws, site)}/media/${id}`, input),
  remove: (ws: string, site: string, id: string) =>
    http.del<null>(`${siteBase(ws, site)}/media/${id}`),
}

export interface RedirectInput {
  from_path?: string
  to_path?: string
  status?: number
  is_active?: boolean
}

export const redirectsApi = {
  list: (ws: string, site: string) =>
    http.get<ApiCollection<RedirectDto>>(`${siteBase(ws, site)}/redirects`),
  create: (ws: string, site: string, input: RedirectInput) =>
    http.post<ApiItem<RedirectDto>>(`${siteBase(ws, site)}/redirects`, input),
  update: (ws: string, site: string, id: string, input: RedirectInput) =>
    http.patch<ApiItem<RedirectDto>>(`${siteBase(ws, site)}/redirects/${id}`, input),
  remove: (ws: string, site: string, id: string) =>
    http.del<null>(`${siteBase(ws, site)}/redirects/${id}`),
}
