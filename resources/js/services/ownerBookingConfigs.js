import { api } from './api.js';

export const ownerBookingConfigService = {
  list() {
    return api('/api/owner/booking-configs');
  },

  update(clusterId, payload) {
    return api(`/api/owner/booking-configs/${clusterId}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },
};
