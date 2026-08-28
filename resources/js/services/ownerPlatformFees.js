import { api } from './api.js';

export const ownerPlatformFeeService = {
  list(clusterId) {
    return api(`/api/owner/platform-fees?venue_cluster_id=${encodeURIComponent(clusterId)}`);
  },

  overview() {
    return api('/api/owner/platform-fees/overview');
  },

  detail(id) {
    return api(`/api/owner/platform-fees/${id}`);
  },

  createPayment(id) {
    return api(`/api/owner/platform-fees/${id}/payment`, {
      method: 'POST',
    });
  },

  payFromBalance(id) {
    return api(`/api/owner/platform-fees/${encodeURIComponent(id)}/pay-from-balance`, {
      method: 'POST',
    });
  },

  balancePreview(id) {
    return api(`/api/owner/platform-fees/${encodeURIComponent(id)}/balance-preview`);
  },

  updateSettings(clusterId, autoPayFromBalance) {
    return api('/api/owner/platform-fees/settings', {
      method: 'PATCH',
      body: JSON.stringify({
        venue_cluster_id: clusterId,
        auto_pay_from_balance: autoPayFromBalance,
      }),
    });
  },

  arrangements() {
    return api('/api/owner/platform-fees/arrangements');
  },

  acceptArrangement(id) {
    return api(`/api/owner/platform-fees/arrangements/${encodeURIComponent(id)}/accept`, {
      method: 'POST',
    });
  },

  rejectArrangement(id, reason) {
    return api(`/api/owner/platform-fees/arrangements/${encodeURIComponent(id)}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  },

  cancel(id, reason) {
    return api(`/api/owner/platform-fees/${id}/cancel`, {
      method: 'PATCH',
      body: JSON.stringify({ reason }),
    });
  },

  createAdvancePayment(clusterId, months) {
    return api('/api/owner/platform-fees/prepay', {
      method: 'POST',
      body: JSON.stringify({
        venue_cluster_id: clusterId,
        months,
      }),
    });
  },
};
