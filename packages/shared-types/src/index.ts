import type { PageSchema } from '@sass-blog/site-schema'

/** Envolturas de las API Resources del backend (data / data[] + meta). */
export interface ApiItem<T> {
  data: T
}
export interface ApiCollection<T> {
  data: T[]
  meta?: {
    total?: number
    per_page?: number
    current_page?: number
    last_page?: number
  }
}

export interface UserDto {
  id: string
  name: string
  email: string
  workspaces?: WorkspaceDto[]
}

export interface WorkspaceDto {
  id: string
  name: string
  slug: string
  personal: boolean
  role?: string
  created_at?: string
}

export interface SiteDto {
  id: string
  name: string
  slug: string
  status: string
  primary_domain: string | null
  settings: Record<string, unknown> | null
  created_at?: string
  updated_at?: string
}

export interface PageSummaryDto {
  id: string
  title: string
  path: string
  status: string
  published_at: string | null
  updated_at?: string
}

export interface PageDto extends PageSummaryDto {
  draft_schema: PageSchema
  has_unpublished_changes?: boolean
}

export interface AuthResponse {
  data: UserDto
  token: string
}

export type { PageSchema } from '@sass-blog/site-schema'
