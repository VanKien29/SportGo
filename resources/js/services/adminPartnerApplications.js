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

export const adminPartnerApplicationService = {
  list(params = {}) {
    return api(`/api/admin/partner-applications${query(params)}`);
  },

  show(id) {
    return api(`/api/admin/partner-applications/${id}`);
  },

  approve(id, payload) {
    return api(`/api/admin/partner-applications/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  reject(id, payload) {
    return api(`/api/admin/partner-applications/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  approveSignature(contractId) {
    return api(`/api/admin/contracts/${contractId}/approve-signature`, {
      method: 'POST',
    });
  },

  courtTypes() {
    return api('/api/admin/court-types');
  },
};
