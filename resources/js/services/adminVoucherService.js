import { api } from './api.js';

function query(params = {}) {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) search.set(key, value);
  });
  return search.toString() ? `?${search.toString()}` : '';
}

export const adminVoucherService = {
  list(params = {}) {
    return api(`/api/admin/vouchers${query(params)}`);
  },
  create(payload) {
    return api('/api/admin/vouchers', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },
  update(id, payload) {
    return api(`/api/admin/vouchers/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },
  deactivate(id, reason = '') {
    return api(`/api/admin/vouchers/${id}/deactivate`, {
      method: 'PATCH',
      body: JSON.stringify({ reason }),
    });
  },
};
