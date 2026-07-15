import { api } from './api.js';

export async function fetchWorkCenter(audience) {
  return api(`/api/${audience}/work-center`);
}

export async function markWorkNotificationRead(audience, notificationId) {
  return api(`/api/${audience}/work-center/notifications/${notificationId}/read`, {
    method: 'PATCH',
  });
}

