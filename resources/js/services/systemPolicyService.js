import { api } from './api.js';

export const systemPolicyService = {
  // Các phương thức cho Admin
  adminList() {
    return api('/api/admin/system-policies');
  },
  adminCreate(payload) {
    return api('/api/admin/system-policies', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },
  adminUpdate(id, payload) {
    return api(`/api/admin/system-policies/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },
  adminDelete(id) {
    return api(`/api/admin/system-policies/${id}`, {
      method: 'DELETE',
    });
  },

  // Các phương thức cho người dùng
  checkAcceptance() {
    return api('/api/system-policies/check-acceptance');
  },
  accept(policyId) {
    return api('/api/system-policies/accept', {
      method: 'POST',
      body: JSON.stringify({ policy_id: policyId }),
    });
  },
};
