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
  clusters() {
    return api('/api/owner/venue-clusters');
  },
  list() {
    return api(withCluster('/api/owner/venue-policies'));
  },
  saveRule(payload) {
    return api('/api/owner/venue-policies/rules', {
      method: 'POST',
      body: JSON.stringify(payloadWithCluster(payload)),
    });
  },
  resetRule(id, payload = {}) {
    return api(`/api/owner/venue-policies/rules/${id}`, {
      method: 'DELETE',
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
};
