import { api } from './api';

export const venueClusterService = {
  // Venue Clusters (Owner)
  getClusters() {
    return api('/api/owner/venue-clusters');
  },
  getClusterDetails(id) {
    return api(`/api/owner/venue-clusters/${id}`);
  },
  updateCluster(id, data) {
    return api(`/api/owner/venue-clusters/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },
  resolveMapUrl(url) {
    return api('/api/venue-clusters/resolve-map', {
      method: 'POST',
      body: JSON.stringify({ url }),
    });
  },

  // Venue Courts (Owner)
  getCourts(clusterId) {
    return api(`/api/owner/venue-courts?venue_cluster_id=${clusterId}`);
  },
  createCourt(data) {
    return api('/api/owner/venue-courts', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },
  updateCourt(id, data) {
    return api(`/api/owner/venue-courts/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },
  deleteCourt(id) {
    return api(`/api/owner/venue-courts/${id}`, {
      method: 'DELETE',
    });
  },
  updateCourtsLayout(data) {
    return api('/api/owner/venue-courts/bulk-layout', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },
  uploadMedia(clusterId, formData) {
    return api(`/api/owner/venue-clusters/${clusterId}/media`, {
      method: 'POST',
      body: formData,
    });
  },
  deleteMedia(clusterId, mediaId) {
    return api(`/api/owner/venue-clusters/${clusterId}/media/${mediaId}`, {
      method: 'DELETE',
    });
  },

  // Venue Court Approval Requests (Owner gửi yêu cầu quy mô)
  getApprovalRequests(clusterId, status = null) {
    const qs = status ? `?status=${status}` : '';
    return api(`/api/owner/venue-clusters/${clusterId}/approval-requests${qs}`);
  },
  createApprovalRequest(clusterId, data) {
    return api(`/api/owner/venue-clusters/${clusterId}/approval-requests`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },
  cancelApprovalRequest(clusterId, requestId) {
    return api(`/api/owner/venue-clusters/${clusterId}/approval-requests/${requestId}/cancel`, {
      method: 'PATCH',
    });
  },
};

