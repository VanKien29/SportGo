<template>
  <div class="matchmaking-table-section">
    <!-- Data States -->
    <div v-if="isLoading" class="table-state-card">
      <div class="spinner-sm"></div>
      <span>Đang tải danh sách bài giao lưu...</span>
    </div>

    <div v-else-if="!posts.length" class="table-state-card">
      <span>Không tìm thấy bài giao lưu nào.</span>
    </div>

    <!-- Data Table -->
    <div v-else class="matchmaking-table-wrapper">
      <table class="matchmaking-data-table">
        <thead>
          <tr>
            <th>Người đăng</th>
            <th>Thông tin buổi chơi</th>
            <th>Yêu cầu ghép</th>
            <th>Trạng thái</th>
            <th>Booking liên quan</th>
            <th class="action-col">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="post in posts" :key="post.id">
            <!-- Author -->
            <td class="cell-author">
              <div class="author-info">
                <span class="author-name">{{ post.author?.full_name || post.author?.username || 'Người chơi' }}</span>
                <span class="author-sub">{{ post.author?.phone || 'Không có SĐT' }}</span>
              </div>
            </td>

            <!-- Play Session Info -->
            <td class="cell-session">
              <div class="session-info">
                <span class="post-title-text">{{ post.title }}</span>
                <span v-if="post.description" class="post-desc-text">{{ post.description }}</span>
                <span class="post-meta-text">
                  {{ formatDate(post.booking?.booking_date) }} ({{ formatTime(post.booking?.start_time) }} - {{ formatTime(post.booking?.end_time) }})
                </span>
                <span class="post-venue-text">
                  {{ post.booking?.venueCluster?.name }} · {{ post.booking?.venueCourt?.name }}
                </span>
              </div>
            </td>

            <!-- Needed & Cost -->
            <td class="cell-needed">
              <div class="needed-info">
                <span>Cần thêm: {{ post.needed_players }} người</span>
                <span v-if="post.cost_per_player > 0" class="cost-text">
                  Chi phí: {{ formatMoney(post.cost_per_player) }} đ/người
                </span>
              </div>
            </td>

            <!-- Status -->
            <td class="cell-status">
              <div class="status-info">
                <span>{{ getStatusLabel(post.status) }}</span>
                <span v-if="post.status_reason" class="reason-text">Lý do: {{ post.status_reason }}</span>
              </div>
            </td>

            <!-- Related Booking -->
            <td class="cell-booking">
              <div v-if="post.booking" class="booking-info">
                <span>Mã: {{ post.booking.booking_code }}</span>
                <router-link
                  :to="{
                    name: 'owner-counter-booking',
                    query: {
                      venue_cluster_id: post.booking.venue_cluster_id,
                      booking_date: post.booking.booking_date,
                      venue_court_id: post.booking.venue_court_id,
                      booking_id: post.booking.id,
                      booking_code: post.booking.booking_code,
                    }
                  }"
                  class="link-btn"
                >
                  Xem lịch đặt sân
                </router-link>
              </div>
              <span v-else class="muted-dash">-</span>
            </td>

            <!-- Actions -->
            <td class="action-col">
              <div class="table-actions">
                <router-link
                  v-if="post.group_chat_id"
                  :to="{ name: 'owner-chat', query: { conversation_id: post.group_chat_id } }"
                  class="action-btn"
                >
                  Mở nhóm chat
                </router-link>
                <button
                  v-if="post.status === 'open' || post.status === 'full'"
                  type="button"
                  class="action-btn"
                  title="Ẩn bài viết"
                  @click="$emit('open-hide-modal', post)"
                >
                  Ẩn bài
                </button>
                <button
                  v-if="post.status === 'open' || post.status === 'full'"
                  type="button"
                  class="action-btn delete-btn"
                  title="Báo cáo vi phạm"
                  @click="$emit('open-report-modal', post)"
                >
                  Báo cáo
                </button>
              </div>
              <span v-if="!post.group_chat_id && post.status !== 'open' && post.status !== 'full'" class="muted-dash">-</span>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="pagination-row">
        <button
          type="button"
          class="page-btn"
          :disabled="pagination.current_page <= 1"
          @click="$emit('change-page', pagination.current_page - 1)"
        >
          Trước
        </button>
        <span class="page-num-info">Trang {{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button
          type="button"
          class="page-btn"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="$emit('change-page', pagination.current_page + 1)"
        >
          Sau
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MatchmakingTable',
  props: {
    posts: { type: Array, default: () => [] },
    clusters: { type: Array, default: () => [] },
    isLoading: { type: Boolean, default: false },
    searchQuery: { type: String, default: '' },
    filterClusterId: { type: [String, Number], default: '' },
    pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1 }) },
  },
  emits: [
    'update:search-query',
    'update:filter-cluster-id',
    'open-hide-modal',
    'open-report-modal',
    'change-page',
  ],
  methods: {
    getStatusLabel(status) {
      const map = {
        open: 'Đang tìm người',
        full: 'Đã đủ người',
        cancelled: 'Đã hủy',
        hidden: 'Đã ẩn',
        closed: 'Đã đóng',
      };
      return map[status] || status;
    },
    formatDate(val) {
      if (!val) return '-';
      const d = new Date(val);
      if (Number.isNaN(d.getTime())) return val;
      return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
    },
    formatTime(val) {
      if (!val) return '--:--';
      return String(val).substring(0, 5);
    },
    formatMoney(val) {
      const num = Number(val);
      if (!Number.isFinite(num) || num <= 0) return '0';
      return new Intl.NumberFormat('vi-VN').format(num);
    },
  },
};
</script>

