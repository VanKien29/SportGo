import { api } from './api.js';
 
function toQuery(params = {}) {
  const query = new URLSearchParams();
 
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return;
    query.append(key, value);
  });
 
  return query.toString();
}
 
export const ownerMatchmakingService = {
  list(params = {}) {
    const query = toQuery(params);
    return api(`/api/owner/matchmaking-posts${query ? `?${query}` : ''}`);
  },
 
  hide(id, payload) {
    return api(`/api/owner/matchmaking-posts/${id}/hide`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },
 
  report(id, payload) {
    return api(`/api/owner/matchmaking-posts/${id}/report`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  create(payload) {
    const isFormData = payload instanceof FormData;
    return api('/api/owner/matchmaking-posts', {
      method: 'POST',
      body: isFormData ? payload : JSON.stringify(payload),
      // if using api wrapper, typically FormData handles headers automatically (omits Content-Type so browser sets boundary)
    }, isFormData);
  },

  getEligibleBookings(venue_cluster_id = '') {
    const query = venue_cluster_id ? `?venue_cluster_id=${venue_cluster_id}` : '';
    return api(`/api/owner/matchmaking-posts/eligible-bookings${query}`);
  },
};
