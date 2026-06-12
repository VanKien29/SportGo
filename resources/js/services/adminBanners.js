import { api, apiFormData } from './api.js';

function query(params = {}) {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      search.set(key, value);
    }
  });

  return search.toString() ? `?${search.toString()}` : '';
}

export const adminBannerService = {
  list(params = {}) {
    return api(`/api/admin/banners${query(params)}`);
  },

  create(formData) {
    return apiFormData('/api/admin/banners', formData);
  },

  update(id, formData) {
    formData.append('_method', 'PATCH');
    return apiFormData(`/api/admin/banners/${id}`, formData);
  },

  remove(id) {
    return api(`/api/admin/banners/${id}`, { method: 'DELETE' });
  },

  reorder(bannerIds) {
    return api('/api/admin/banners/reorder', {
      method: 'POST',
      body: JSON.stringify({ banner_ids: bannerIds }),
    });
  },
};
