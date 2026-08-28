import { api } from './api.js';

export const platformFeeArrangementService = {
  async list(filters = {}) {
    const query = new URLSearchParams();
    if (filters.venue_cluster_id) query.set('venue_cluster_id', filters.venue_cluster_id);
    if (filters.status) query.set('status', filters.status);
    const response = await api(`/api/admin/platform-fee-arrangements${query.toString() ? `?${query}` : ''}`);
    return response.data || [];
  },

  create(payload) {
    return api('/api/admin/platform-fee-arrangements', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  cancel(id) {
    return api(`/api/admin/platform-fee-arrangements/${encodeURIComponent(id)}/cancel`, {
      method: 'POST',
    });
  },
};
