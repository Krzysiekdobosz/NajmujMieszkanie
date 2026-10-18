import api from './api'

export const contractService = {
  list:         (params)     => api.get('/owner/contracts', { params }),
  get:          (id)         => api.get(`/owner/contracts/${id}`),
  create:       (data)       => api.post('/owner/contracts', data),
  updateStatus: (id, status) => api.patch(`/owner/contracts/${id}/status`, { status }),
  download:     (id)         => api.get(`/owner/contracts/${id}/download`, { responseType: 'blob' }),
}
