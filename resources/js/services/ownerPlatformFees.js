import { api } from './api.js';

export const ownerPlatformFeeService = {
  list(clusterId) {
    return api(`/api/owner/platform-fees?venue_cluster_id=${encodeURIComponent(clusterId)}`);
  },

  detail(id) {
    return api(`/api/owner/platform-fees/${id}`);
  },

  createPayment(id) {
    return api(`/api/owner/platform-fees/${id}/payment`, {
      method: 'POST',
    });
  },
};
