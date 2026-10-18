// Adapter axiosa kierujący żądania do API działającego w przeglądarce.
import { handle, HttpError } from './server'

const LATENCY_MS = 150

function parseRequest(config) {
  const url = new URL(config.url, 'http://demo.local/')
  const query = { ...Object.fromEntries(url.searchParams), ...(config.params || {}) }
  Object.keys(query).forEach((key) => query[key] === undefined && delete query[key])

  let body = config.data
  let formData = null
  if (body instanceof FormData) {
    formData = body
    body = {}
  } else if (typeof body === 'string' && body) {
    body = JSON.parse(body)
  }

  return {
    method: (config.method || 'get').toLowerCase(),
    path: url.pathname.replace(/^\/api\/v1/, '').replace(/\/$/, '') || '/',
    query,
    body,
    formData,
    headers: config.headers || {},
  }
}

export function demoAdapter(config) {
  return new Promise((resolve, reject) => {
    setTimeout(() => {
      try {
        const result = handle(parseRequest(config))
        resolve({ data: result.data, status: result.status, statusText: 'OK', headers: {}, config, request: {} })
      } catch (error) {
        if (!(error instanceof HttpError)) console.error(error)
        const status = error.status || 500
        const data = { success: false, message: error.message }
        if (error.errors) data.errors = error.errors
        const err = new Error(error.message)
        err.config = config
        err.response = { status, data, headers: {}, config }
        err.isAxiosError = true
        reject(err)
      }
    }, LATENCY_MS)
  })
}
