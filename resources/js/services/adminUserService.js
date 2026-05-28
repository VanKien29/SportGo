import { api } from './api.js';

export const adminUserService = {
  list() {
    return api('/api/admin/users');
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
