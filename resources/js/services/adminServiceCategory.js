import { api } from './api.js';

export const adminServiceCategoryService = {
    list(params = {}) {
        const query = new URLSearchParams(params).toString();
        return api(`/api/admin/service-categories?${query}`);
    },
    create(data) {
        return api('/api/admin/service-categories', {
            method: 'POST',
            body: JSON.stringify(data),
            headers: { 'Content-Type': 'application/json' },
        });
    },
    update(id, data) {
        return api(`/api/admin/service-categories/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
            headers: { 'Content-Type': 'application/json' },
        });
    },
    delete(id) {
        return api(`/api/admin/service-categories/${id}`, {
            method: 'DELETE',
        });
    },
    toggleStatus(id) {
        return api(`/api/admin/service-categories/${id}/toggle-status`, {
            method: 'PATCH',
        });
    }
};
