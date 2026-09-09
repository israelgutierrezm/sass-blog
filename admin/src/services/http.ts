const BASE = import.meta.env.VITE_API_BASE ?? 'http://127.0.0.1:8000/api/v1'
const TOKEN_KEY = 'sb_token'

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string | null): void {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  } else {
    localStorage.removeItem(TOKEN_KEY)
  }
}

/** Error de API con el estado y los errores de validación (422) mapeados. */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string,
    public readonly errors: Record<string, string[]> = {},
  ) {
    super(message)
    this.name = 'ApiError'
  }

  /** Primer mensaje de error (de un campo, o general). */
  first(field?: string): string {
    if (field && this.errors[field]?.[0]) {
      return this.errors[field][0]
    }
    const firstField = Object.values(this.errors)[0]
    return firstField?.[0] ?? this.message
  }
}

function authHeaders(): Record<string, string> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  const token = getToken()
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  return headers
}

async function parse<T>(response: Response): Promise<T> {
  const text = await response.text()
  const json = text ? JSON.parse(text) : null

  if (!response.ok) {
    if (response.status === 401) {
      setToken(null)
    }
    throw new ApiError(response.status, json?.message ?? `HTTP ${response.status}`, json?.errors ?? {})
  }

  return json as T
}

async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
  const headers = authHeaders()
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  return parse<T>(await fetch(`${BASE}${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  }))
}

/** Subida multipart: NO fija Content-Type (el navegador pone el boundary). */
async function upload<T>(path: string, form: FormData): Promise<T> {
  return parse<T>(await fetch(`${BASE}${path}`, {
    method: 'POST',
    headers: authHeaders(),
    body: form,
  }))
}

export const http = {
  get: <T>(path: string) => request<T>('GET', path),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
  patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body),
  del: <T>(path: string) => request<T>('DELETE', path),
  upload: <T>(path: string, form: FormData) => upload<T>(path, form),
}
