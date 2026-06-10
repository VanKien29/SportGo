import { api } from './api.js';

function query(params = {}) {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      search.set(key, value);
    }
  });

  return search.toString() ? `?${search.toString()}` : '';
}

export const adminPolicyService = {
  list(params = {}) {
    return api(`/api/admin/policies${query(params)}`);
  },

  show(id) {
    return api(`/api/admin/policies/${id}`);
  },

  create(payload) {
    return api('/api/admin/policies', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  delete(id) {
    return api(`/api/admin/policies/${id}`, { method: 'DELETE' });
  },

  update(id, payload) {
    return api(`/api/admin/policies/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  cloneVersion(id) {
    return api(`/api/admin/policies/${id}/clone-version`, { method: 'POST' });
  },

  publish(id) {
    return api(`/api/admin/policies/${id}/publish`, { method: 'POST' });
  },

  updateStatus(id, payload) {
    return api(`/api/admin/policies/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },

  saveCancelRefundTiers(id, payload) {
    return api(`/api/admin/policies/${id}/cancel-refund-tiers`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  saveModerationThresholds(id, payload) {
    return api(`/api/admin/policies/${id}/moderation-thresholds`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  addBinding(id, payload) {
    return api(`/api/admin/policies/${id}/bindings`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  disableBinding(id, bindingId) {
    return api(`/api/admin/policies/${id}/bindings/${bindingId}`, { method: 'DELETE' });
  },

  addRule(id, payload) {
    return api(`/api/admin/policies/${id}/rules`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  updateRule(id, ruleId, payload) {
    return api(`/api/admin/policies/${id}/rules/${ruleId}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  showRule(id, ruleId) {
    return api(`/api/admin/policies/${id}/rules/${ruleId}`);
  },

  toggleRule(id, ruleId) {
    return api(`/api/admin/policies/${id}/rules/${ruleId}/toggle`, { method: 'PATCH' });
  },

  actionCodes(params = {}) {
    return api(`/api/admin/policies/action-codes${query(params)}`);
  },

  ruleTemplates(params = {}) {
    return api(`/api/admin/policies/rule-templates${query(params)}`);
  },
};
