/**
 * adminPendingCounts.js
 * 
 * Reactive store theo dõi số lượng công việc pending của admin.
 * Poll mỗi 60 giây. Cung cấp badge numbers cho AdminSidebar.
 */

import { reactive } from 'vue';
import { api } from './api.js';

// ── Reactive state ──────────────────────────────────────────────
export const pendingCounts = reactive({
  partner_applications: 0,
  venue_clusters: 0,
  finance: 0,
  moderation_support: 0,
  // chi tiết
  detail: {
    scale_approvals: 0,
    location_changes: 0,
    info_changes: 0,
    refunds: 0,
    withdrawals: 0,
    reports: 0,
    moderation_posts: 0,
  },
  lastFetched: null,
  loading: false,
  error: null,
});

let _pollTimer = null;

// ── Fetch ────────────────────────────────────────────────────────
export async function fetchPendingCounts() {
  if (pendingCounts.loading) return;
  pendingCounts.loading = true;
  pendingCounts.error = null;
  try {
    const res = await api('/api/admin/pending-counts');
    const data = res?.data || {};
    pendingCounts.partner_applications = data.partner_applications ?? 0;
    pendingCounts.venue_clusters = data.venue_clusters ?? 0;
    pendingCounts.finance = data.finance ?? 0;
    pendingCounts.moderation_support = data.moderation_support ?? 0;
    if (data.detail) {
      Object.assign(pendingCounts.detail, data.detail);
    }
    pendingCounts.lastFetched = Date.now();
  } catch (err) {
    pendingCounts.error = err?.message || 'Lỗi tải counts';
  } finally {
    pendingCounts.loading = false;
  }
}

// ── Auto-poll ───────────────────────────────────────────────────
export function startPendingCountsPoll(intervalMs = 60_000) {
  stopPendingCountsPoll();
  fetchPendingCounts(); // fetch ngay lần đầu
  _pollTimer = setInterval(fetchPendingCounts, intervalMs);
}

export function stopPendingCountsPoll() {
  if (_pollTimer) {
    clearInterval(_pollTimer);
    _pollTimer = null;
  }
}

// ── Helper: badge label (99+ if large) ──────────────────────────
export function badgeLabel(count) {
  if (!count || count <= 0) return null;
  return count > 99 ? '99+' : String(count);
}
