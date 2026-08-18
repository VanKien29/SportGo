import { api } from './api';

export const courtTypeService = {
  getAll() {
    return api('/api/court-types');
  },
  // Public/client screens consume the list directly while the admin screen
  // uses the raw API envelope. Keep both shapes explicit so a missing helper
  // cannot silently turn every sport combobox into an empty list.
  async getCourtTypes() {
    const response = await this.getAll();
    return response?.data || response || [];
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
  requestNew(data) {
    return api('/api/owner/court-types/request', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },
};
