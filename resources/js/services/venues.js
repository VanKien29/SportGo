import { api, apiCached } from './api.js';

function toQuery(params = {}) {
  const query = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return;
    query.append(key, value);
  });

  return query.toString();
}

export const venueService = {
  list(params = {}) {
    const query = toQuery(params);
    return api(`/api/venues${query ? `?${query}` : ''}`);
  },

  filterOptions() {
    return apiCached('/api/venues/filter-options');
  },

  show(id) {
    return api(`/api/venues/${id}`);
  },

  schedule(id, params = {}) {
    const query = toQuery(params);
    return api(`/api/venues/${id}/schedule${query ? `?${query}` : ''}`);
  },
};
