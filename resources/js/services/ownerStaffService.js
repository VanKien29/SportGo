import { api } from './api.js';

function withCluster(path) {
  const clusterId = localStorage.getItem('selected_cluster');
  const joiner = path.includes('?') ? '&' : '?';
  return clusterId ? `${path}${joiner}venue_cluster_id=${encodeURIComponent(clusterId)}` : path;
}

function withClusterPayload(payload = {}) {
  return {
    ...payload,
    venue_cluster_id: payload.venue_cluster_id || localStorage.getItem('selected_cluster'),
  };
}

export const ownerStaffService = {
  list() {
    return api(withCluster('/api/owner/staff'));
  },
  create(payload) {
    return api('/api/owner/staff', {
      method: 'POST',
      body: JSON.stringify(withClusterPayload(payload)),
    });
  },
  update(id, payload) {
    return api(`/api/owner/staff/${id}`, {
      method: 'PUT',
      body: JSON.stringify(withClusterPayload(payload)),
    });
  },
  deactivate(id, reason = '') {
    return api(`/api/owner/staff/${id}/deactivate`, {
      method: 'PATCH',
      body: JSON.stringify(withClusterPayload({ reason })),
    });
  },
};
