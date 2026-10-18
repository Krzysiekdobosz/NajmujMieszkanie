import api from './api'

export const paymentService = {
  list:         (params)     => api.get('/owner/payments', { params }),
  create:       (data)       => api.post('/owner/payments', data),
  updateStatus: (id, status) => api.patch(`/owner/payments/${id}/status`, { status }),
}
