import { api, apiFormData } from './api.js';

export const complaintService = {
  create(payload) { return payload instanceof FormData ? apiFormData('/api/complaints', payload) : api('/api/complaints', { method: 'POST', body: JSON.stringify(payload) }); },
  list(params = {}) {
    const query = new URLSearchParams(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined));
    return api(`/api/complaints${query.toString() ? `?${query}` : ''}`);
  },
  get(id) { return api(`/api/complaints/${id}`); },
  reply(id, content) { return api(`/api/complaints/${id}/reply`, { method: 'POST', body: JSON.stringify({ content }) }); },
};
