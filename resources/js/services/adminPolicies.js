import { api } from './api.js';

export const adminPolicyService = {
  list() {
    return api('/api/admin/system-policies');
  },

  show(id) {
    return api(`/api/admin/system-policies/${id}`);
  },

  create(payload) {
    return api('/api/admin/system-policies', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  update(id, payload) {
    return api(`/api/admin/system-policies/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  deactivate(id) {
    return api(`/api/admin/system-policies/${id}`, {
      method: 'DELETE',
    });
  },
};
