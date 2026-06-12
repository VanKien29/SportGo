import { api } from './api.js';

function buildQuery(params = {}) {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      query.set(key, value);
    }
  });

  return query.toString();
}

export const adminUserService = {
  list(params = {}) {
    const query = buildQuery(params);
    return api(`/api/admin/users${query ? `?${query}` : ''}`);
  },

  show(id) {
    return api(`/api/admin/users/${id}`);
  },

  get(id) {
    return api(`/api/admin/users/${id}`);
  },

  create(payload) {
    return api('/api/admin/users', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  update(id, payload) {
    return api(`/api/admin/users/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  lock(id, payload) {
    return api(`/api/admin/users/${id}/lock`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },

  unlock(id, payload) {
    return api(`/api/admin/users/${id}/unlock`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },
};
