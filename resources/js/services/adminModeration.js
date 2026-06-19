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

export const adminModerationService = {
  getQueue(params = {}) {
    return api(`/api/admin/moderation/queue${query(params)}`);
  },

  getConfig() {
    return api('/api/admin/moderation/config');
  },

  saveConfig(data) {
    return api('/api/admin/moderation/config', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  approvePost(type, id) {
    return api(`/api/admin/moderation/posts/${type}/${id}/approve`, {
      method: 'POST',
    });
  },

  rejectPost(type, id, reason) {
    return api(`/api/admin/moderation/posts/${type}/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  },

  hidePost(type, id, reason) {
    return api(`/api/admin/moderation/posts/${type}/${id}/hide`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  },

  deletePost(type, id, reason = '') {
    return api(`/api/admin/moderation/posts/${type}/${id}`, {
      method: 'DELETE',
      body: JSON.stringify({ reason }),
    });
  },

  resolveReport(id, data) {
    return api(`/api/admin/moderation/reports/${id}/resolve`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },
};

export const adminReportService = {
  list(params = {}) {
    return api(`/api/admin/reports${query(params)}`);
  },
  show(id) {
    return api(`/api/admin/reports/${id}`);
  },
  review(id) {
    return api(`/api/admin/reports/${id}/review`, {
      method: 'PATCH',
    });
  },
  resolve(id, data) {
    return api(`/api/admin/reports/${id}/resolve`, {
      method: 'PATCH',
      body: JSON.stringify(data),
    });
  },
  getAutoResolveConfig() {
    return api('/api/admin/reports/auto-resolve-config');
  },
  saveAutoResolveConfig(payload) {
    return api('/api/admin/report-resolve-policy', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },
};

export const adminComplaintService = {
  list(params = {}) {
    return api(`/api/admin/complaints${query(params)}`);
  },
  show(id) {
    return api(`/api/admin/complaints/${id}`);
  },
  assign(id, assignedTo) {
    return api(`/api/admin/complaints/${id}/assign`, {
      method: 'PATCH',
      body: JSON.stringify({ assigned_to: assignedTo }),
    });
  },
  resolve(id, data) {
    return api(`/api/admin/complaints/${id}/resolve`, {
      method: 'PATCH',
      body: JSON.stringify(data),
    });
  },
};

