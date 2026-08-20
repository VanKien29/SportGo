import { api } from './api.js';

function withCluster(path) {
  const clusterId = localStorage.getItem('selected_cluster');
  const joiner = path.includes('?') ? '&' : '?';
  return clusterId ? `${path}${joiner}venue_cluster_id=${encodeURIComponent(clusterId)}` : path;
}

function withClusterPayload(payload = {}) {
  return {
    ...payload,
    venue_cluster_id: payload.venue_cluster_id || localStorage.getItem('selected_cluster'),
  };
}

export const ownerStaffShiftService = {
  // Shift templates
  listShifts() {
    return api(withCluster('/api/owner/staff-shifts'));
  },
  createShift(payload) {
    return api('/api/owner/staff-shifts', {
      method: 'POST',
      body: JSON.stringify(withClusterPayload(payload)),
    });
  },
  updateShift(id, payload) {
    return api(`/api/owner/staff-shifts/${id}`, {
      method: 'PUT',
      body: JSON.stringify(withClusterPayload(payload)),
    });
  },
  deleteShift(id) {
    return api(withCluster(`/api/owner/staff-shifts/${id}`), {
      method: 'DELETE',
    });
  },

  // Schedules
  listSchedules(params = {}) {
    let url = `/api/owner/staff-shifts/schedules`;
    const searchParams = new URLSearchParams();
    searchParams.set('venue_cluster_id', localStorage.getItem('selected_cluster') || '');
    if (params.start_date) searchParams.set('start_date', params.start_date);
    if (params.end_date) searchParams.set('end_date', params.end_date);
    if (params.user_id) searchParams.set('user_id', params.user_id);
    return api(`${url}?${searchParams.toString()}`);
  },
  createSchedules(payload) {
    return api('/api/owner/staff-shifts/schedules', {
      method: 'POST',
      body: JSON.stringify(withClusterPayload(payload)),
    });
  },
  updateSchedule(id, payload) {
    return api(`/api/owner/staff-shifts/schedules/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },
  deleteSchedule(id) {
    return api(withCluster(`/api/owner/staff-shifts/schedules/${id}`), {
      method: 'DELETE',
    });
  },
  attendanceReport(params = {}) {
    let url = `/api/owner/staff-shifts/attendance-report`;
    const searchParams = new URLSearchParams();
    searchParams.set('venue_cluster_id', localStorage.getItem('selected_cluster') || '');
    if (params.start_date) searchParams.set('start_date', params.start_date);
    if (params.end_date) searchParams.set('end_date', params.end_date);
    return api(`${url}?${searchParams.toString()}`);
  },

  // Staff check-in / check-out
  mySchedules(params = {}) {
    let url = `/api/owner/staff-shifts/my-schedules`;
    const searchParams = new URLSearchParams();
    if (params.start_date) searchParams.set('start_date', params.start_date);
    if (params.end_date) searchParams.set('end_date', params.end_date);
    return api(`${url}?${searchParams.toString()}`);
  },
  handoverSummary(id) {
    return api(`/api/owner/staff-shifts/schedules/${id}/handover-summary`);
  },
  checkIn(id) {
    return api(`/api/owner/staff-shifts/schedules/${id}/check-in`, {
      method: 'POST',
    });
  },
  checkOut(id, payload = {}) {
    return api(`/api/owner/staff-shifts/schedules/${id}/check-out`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },
};
