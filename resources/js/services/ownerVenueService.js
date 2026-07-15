import { api } from './api.js';

export const ownerVenueService = {
    /**
     * Lấy danh sách dịch vụ/sản phẩm tại sân (Cho Owner)
     */
    listForOwner(clusterId) {
        return api(`/api/owner/venue-clusters/${clusterId}/services`);
    },

    /**
     * Thêm dịch vụ/sản phẩm mới (Cho Owner)
     */
    create(clusterId, data) {
        return api(`/api/owner/venue-clusters/${clusterId}/services`, {
            method: 'POST',
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json',
            },
        });
    },

    /**
     * Cập nhật dịch vụ/sản phẩm (Cho Owner)
     */
    update(id, data) {
        return api(`/api/owner/venue-services/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json',
            },
        });
    },

    /**
     * Xóa dịch vụ/sản phẩm (Cho Owner)
     */
    delete(id) {
        return api(`/api/owner/venue-services/${id}`, {
            method: 'DELETE',
        });
    },

    /**
     * Bật/Tắt trạng thái hoạt động của dịch vụ (Cho Owner)
     */
    toggleStatus(id) {
        return api(`/api/owner/venue-services/${id}/toggle-status`, {
            method: 'PATCH',
        });
    }
};
