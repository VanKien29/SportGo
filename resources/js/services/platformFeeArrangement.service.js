import { api } from './api.js';

export const platformFeeArrangementService = {
  async list(filters = {}) {
    const query = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) query.set(key, value);
    });
    const response = await api(`/api/admin/platform-fee-arrangements${query.toString() ? `?${query}` : ''}`);
    return {
      items: response.data || [],
      meta: {
        current_page: Number(response.current_page || 1),
        last_page: Number(response.last_page || 1),
        per_page: Number(response.per_page || 20),
        total: Number(response.total || (response.data || []).length),
      },
    };
  },

  preview(venueClusterId, serviceMonths) {
    const query = new URLSearchParams({ venue_cluster_id: venueClusterId, service_months: serviceMonths });
    return api(`/api/admin/platform-fee-arrangements/preview?${query}`);
  },

  detail(id) {
    return api(`/api/admin/platform-fee-arrangements/${encodeURIComponent(id)}`);
  },

  create(payload) {
    return api('/api/admin/platform-fee-arrangements', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  cancel(id, reason) {
    return api(`/api/admin/platform-fee-arrangements/${encodeURIComponent(id)}/cancel`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  },
};
