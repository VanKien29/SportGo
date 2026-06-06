import { api } from './api.js';

function query(params = {}) {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) search.set(key, value);
  });
  return search.toString() ? `?${search}` : '';
}

export const adminReportService = {
  list(params = {}) {
    return api(`/api/admin/reports${query(params)}`);
  },
  show(id) {
    return api(`/api/admin/reports/${id}`);
  },
  review(id) {
    return api(`/api/admin/reports/${id}/review`, { method: 'PATCH' });
  },
  resolve(id, payload) {
    return api(`/api/admin/reports/${id}/resolve`, {
      method: 'PATCH',
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
  resolve(id, payload) {
    return api(`/api/admin/complaints/${id}/resolve`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },
};