<style scoped>
.matchmaking-table-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.matchmaking-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search-input-wrapper {
  flex: 1;
  min-width: 200px;
}

.search-input-wrapper input {
  width: 100%;
  height: 38px;
  padding: 0 12px;
  border-radius: 8px;
  border: 1px solid var(--admin-border, #cfded1);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #101c15);
  font-size: 13px;
  font-weight: 400;
  outline: none;
}

.search-input-wrapper input:focus {
  border-color: var(--admin-primary, #22a653);
}

.cluster-select-wrapper select {
  height: 38px;
  padding: 0 12px;
  border-radius: 8px;
  border: 1px solid var(--admin-border, #cfded1);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #101c15);
  font-size: 13px;
  font-weight: 400;
  outline: none;
  cursor: pointer;
}

/* States */
.table-state-card {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 36px 20px;
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px dashed var(--admin-border, #cfded1);
  border-radius: 8px;
  color: var(--admin-muted, #2f3d34);
  font-size: 13.5px;
  font-weight: 400;
}

.spinner-sm {
  width: 18px;
  height: 18px;
  border: 2px solid var(--admin-border, #cfded1);
  border-top-color: var(--admin-primary, #22a653);
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Data Table */
.matchmaking-table-wrapper {
  overflow-x: auto;
  border: none;
  border-radius: 0;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.matchmaking-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  text-align: left;
}

.matchmaking-data-table th {
  background: var(--admin-bg-soft, #f7fbf5);
  color: var(--admin-text, #101c15);
  font-weight: 400;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 12px 14px;
  border-bottom: none;
}

.matchmaking-data-table td {
  padding: 12px 14px;
  border-bottom: none;
  color: var(--admin-text, #101c15);
  font-weight: 400;
  vertical-align: middle;
}

.matchmaking-data-table tbody tr {
  transition: background-color 0.12s ease;
}

.matchmaking-data-table tbody tr:hover {
  background: var(--admin-hover, #edf7ed);
}

.author-info, .session-info, .needed-info, .status-info, .booking-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.author-name {
  font-weight: 400;
  color: var(--admin-text, #101c15);
}

.author-sub, .post-desc-text, .post-meta-text, .post-venue-text, .cost-text, .reason-text {
  font-size: 12px;
  font-weight: 400;
  color: var(--admin-muted, #2f3d34);
}

.post-title-text {
  font-weight: 400;
  color: var(--admin-text, #101c15);
}

.link-btn {
  font-size: 12px;
  color: var(--admin-primary, #22a653);
  text-decoration: none;
  font-weight: 400;
}

.link-btn:hover {
  text-decoration: underline;
}

.action-col {
  text-align: center;
  width: 120px;
}

.table-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.action-btn {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 400;
  border: 1px solid var(--admin-border-soft, #e3ece4);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #101c15);
  cursor: pointer;
  transition: all 0.15s ease;
}

.action-btn:hover {
  background: var(--admin-hover, #edf7ed);
}

.action-btn.delete-btn {
  color: var(--admin-text, #101c15);
}

.muted-dash {
  color: var(--admin-muted, #2f3d34);
  font-size: 12px;
}

/* Pagination */
.pagination-row {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 8px;
}

.page-btn {
  padding: 6px 14px;
  border-radius: 6px;
  border: 1px solid var(--admin-border-soft, #e3ece4);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #101c15);
  font-size: 12.5px;
  font-weight: 400;
  cursor: pointer;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-num-info {
  font-size: 12.5px;
  color: var(--admin-muted, #2f3d34);
  font-weight: 400;
}
</style>
