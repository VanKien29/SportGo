import { api } from './api';

export const ownerUnlockRequestsService = {
  list(clusterId) {
    return api(`/api/owner/venue-clusters/${clusterId}/unlock-requests`);
  },
  create(clusterId, data) {
    return api(`/api/owner/venue-clusters/${clusterId}/unlock-requests`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },
  cancel(clusterId, requestId) {
    return api(`/api/owner/venue-clusters/${clusterId}/unlock-requests/${requestId}/cancel`, {
      method: 'PATCH',
    });
  },
};
