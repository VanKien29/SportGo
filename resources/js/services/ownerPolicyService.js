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

export const ownerPolicyService = {
  list(clusterId = null) {
    const path = clusterId
      ? `/api/owner/venue-policies?venue_cluster_id=${encodeURIComponent(clusterId)}`
      : withCluster('/api/owner/venue-policies');

    return api(path);
  },
  saveRule(payload) {
    return api('/api/owner/venue-policies/rules', {
      method: 'POST',
      body: JSON.stringify(payloadWithCluster(payload)),
    });
  },
  createNotice(payload) {
    return api('/api/owner/venue-policies/notices', {
      method: 'POST',
      body: JSON.stringify(payloadWithCluster(payload)),
    });
  },
  updateNotice(id, payload) {
    return api(`/api/owner/venue-policies/notices/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payloadWithCluster(payload)),
    });
  },
  resetRule(id, venueClusterId = null) {
    return api(`/api/owner/venue-policies/rules/${id}`, {
      method: 'DELETE',
      body: JSON.stringify(payloadWithCluster({ venue_cluster_id: venueClusterId })),
    });
  },
};
