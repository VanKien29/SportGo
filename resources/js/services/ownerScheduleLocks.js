import { api } from './api.js';

function queryString(params = {}) {
  const query = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') query.append(key, value);
  });

  return query.toString();
}

export const ownerScheduleLockService = {
  list(params) {
    return api(`/api/owner/schedule-locks?${queryString(params)}`);
  },

  create(payload) {
    return api('/api/owner/schedule-locks', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  remove(id) {
    return api(`/api/owner/schedule-locks/${id}`, {
      method: 'DELETE',
    });
  },
};
