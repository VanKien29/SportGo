import { api } from './api.js';

function buildQuery(params = {}) {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      query.set(key, value);
    }
  });

  return query.toString();
}

export const adminUserService = {
  list(params = {}) {
    const query = buildQuery(params);
    return api(`/api/admin/users${query ? `?${query}` : ''}`);
  },

  show(id) {
    return api(`/api/admin/users/${id}`);
  },

  get(id) {
    return api(`/api/admin/users/${id}`);
  },

  create(payload) {
    return api('/api/admin/users', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  update(id, payload) {
    return api(`/api/admin/users/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  lock(id, payload) {
    return api(`/api/admin/users/${id}/lock`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },

  unlock(id, payload) {
    return api(`/api/admin/users/${id}/unlock`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },

  // --- User Lock Management (new) ---

  lockUser(id, payload) {
    return api(`/api/admin/users/${id}/lock`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  unlockUser(id, payload) {
    return api(`/api/admin/users/${id}/unlock`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  lockLogs(id, page = 1) {
    return api(`/api/admin/users/${id}/lock-logs?page=${page}`);
  },

  // --- Comment & Post detail ---

  commentDetail(id) {
    return api(`/api/admin/comments/${id}`);
  },

  postDetail(id, page = 1) {
    return api(`/api/admin/posts/${id}?page=${page}`);
  },

  // --- User Lock Policy ---

  getLockPolicy() {
    return api('/api/admin/user-lock-policy');
  },

  saveLockPolicy(payload) {
    return api('/api/admin/user-lock-policy', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },
};
