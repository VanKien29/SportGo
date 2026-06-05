import { api } from './api.js';

function query(params = {}) {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      search.set(key, value);
    }
  });

  return search.toString() ? `?${search.toString()}` : '';
}

export const adminPaymentService = {
  list(params = {}) {
    return api(`/api/admin/payments${query(params)}`);
  },

  show(id) {
    return api(`/api/admin/payments/${id}`);
  },

  retry(id, payload) {
    return api(`/api/admin/payments/${id}/retry`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  updateStatus(id, payload) {
    return api(`/api/admin/payments/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },
};
