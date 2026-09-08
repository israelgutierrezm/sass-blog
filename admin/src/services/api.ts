import type {
  ApiCollection,
  ApiItem,
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
