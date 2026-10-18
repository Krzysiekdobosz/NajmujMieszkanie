import axios from 'axios'

const isDemo = import.meta.env.VITE_DEMO === 'true'

const api = axios.create({
  baseURL: isDemo ? '/api/v1' : import.meta.env.VITE_API_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  // W wersji demo (GitHub Pages) API działa w przeglądarce na danych testowych.
  ...(isDemo ? { adapter: (config) => import('@/demo/adapter').then((m) => m.demoAdapter(config)) } : {}),
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      window.location.href = isDemo
        ? `${import.meta.env.BASE_URL}#/auth/logowanie`
        : '/auth/logowanie'
    }
    return Promise.reject(error)
  }
)

export default api
