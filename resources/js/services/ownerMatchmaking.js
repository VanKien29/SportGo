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
};
