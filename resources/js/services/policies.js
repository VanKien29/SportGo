import { api } from './api.js';

export const policyService = {
  list() {
    return api('/api/policies', { cache: 'no-store', dedupe: false });
  },

  required() {
    return api('/api/policies/required');
  },

  accept(id) {
    return api(`/api/policies/${id}/accept`, {
      method: 'POST',
    });
  },
};
