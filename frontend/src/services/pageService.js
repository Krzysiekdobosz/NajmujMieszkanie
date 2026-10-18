import api from './api'

export const pageService = {
  show:        (slug) => api.get(`/pages/${slug}`),
  sendContact: (data) => api.post('/contact-messages', data),
}
