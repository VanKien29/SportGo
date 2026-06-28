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

export const adminVenuePostService = {
  list(params = {}) {
    return api(`/api/admin/venue-posts${query(params)}`);
  },

  show(id) {
    return api(`/api/admin/venue-posts/${id}`);
  },

  approve(id, payload) {
    return api(`/api/admin/venue-posts/${id}/approve`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },

  remove(id) {
    return api(`/api/admin/venue-posts/${id}`, { method: 'DELETE' });
  },

  restore(id) {
    return api(`/api/admin/venue-posts/${id}/restore`, { method: 'POST' });
  },
};
