import { api, apiFormData } from './api.js';

export const ownerPlatformFeeService = {
  list(clusterId) {
    return api(`/api/owner/platform-fees?venue_cluster_id=${encodeURIComponent(clusterId)}`);
  },

  detail(id) {
    return api(`/api/owner/platform-fees/${id}`);
  },

  submitProof(id, formData) {
    return apiFormData(`/api/owner/platform-fees/${id}/payment-proof`, formData);
  },
};
