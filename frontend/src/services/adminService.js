import api from './api'

export const adminService = {
  // Użytkownicy
  users:        (params) => api.get('/admin/users', { params }),
  userShow:     (id)     => api.get(`/admin/users/${id}`),
  userStatus:   (id, isActive) => api.patch(`/admin/users/${id}/status`, { is_active: isActive }),
  userRole:     (id, role)     => api.patch(`/admin/users/${id}/role`, { role }),

  // Oferty
  properties:     (params) => api.get('/admin/properties', { params }),
  propertyStatus: (id, status, reason) => api.patch(`/admin/properties/${id}/status`, { status, rejected_reason: reason }),

  // Zgłoszenia kontaktowe
  contacts:       (params) => api.get('/admin/contact-messages', { params }),
  contactShow:    (id)     => api.get(`/admin/contact-messages/${id}`),
  contactStatus:  (id, status, note) => api.patch(`/admin/contact-messages/${id}/status`, { status, admin_note: note }),

  // Strony statyczne
  pages:       () => api.get('/admin/pages'),
  pageShow:    (id) => api.get(`/admin/pages/${id}`),
  pageUpdate:  (id, data) => api.put(`/admin/pages/${id}`, data),

  // Ustawienia
  settings:       () => api.get('/admin/settings'),
  settingsUpdate: (settings) => api.put('/admin/settings', { settings }),

  // Logi
  activityLogs: (params) => api.get('/admin/activity-logs', { params }),
}
