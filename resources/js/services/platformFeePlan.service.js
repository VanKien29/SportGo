import { api } from './api.js';

export const platformFeePlanService = {
  async list(status = '') {
    const suffix = status ? `?status=${encodeURIComponent(status)}` : '';
    const response = await api(`/api/admin/platform-fee-plans${suffix}`);
    return response.data || [];
  },

  detail(id) {
    return api(`/api/admin/platform-fee-plans/${encodeURIComponent(id)}`);
  },

  createDraft(payload) {
    return api('/api/admin/platform-fee-plans', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  update(id, payload) {
    return api(`/api/admin/platform-fee-plans/${encodeURIComponent(id)}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  schedule(id, effectiveFrom) {
    return api(`/api/admin/platform-fee-plans/${encodeURIComponent(id)}/schedule`, {
      method: 'POST',
      body: JSON.stringify({ effective_from: effectiveFrom }),
    });
  },

  cancelSchedule(id) {
    return api(`/api/admin/platform-fee-plans/${encodeURIComponent(id)}/cancel-schedule`, {
      method: 'POST',
    });
  },
};
