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

export const adminRoleService = {
  list(params = {}) {
    return api(`/api/admin/roles${query(params)}`);
  },

  show(id) {
    return api(`/api/admin/roles/${id}`);
  },

  create(payload) {
    return api('/api/admin/roles', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  update(id, payload) {
    return api(`/api/admin/roles/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  delete(id) {
    return api(`/api/admin/roles/${id}`, { method: 'DELETE' });
  },

  permissions() {
    return api('/api/admin/permissions');
  },

  matrix() {
    return api('/api/admin/roles/matrix');
  },

  updatePermissions(id, permissionIds) {
    return api(`/api/admin/roles/${id}/permissions`, {
      method: 'PUT',
      body: JSON.stringify({ permission_ids: permissionIds }),
    });
  },

  togglePermission(id, permissionId, action) {
    return api(`/api/admin/roles/${id}/permissions/toggle`, {
      method: 'PATCH',
      body: JSON.stringify({ permission_id: permissionId, action }),
    });
  },

  users(id) {
    return api(`/api/admin/roles/${id}/users`);
  },
};
