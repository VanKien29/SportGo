import { api } from './api.js';

export const adminVenueClusterService = {
  /**
   * Lấy danh sách cụm sân toàn hệ thống
   * @param {Object} params - { status, search, owner_id }
   */
  list(params = {}) {
    const query = new URLSearchParams();
    if (params.status) query.set('status', params.status);
    if (params.search) query.set('search', params.search);
    if (params.owner_id) query.set('owner_id', params.owner_id);
    const qs = query.toString();
    return api(`/api/admin/venue-clusters${qs ? `?${qs}` : ''}`);
  },

  /**
   * Lấy chi tiết cụm sân (bao gồm bookings, fees, lock history, approval requests)
   * @param {string} id
   */
  show(id) {
    return api(`/api/admin/venue-clusters/${id}`);
  },

  /**
   * Khóa cụm sân
   * @param {string} id
   * @param {{ status_reason: string, locked_until?: string }} payload
   */
  lock(id, payload) {
    return api(`/api/admin/venue-clusters/${id}/lock`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },

  /**
   * Mở khóa cụm sân
   * @param {string} id
   */
  unlock(id) {
    return api(`/api/admin/venue-clusters/${id}/unlock`, {
      method: 'PATCH',
    });
  },

  /**
   * Duyệt yêu cầu mở rộng / thu hẹp
   * @param {string} clusterId
   * @param {string} requestId
   */
  approveRequest(clusterId, requestId) {
    return api(`/api/admin/venue-clusters/${clusterId}/approval-requests/${requestId}/approve`, {
      method: 'PATCH',
    });
  },

  /**
   * Từ chối yêu cầu mở rộng / thu hẹp
   * @param {string} clusterId
   * @param {string} requestId
   * @param {{ status_reason: string }} payload
   */
  rejectRequest(clusterId, requestId, payload) {
    return api(`/api/admin/venue-clusters/${clusterId}/approval-requests/${requestId}/reject`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },

  /**
   * Cập nhật danh sách tiện ích của cụm sân (Admin)
   * @param {string} id
   * @param {{ amenities: string[] }} payload
   */
  updateAmenities(id, payload) {
    return api(`/api/admin/venue-clusters/${id}/amenities`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },

  /**
   * Duyệt yêu cầu thay đổi vị trí
   * @param {string} clusterId
   * @param {string} requestId
   */
  approveLocationChange(clusterId, requestId) {
    return api(`/api/admin/venue-clusters/${clusterId}/location-change-requests/${requestId}/approve`, {
      method: 'PATCH',
    });
  },

  /**
   * Từ chối yêu cầu thay đổi vị trí
   * @param {string} clusterId
   * @param {string} requestId
   * @param {{ status_reason: string }} payload
   */
  rejectLocationChange(clusterId, requestId, payload) {
    return api(`/api/admin/venue-clusters/${clusterId}/location-change-requests/${requestId}/reject`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },
};
