import { api } from './api';

export const amenityService = {
  getAll(activeOnly = false) {
    const url = activeOnly ? '/api/amenities?active_only=1' : '/api/amenities';
    return api(url);
  },
  create(data) {
    return api('/api/admin/amenities', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },
  update(id, data) {
    return api(`/api/admin/amenities/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },
  delete(id) {
    return api(`/api/admin/amenities/${id}`, {
      method: 'DELETE',
    });
  },
};
