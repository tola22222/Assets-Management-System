import { defineStore } from 'pinia'
import http from '../api/http'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('token') || null,
    user: JSON.parse(localStorage.getItem('user') || 'null'),
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    // Mirrors backend/app/Models/User.php's role helpers so the SPA can gate
    // workflow-action buttons consistently instead of re-checking role strings inline.
    isOperationsHrManager: (state) => state.user?.role === 'operations_hr_manager',
    isStaff: (state) => state.user?.role === 'staff',
    isExecutiveDirector: (state) => state.user?.role === 'executive_director',
    isFinanceManager: (state) => state.user?.role === 'finance_manager',
    canApproveDisposal: (state) => ['operations_hr_manager', 'executive_director'].includes(state.user?.role),
    canCompleteVerification: (state) => ['operations_hr_manager', 'finance_manager'].includes(state.user?.role),
  },

  actions: {
    async login(email, password) {
      const { data } = await http.post('/login', { email, password })
      this.token = data.token
      this.user = data.user
      localStorage.setItem('token', data.token)
      localStorage.setItem('user', JSON.stringify(data.user))
    },

    async logout() {
      try {
        await http.post('/logout')
      } finally {
        this.token = null
        this.user = null
        localStorage.removeItem('token')
        localStorage.removeItem('user')
      }
    },

    setUser(user) {
      this.user = user
      localStorage.setItem('user', JSON.stringify(user))
    },
  },
})
