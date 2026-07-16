import { api } from './api.js';

export const ownerServiceCategoryService = {
    listActive() {
        return api('/api/service-categories?active_only=1');
    }
};
