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

export const ownerPostService = {
  list(params = {}) {
    return api(`/api/owner/posts${query(params)}`);
  },

  show(id) {
    return api(`/api/owner/posts/${id}`);
  },

  create(formData) {
    return api('/api/owner/posts', {
      method: 'POST',
      body: formData, // FormData handles file upload and does not set Content-Type header
    });
  },

  update(id, formData) {
    // Laravel requires POST with _method = PUT when uploading files in an update request
    if (formData instanceof FormData && !formData.has('_method')) {
      formData.append('_method', 'PUT');
    }

    return api(`/api/owner/posts/${id}`, {
      method: 'POST',
      body: formData,
    });
  },

  delete(id) {
    return api(`/api/owner/posts/${id}`, {
      method: 'DELETE',
    });
  },
};
