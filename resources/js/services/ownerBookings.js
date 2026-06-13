import { api } from './api.js';

function toQuery(params = {}) {
  const query = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return;
    query.append(key, value);
  });

  return query.toString();
}

export const ownerBookingService = {
  list(params = {}) {
    const query = toQuery(params);
    return api(`/api/owner/bookings${query ? `?${query}` : ''}`);
  },

  schedule(params = {}) {
    const query = toQuery(params);
    return api(`/api/owner/bookings/schedule${query ? `?${query}` : ''}`);
  },

  show(id) {
    return api(`/api/owner/bookings/${id}`);
  },

  createCounter(payload) {
    return api('/api/owner/bookings/counter', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  createRecurring(payload) {
    return api('/api/owner/bookings/recurring', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  collectPayment(id, payload) {
    return api(`/api/owner/bookings/${id}/payments/collect`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  updateStatus(id, payload) {
    return api(`/api/owner/bookings/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },

  changeCourt(id, payload) {
    return api(`/api/owner/bookings/${id}/court`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },
};
