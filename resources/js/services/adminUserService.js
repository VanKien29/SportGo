import { api } from './api.js';

export const adminUserService = {
  list() {
    return api('/api/admin/users');
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

  unlock(id) {
    return api(`/api/admin/users/${id}/unlock`, {
      method: 'PATCH',
    });
  },
};
