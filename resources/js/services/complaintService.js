import { api, apiFormData } from './api.js';

export const complaintService = {
  create(payload, options = {}) {
    return payload instanceof FormData
      ? apiFormData('/api/complaints', payload, options)
      : api('/api/complaints', { method: 'POST', body: JSON.stringify(payload), ...options });
  },
  eligibleBookings() { return api('/api/complaints/eligible-bookings', { cache: 'no-store', dedupe: false }); },
  list(params = {}) {
    const query = new URLSearchParams(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined));
    return api(`/api/complaints${query.toString() ? `?${query}` : ''}`);
  },
  get(id) { return api(`/api/complaints/${id}`); },
  reply(id, content, files = [], options = {}) {
    if (files.length) {
      const payload = new FormData();
      if (content) payload.append('content', content);
      files.forEach((file) => payload.append('evidence_images[]', file));
      return apiFormData(`/api/complaints/${id}/reply`, payload, options);
    }
    return api(`/api/complaints/${id}/reply`, { method: 'POST', body: JSON.stringify({ content }), ...options });
  },
};
