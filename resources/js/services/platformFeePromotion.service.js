import { api } from './api.js';

export const platformFeePromotionService = {
  async list(filters = {}) {
    const query = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => { if (value !== '' && value != null) query.set(key, value); });
    const response = await api(`/api/admin/platform-fee-promotions${query.toString() ? `?${query}` : ''}`);
    return response.data || [];
  },
  create(payload) { return api('/api/admin/platform-fee-promotions', { method: 'POST', body: JSON.stringify(payload) }); },
  update(id, payload) { return api(`/api/admin/platform-fee-promotions/${encodeURIComponent(id)}`, { method: 'PUT', body: JSON.stringify(payload) }); },
  publish(id) { return api(`/api/admin/platform-fee-promotions/${encodeURIComponent(id)}/publish`, { method: 'POST' }); },
  deactivate(id) { return api(`/api/admin/platform-fee-promotions/${encodeURIComponent(id)}/deactivate`, { method: 'POST' }); },
  remove(id) { return api(`/api/admin/platform-fee-promotions/${encodeURIComponent(id)}`, { method: 'DELETE' }); },
};
