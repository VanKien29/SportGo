import { api } from './api';

export const venueClusterService = {
  // Venue Clusters (Owner)
  getClusters(params = {}) {
    const query = new URLSearchParams(params).toString();
    return api(`/api/owner/venue-clusters${query ? `?${query}` : ''}`);
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
  getCourts(clusterId, params = {}) {
    const query = new URLSearchParams({
      venue_cluster_id: clusterId,
      ...params,
    }).toString();

    return api(`/api/owner/venue-courts?${query}`);
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
  createApprovalRequest(clusterId, formData) {
    return api(`/api/owner/venue-clusters/${clusterId}/approval-requests`, {
      method: 'POST',
      body: formData,
    });
  },
  supplementApprovalRequest(clusterId, requestId, formData) {
    return api(`/api/owner/venue-clusters/${clusterId}/approval-requests/${requestId}/supplement`, {
      method: 'POST',
      body: formData,
    });
  },
  cancelApprovalRequest(clusterId, requestId) {
    return api(`/api/owner/venue-clusters/${clusterId}/approval-requests/${requestId}/cancel`, {
      method: 'PATCH',
    });
  },

  // Venue Location Change Requests (Owner gửi yêu cầu thay đổi vị trí)
  getLocationChangeRequests(clusterId, status = null) {
    const qs = status ? `?status=${status}` : '';
    return api(`/api/owner/venue-clusters/${clusterId}/location-change-requests${qs}`);
  },
  createLocationChangeRequest(clusterId, data) {
    return api(`/api/owner/venue-clusters/${clusterId}/location-change-requests`, {
      method: 'POST',
      body: data instanceof FormData ? data : JSON.stringify(data),
    });
  },
  supplementLocationChangeRequest(clusterId, requestId, formData) {
    return api(`/api/owner/venue-clusters/${clusterId}/location-change-requests/${requestId}/supplement`, {
      method: 'POST',
      body: formData,
    });
  },
  cancelLocationChangeRequest(clusterId, requestId) {
    return api(`/api/owner/venue-clusters/${clusterId}/location-change-requests/${requestId}/cancel`, {
      method: 'PATCH',
    });
  },
};
