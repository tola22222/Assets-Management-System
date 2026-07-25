import axios from 'axios'
import router from '../router'
import { loading, notify } from '../composables'

const http = axios.create({
  baseURL: '/api',
})

// Background/polling requests can opt out of the global overlay with
// http.get(url, { skipLoading: true }).
http.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    if (!config.skipLoading) {
      loading.show()
    }
    return config
  },
  (error) => {
    loading.hide()
    return Promise.reject(error)
  }
)

const STATUS_MESSAGES = {
  403: "You don't have permission to perform this action.",
  404: 'The requested resource could not be found.',
  500: 'Something went wrong on our end. Please try again.',
}

http.interceptors.response.use(
  (response) => {
    if (!response.config.skipLoading) {
      loading.hide()
    }
    return response
  },
  (error) => {
    if (!error.config?.skipLoading) {
      loading.hide()
    }

    const status = error.response?.status

    if (status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      router.push({ name: 'login' })
    } else if (status && STATUS_MESSAGES[status]) {
      notify.error(error.response?.data?.message ?? STATUS_MESSAGES[status])
    } else if (!error.response) {
      notify.error('Network error. Please check your connection.')
    }

    return Promise.reject(error)
  }
)

export default http
