import { api } from './api.js';

export const adminUiSettingsService = {
  getSettings() {
    return api('/api/admin/ui-settings');
  },

  updateSettings(payload) {
    return api('/api/admin/ui-settings', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },
};
