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
