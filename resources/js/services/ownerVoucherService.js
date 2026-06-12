import { api } from './api.js';

function withCluster(path) {
  const clusterId = localStorage.getItem('selected_cluster');
  const joiner = path.includes('?') ? '&' : '?';
  return clusterId ? `${path}${joiner}venue_cluster_id=${encodeURIComponent(clusterId)}` : path;
}

function payloadWithCluster(payload = {}) {
  return {
    ...payload,
    venue_cluster_id: payload.venue_cluster_id || localStorage.getItem('selected_cluster'),
  };
}

export const ownerVoucherService = {
  list() {
    return api(withCluster('/api/owner/vouchers'));
  },
  create(payload) {
    return api('/api/owner/vouchers', {
      method: 'POST',
      body: JSON.stringify(payloadWithCluster(payload)),
    });
  },
  update(id, payload) {
    return api(`/api/owner/vouchers/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payloadWithCluster(payload)),
    });
  },
  deactivate(id, reason = '') {
    return api(`/api/owner/vouchers/${id}/deactivate`, {
      method: 'PATCH',
      body: JSON.stringify(payloadWithCluster({ reason })),
    });
  },
};
