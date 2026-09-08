import type { ApiItem, AuthResponse, UserDto } from '@sass-blog/shared-types'
import { defineStore } from 'pinia'
import { getToken, http, setToken } from '../services/http'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as UserDto | null,
    token: getToken(),
  }),

  getters: {
    isAuthenticated: (state): boolean => Boolean(state.token),
  },

  actions: {
    async login(email: string, password: string): Promise<void> {
      this.setSession(await http.post<AuthResponse>('/login', { email, password }))
    },

    async register(name: string, email: string, password: string): Promise<void> {
      this.setSession(await http.post<AuthResponse>('/register', {
        name,
        email,
        password,
        password_confirmation: password,
      }))
    },

    async fetchMe(): Promise<void> {
      const response = await http.get<ApiItem<UserDto>>('/me')
      this.user = response.data
    },

    setSession(response: AuthResponse): void {
      this.token = response.token
      this.user = response.data
      setToken(response.token)
    },

    logout(): void {
      this.user = null
      this.token = null
      setToken(null)
    },
  },
})
