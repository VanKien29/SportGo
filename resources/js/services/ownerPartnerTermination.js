import { api, apiDownload } from './api.js';

export const ownerPartnerTerminationService = {
  eligibility(clusterId) {
    return api(`/api/owner/venue-clusters/${clusterId}/termination/eligibility`);
  },

  preview(clusterId, payload) {
    return api(`/api/owner/venue-clusters/${clusterId}/termination/preview`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  sendOtp(clusterId, payload) {
    return api(`/api/owner/venue-clusters/${clusterId}/termination/send-otp`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  submit(clusterId, payload) {
    return api(`/api/owner/venue-clusters/${clusterId}/termination/submit`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  show(id) {
    return api(`/api/owner/termination-requests/${id}`);
  },

  downloadDocument(id) {
    return apiDownload(`/api/files/documents/${id}/download`);
  },

  futureBookings(id) {
    return api(`/api/owner/termination-requests/${id}/future-bookings`);
  },

  bulkAction(id, payload) {
    return api(`/api/owner/termination-requests/${id}/future-bookings/bulk-action`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  storeWithdrawal(id, payload) {
    return api(`/api/owner/termination-requests/${id}/withdrawals`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  cancelSendOtp(id, payload) {
    return api(`/api/owner/termination-requests/${id}/cancel/send-otp`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  cancel(id, payload) {
    return api(`/api/owner/termination-requests/${id}/cancel`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  finalDocumentSignSendOtp(id, payload) {
    return api(`/api/owner/termination-requests/${id}/final-document/sign/send-otp`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  finalDocumentSign(id, payload) {
    return api(`/api/owner/termination-requests/${id}/final-document/sign`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  acknowledgeUnilateralNotice(id) {
    return api(`/api/owner/termination-requests/${id}/unilateral-notice/acknowledge`, {
      method: 'POST',
      body: JSON.stringify({ accepted: true }),
    });
  },

  requestUnilateralReconsideration(id, payload) {
    return api(`/api/owner/termination-requests/${id}/unilateral-notice/reconsideration`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },
};
