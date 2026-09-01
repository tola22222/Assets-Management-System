import { defineStore } from 'pinia'
import http from '../api/http'
import { loadPermissions, clearPermissions } from '../composables/usePermissions'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('token') || null,
    user: JSON.parse(localStorage.getItem('user') || 'null'),
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
  },

  actions: {
    async login(email, password, remember = false) {
      const { data } = await http.post('/login', { email, password, remember })
      this.token = data.token
      this.user = data.user
      localStorage.setItem('token', data.token)
      localStorage.setItem('user', JSON.stringify(data.user))
      // Permissions are per-account, so they must be refetched on sign-in
      // rather than carried over from whoever was signed in before.
      await loadPermissions(true)
    },

    async logout() {
      try {
        await http.post('/logout')
      } finally {
        this.token = null
        this.user = null
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        clearPermissions()
      }
    },

    setUser(user) {
      this.user = user
      localStorage.setItem('user', JSON.stringify(user))
    },
  },
})
