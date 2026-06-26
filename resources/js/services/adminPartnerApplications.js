import { api, apiDownload } from './api.js';

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
    return api(`/api/admin/partner-profiles${query(params)}`);
  },

  show(id) {
    return api(`/api/admin/partner-profiles/${id}`);
  },

  approve(id, payload) {
    return api(`/api/admin/partner-profiles/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  reject(id, payload) {
    return api(`/api/admin/partner-profiles/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  signDocument(id, payload = {}) {
    return api(`/api/admin/partner-profiles/${id}/sign-document`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  requestSignDocumentOtp(id, payload = {}) {
    return api(`/api/admin/partner-profiles/${id}/sign-document/request-otp`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  verifySignDocumentOtp(id, payload = {}) {
    return api(`/api/admin/partner-profiles/${id}/sign-document/verify-otp`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  terminate(id, payload) {
    return api(`/api/admin/partner-profiles/${id}/terminate`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  confirmTermination(id, payload = {}) {
    return api(`/api/admin/partner-profiles/${id}/confirm-termination`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  approveSignature(contractId) {
    return api(`/api/admin/contracts/${contractId}/approve-signature`, {
      method: 'POST',
    });
  },

  downloadDocument(id) {
    return apiDownload(`/api/files/documents/${id}/download`);
  },

  downloadUploadedDocument(id) {
    return apiDownload(`/api/admin/partner-profiles/documents/${id}/download`);
  },

  courtTypes() {
    return api('/api/admin/court-types');
  },
};
