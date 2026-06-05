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

  create(payload) {
    return api('/api/admin/users', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  assignRoles(id, roleIds) {
    return api(`/api/admin/users/${id}/roles`, {
      method: 'PUT',
      body: JSON.stringify({ roles: roleIds }),
    });
  },
};
