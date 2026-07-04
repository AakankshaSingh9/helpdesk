// Reactive authentication state, shared app-wide via a simple module singleton
// (no Pinia needed at this scale).

import { reactive, readonly } from 'vue'
import { api, ApiError, ensureCsrfCookie } from '../lib/api'

export type User = {
  id: string
  name: string
  email: string
  role?: string
}

type AuthState = {
  user: User | null
  ready: boolean // true once we've made the initial "am I logged in?" check
}

const state = reactive<AuthState>({ user: null, ready: false })

/** Load the current user from the session cookie, if any. Runs once at startup. */
async function loadUser(): Promise<void> {
  try {
    state.user = await api<User>('/api/user')
  } catch (e) {
    // 401 = not logged in; anything else we also treat as "no user" here.
    state.user = null
    if (!(e instanceof ApiError) || e.status !== 401) {
      console.warn('Failed to load user:', e)
    }
  } finally {
    state.ready = true
  }
}

async function login(email: string, password: string): Promise<void> {
  await ensureCsrfCookie()
  await api('/login', { method: 'POST', body: { email, password } })
  // Session cookie is now set — fetch the profile to populate the nav bar.
  state.user = await api<User>('/api/user')
}

async function logout(): Promise<void> {
  try {
    await api('/logout', { method: 'POST' })
  } finally {
    state.user = null
  }
}

export const auth = {
  state: readonly(state),
  get isAuthenticated() {
    return state.user !== null
  },
  loadUser,
  login,
  logout,
}
