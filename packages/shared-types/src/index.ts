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

// --- CMS (FASE 3) -----------------------------------------------------------

export interface CollectionFieldDto {
  id: string
  key: string
  label: string
  type: string
  required: boolean
  config: Record<string, unknown> | null
  position: number
  related_collection?: string | null
}

export interface CollectionDto {
  id: string
  handle: string
  name: string
  name_singular: string | null
  description?: string | null
  kind: string
  route_prefix: string | null
  fields?: CollectionFieldDto[]
  created_at?: string
  updated_at?: string
}

export interface AuthorDto {
  id: string
  name: string
  slug: string
  bio?: string | null
  avatar_url?: string | null
  email?: string | null
  links?: Record<string, unknown> | null
  position?: number
}

export interface CategoryDto {
  id: string
  name: string
  slug: string
  description?: string | null
  position?: number
}

export interface EntryRefDto {
  id: string
  name: string
  slug: string
}

export interface EntrySummaryDto {
  id: string
  title: string
  slug: string
  status: string
  path: string | null
  published_at: string | null
  updated_at?: string
}

export interface EntryDto extends EntrySummaryDto {
  values: Record<string, unknown>
  author: EntryRefDto | null
  categories: EntryRefDto[]
}

export interface MediaAssetDto {
  id: string
  url: string
  original_filename: string
  mime_type: string
  size_bytes: number
  width: number | null
  height: number | null
  alt: string | null
  title: string | null
  status: string
  variants?: Record<string, string>
  created_at?: string
}

/** Tarjeta resuelta para el CollectionGrid (sidecar `resolved`). */
export interface EntryCard {
  id: string
  title: string
  path: string | null
  excerpt: string | null
  image: string | null
  date: string | null
  author: { name: string; slug: string } | null
  category: { name: string; slug: string } | null
}

export type { PageSchema } from '@sass-blog/site-schema'
