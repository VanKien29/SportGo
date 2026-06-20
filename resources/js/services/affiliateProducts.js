import { api } from './api.js';

export const affiliateProductService = {
    /**
     * Lấy danh sách sản phẩm tiếp thị liên kết của cụm sân (Cho Owner)
     */
    listForOwner(clusterId) {
        return api(`/api/owner/venue-clusters/${clusterId}/affiliate-products`);
    },

    /**
     * Thêm sản phẩm tiếp thị liên kết mới (Cho Owner)
     */
    create(clusterId, formData) {
        return api(`/api/owner/venue-clusters/${clusterId}/affiliate-products`, {
            method: 'POST',
            body: formData,
        });
    },

    /**
     * Cập nhật sản phẩm tiếp thị liên kết (Cho Owner)
     */
    update(id, formData) {
        return api(`/api/owner/affiliate-products/${id}`, {
            method: 'POST',
            body: formData,
        });
    },

    /**
     * Xóa sản phẩm tiếp thị liên kết (Cho Owner)
     */
    delete(id) {
        return api(`/api/owner/affiliate-products/${id}`, {
            method: 'DELETE',
        });
    },

    /**
     * Bật/Tắt trạng thái hoạt động của sản phẩm (Cho Owner)
     */
    toggleStatus(id) {
        return api(`/api/owner/affiliate-products/${id}/toggle-status`, {
            method: 'PATCH',
        });
    },

    /**
     * Lấy danh sách sản phẩm tiếp thị liên kết đang hoạt động của cụm sân (Cho Player)
     */
    listForPublic(clusterId) {
        return api(`/api/venues/${clusterId}/affiliate-products`);
    },

    /**
     * Ghi nhận click chuột và chuyển tới link tiếp thị liên kết
     */
    trackClick(id) {
        return api(`/api/affiliate-products/${id}/click`, {
            method: 'POST',
        });
    }
};
