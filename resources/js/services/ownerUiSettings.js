import { api } from './api.js';

export const ownerUiSettingsService = {
  getSettings() {
    return api('/api/owner/ui-settings');
  },

  updateSettings(payload) {
    return api('/api/owner/ui-settings', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },
};
