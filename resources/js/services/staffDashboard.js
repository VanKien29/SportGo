import { api } from './api.js';

export const staffDashboardService = {
  getOverview(params = {}) {
    const searchParams = new URLSearchParams();
    if (params.venue_cluster_id) {
      searchParams.set('venue_cluster_id', params.venue_cluster_id);
    }
    return api(`/api/owner/staff-dashboard/overview?${searchParams.toString()}`);
  }
};
