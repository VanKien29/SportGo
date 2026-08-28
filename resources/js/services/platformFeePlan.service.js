import { api } from './api.js';

export const platformFeePlanService = {
  async list(filters = {}) {
    const query = new URLSearchParams();
    Object.entries(typeof filters === 'string' ? { status: filters } : filters).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) query.set(key, value);
    });
    const response = await api(`/api/admin/platform-fee-plans${query.toString() ? `?${query}` : ''}`);
    return {
      items: response.data || [],
      meta: {
        current_page: Number(response.current_page || 1),
        last_page: Number(response.last_page || 1),
        per_page: Number(response.per_page || 15),
        total: Number(response.total || (response.data || []).length),
        status_summary: response.status_summary || null,
      },
    };
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

  schedule(id, effectiveFrom, draft = null) {
    return api(`/api/admin/platform-fee-plans/${encodeURIComponent(id)}/schedule`, {
      method: 'POST',
      body: JSON.stringify({ effective_from: effectiveFrom, ...(draft || {}) }),
    });
  },

  cancelSchedule(id) {
    return api(`/api/admin/platform-fee-plans/${encodeURIComponent(id)}/cancel-schedule`, {
      method: 'POST',
    });
  },

  remove(id) {
    return api(`/api/admin/platform-fee-plans/${encodeURIComponent(id)}`, {
      method: 'DELETE',
    });
  },
};
