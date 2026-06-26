import { api } from './api.js';

export const vipMembershipService = {
  playerIndex() {
    return api('/api/vip-membership');
  },
  subscribe(payload) {
    return api('/api/vip-membership/subscribe', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },
  adminPackages() {
    return api('/api/admin/membership-packages');
  },
  updateAdminPackage(id, payload) {
    return api(`/api/admin/membership-packages/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },
};
