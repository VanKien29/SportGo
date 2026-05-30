import { api } from './api.js';

export const policyService = {
  required() {
    return api('/api/policies/required');
  },

  accept(id) {
    return api(`/api/policies/${id}/accept`, {
      method: 'POST',
    });
  },
};
