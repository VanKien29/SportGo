import { api } from './api';

export const courtTypeService = {
  getAll() {
    return api('/api/court-types');
  },
  create(data) {
    return api('/api/admin/court-types', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },
  update(id, data) {
    return api(`/api/admin/court-types/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },
  delete(id) {
    return api(`/api/admin/court-types/${id}`, {
      method: 'DELETE',
    });
  },
};
