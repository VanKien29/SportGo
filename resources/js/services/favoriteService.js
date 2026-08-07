import { api } from './api.js';

export const favoriteService = {
  list() { return api('/api/favorites/venues'); },
  status(venueId) { return api(`/api/favorites/venues/${venueId}/status`); },
  toggle(venueId) { return api(`/api/favorites/venues/${venueId}/toggle`, { method: 'POST' }); },
};
