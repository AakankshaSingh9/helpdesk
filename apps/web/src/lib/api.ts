// Thin wrapper around fetch for talking to the Laravel API using Sanctum's
// cookie-based SPA authentication.
//
// The flow (see GETTING-STARTED.md):
//   1. GET /sanctum/csrf-cookie          → sets the XSRF-TOKEN cookie
//   2. state-changing requests send that token back as the X-XSRF-TOKEN header
//   3. every request uses credentials: 'include' so the session cookie rides along

// Base URL of the Laravel API. Configured via VITE_API_URL in apps/web/.env
// (see .env.example) — no hardcoded fallback so a missing value fails loudly.
const API_URL = (import.meta.env.VITE_API_URL as string | undefined)?.replace(/\/$/, '')
if (!API_URL) {
  throw new Error(
    'VITE_API_URL is not set. Define it in apps/web/.env, e.g. VITE_API_URL=http://localhost:8000',
  )
}

/** Read a cookie value by name (returns the URL-decoded value, or null). */
function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'))
  return match ? decodeURIComponent(match[1]) : null
}

/** Ask Laravel to set the XSRF-TOKEN cookie. Safe to call more than once. */
export async function ensureCsrfCookie(): Promise<void> {
  await fetch(`${API_URL}/sanctum/csrf-cookie`, { credentials: 'include' })
}

export class ApiError extends Error {
  status: number
  /** Laravel validation errors, keyed by field, if present. */
  errors?: Record<string, string[]>

  constructor(status: number, message: string, errors?: Record<string, string[]>) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

type ApiOptions = { method?: string; body?: unknown }

/**
 * Make a JSON request to the API. For non-GET requests it first makes sure the
 * CSRF cookie exists, then attaches the X-XSRF-TOKEN header.
 */
export async function api<T = unknown>(path: string, opts: ApiOptions = {}): Promise<T> {
  const method = opts.method ?? 'GET'
  const headers: Record<string, string> = { Accept: 'application/json' }

  if (method !== 'GET') {
    await ensureCsrfCookie()
    const token = getCookie('XSRF-TOKEN')
    if (token) headers['X-XSRF-TOKEN'] = token
  }
  if (opts.body !== undefined) headers['Content-Type'] = 'application/json'

  const res = await fetch(`${API_URL}${path}`, {
    method,
    credentials: 'include',
    headers,
    body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
  })

  // 204 No Content (e.g. logout) — nothing to parse.
  if (res.status === 204) return undefined as T

  const data = res.headers.get('content-type')?.includes('application/json')
    ? await res.json()
    : null

  if (!res.ok) {
    const message = (data && (data.message as string)) || `HTTP ${res.status}`
    throw new ApiError(res.status, message, data?.errors)
  }
  return data as T
}
