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
