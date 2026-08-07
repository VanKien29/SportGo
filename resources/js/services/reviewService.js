import { api } from './api.js';

export const reviewService = {
  eligible(venueClusterId) {
    const query = venueClusterId ? `?venue_cluster_id=${encodeURIComponent(venueClusterId)}` : '';
    return api(`/api/reviews/eligible${query}`);
  },

  create(data) {
    return api('/api/reviews', { method: 'POST', body: JSON.stringify(data) });
  },

  update(id, data) {
    return api(`/api/reviews/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
  },

  remove(id) {
    return api(`/api/reviews/${id}`, { method: 'DELETE' });
  },
};
