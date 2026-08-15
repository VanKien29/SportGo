<template>
  <div class="profile-section-card">
    <!-- Main Profile Section Card (Đồng bộ 1:1 với các Tab trong Cụm Sân) -->
    <div class="tab-section-header">
        <div>
          <h2>Yêu cầu thay đổi thông tin</h2>
          <p class="section-subtitle">
            Theo dõi lịch sử và tiến độ xét duyệt các yêu cầu thay đổi tên, hotline, mô tả và bộ sưu tập ảnh cụm sân.
          </p>
        </div>
      </div>

      <!-- Filter Bar (Tabs lọc trạng thái) -->
      <div v-if="requestsList.length > 0" class="table-filter-bar">
        <div class="filter-tabs">
          <button
            type="button"
            class="tab-btn"
            :class="{ active: filterStatus === '' }"
            @click="filterStatus = ''"
          >
            Tất cả ({{ requestsList.length }})
          </button>
          <button
            type="button"
            class="tab-btn"
            :class="{ active: filterStatus === 'pending' }"
            @click="filterStatus = 'pending'"
          >
            Chờ duyệt ({{ countStatus('pending') }})
          </button>
          <button
            type="button"
            class="tab-btn"
            :class="{ active: filterStatus === 'approved' }"
            @click="filterStatus = 'approved'"
          >
            Đã duyệt ({{ countStatus('approved') }})
          </button>
          <button
            type="button"
            class="tab-btn"
            :class="{ active: filterStatus === 'rejected' }"
            @click="filterStatus = 'rejected'"
          >
            Từ chối ({{ countStatus('rejected') }})
          </button>
          <button
            type="button"
            class="tab-btn"
            :class="{ active: filterStatus === 'cancelled' }"
            @click="filterStatus = 'cancelled'"
          >
            Đã hủy ({{ countStatus('cancelled') }})
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="table-state-card">
        <div class="spinner-sm"></div>
        <span>Đang tải danh sách yêu cầu thay đổi...</span>
      </div>

      <!-- Empty State -->
      <div v-else-if="!requestsList.length" class="table-state-card">
        <span>Chưa có yêu cầu thay đổi thông tin nào được gửi.</span>
      </div>

      <!-- Empty Filter State -->
      <div v-else-if="filteredRequests.length === 0" class="table-state-card">
        <span>Không tìm thấy yêu cầu nào phù hợp với bộ lọc.</span>
        <button
          type="button"
          class="btn btn-outline"
          style="margin-top: 6px;"
          @click="filterStatus = ''"
        >
          Xóa bộ lọc
        </button>
      </div>

      <!-- Audit History List (Bố cục đồng bộ 1:1 với Hồ sơ đối tác) -->
      <div v-else class="audit-history-list">
        <div
          v-for="(item, idx) in filteredRequests"
          :key="item.id"
          class="history-audit-item"
        >
          <div class="audit-axis-col">
            <div class="audit-status-dot" :class="'dot--' + item.status"></div>
            <div v-if="idx < filteredRequests.length - 1" class="audit-status-line"></div>
          </div>

          <div class="audit-item-body">
            <div class="audit-item-header">
              <div class="audit-title-group">
                <span class="audit-code">#{{ item.request_type === 'location' ? 'YCVT' : 'YCTD' }}-{{ item.id }}</span>
                <span class="req-status-pill" :class="'pill-' + item.status">
                  {{ formatStatusLabel(item.status) }}
                </span>
              </div>
              <time class="audit-timestamp">{{ formatDate(item.created_at) }}</time>
            </div>

            <!-- Owner Reason Note -->
            <p v-if="item.note" class="audit-note-text">
              <span class="note-label">Lý do gửi:</span> {{ item.note }}
            </p>

            <!-- Meta Info Grid (Cấu trúc thông tin 2 cột phẳng đồng bộ) -->
            <div class="meta-info-grid">
              <div v-if="item.request_type === 'location' && item.new_address" class="meta-info-item full-width">
                <span class="meta-info-label">Địa chỉ mới</span>
                <span class="meta-info-value highlight">{{ locationAddress(item) }}</span>
              </div>
              <div v-if="item.request_type === 'location' && item.new_latitude != null" class="meta-info-item">
                <span class="meta-info-label">Tọa độ mới</span>
                <span class="meta-info-value">{{ item.new_latitude }}, {{ item.new_longitude }}</span>
              </div>
              <div v-if="item.new_name" class="meta-info-item">
                <span class="meta-info-label">Tên cụm sân</span>
                <span class="meta-info-value highlight">{{ item.new_name }}</span>
              </div>
              <div v-if="item.new_phone_contact" class="meta-info-item">
                <span class="meta-info-label">Hotline</span>
                <span class="meta-info-value">{{ item.new_phone_contact }}</span>
              </div>
              <div v-if="item.new_description" class="meta-info-item full-width">
                <span class="meta-info-label">Mô tả</span>
                <span class="meta-info-value desc-text">{{ item.new_description }}</span>
              </div>
              <div v-if="item.new_images && item.new_images.length" class="meta-info-item full-width">
                <span class="meta-info-label">Bộ sưu tập ảnh ({{ item.new_images.length }} ảnh)</span>
                <div class="req-img-thumbs">
                  <div
                    v-for="(img, imgIdx) in item.new_images"
                    :key="imgIdx"
                    class="req-img-thumb"
                  >
                    <img :src="imgUrl(img)" alt="Ảnh mới" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Admin Review Feedback Box -->
            <div v-if="item.status_reason || item.reviewed_at" class="req-admin-feedback">
              <div class="feedback-head">
                <span>Phản hồi từ Admin ({{ formatDate(item.reviewed_at) }}):</span>
              </div>
              <p class="feedback-text">{{ item.status_reason || 'Đã duyệt cập nhật thông tin.' }}</p>
            </div>

            <!-- Action Button -->
            <div v-if="canCancel(item)" class="audit-actions">
              <button
                type="button"
                class="btn-cancel-req"
                :disabled="isClusterLocked"
                @click="$emit('cancel-request', item)"
              >
                Hủy yêu cầu này
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
</template>

<script>
export default {
  name: 'ClusterRequestsCenterTab',
  props: {
    infoRequests: { type: Array, default: () => [] },
    locationRequests: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    isClusterLocked: { type: Boolean, default: false },
  },
  emits: ['cancel-request'],
  data() {
    return {
      filterStatus: '',
    };
  },
  computed: {
    requestsList() {
      const infoRequests = (Array.isArray(this.infoRequests) ? this.infoRequests : [])
        .map((request) => ({ ...request, request_type: 'info' }));
      const locationRequests = (Array.isArray(this.locationRequests) ? this.locationRequests : [])
        .map((request) => ({ ...request, request_type: 'location' }));

      return [...infoRequests, ...locationRequests].sort(
        (first, second) => new Date(second.created_at || 0) - new Date(first.created_at || 0),
      );
    },
    filteredRequests() {
      if (!this.filterStatus) return this.requestsList;
      return this.requestsList.filter((request) => this.statusMatches(request, this.filterStatus));
    },
  },
  methods: {
    countStatus(status) {
      return this.requestsList.filter((request) => this.statusMatches(request, status)).length;
    },
    statusMatches(request, filterStatus) {
      if (filterStatus === 'pending') {
        return ['pending', 'pending_owner_signature', 'need_supplement'].includes(request.status);
      }
      if (filterStatus === 'approved') {
        return ['approved', 'approved_pending_appendix', 'completed'].includes(request.status);
      }
      return request.status === filterStatus;
    },
    canCancel(request) {
      return request.request_type === 'location'
        ? ['pending_owner_signature', 'pending'].includes(request.status)
        : request.status === 'pending';
    },
    locationAddress(request) {
      return [request.new_address, request.new_ward, request.new_province]
        .filter(Boolean)
        .join(', ');
    },
    formatStatusLabel(status) {
      return (
        {
          pending: 'Chờ duyệt',
          pending_owner_signature: 'Chờ chủ sân ký',
          need_supplement: 'Cần bổ sung',
          approved: 'Đã duyệt',
          approved_pending_appendix: 'Đã duyệt, chờ phụ lục',
          completed: 'Hoàn tất',
          rejected: 'Từ chối',
          cancelled: 'Đã hủy',
        }[status] || status
      );
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('vi-VN', {
          hour: '2-digit',
          minute: '2-digit',
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
        });
      } catch (e) {
        return dateStr;
      }
    },
    imgUrl(path) {
      if (!path) return '';
      if (path.startsWith('http://') || path.startsWith('https://')) return path;
      if (path.startsWith('/storage/')) return path;
      return '/storage/' + path;
    },
  },
};
</script>

<style scoped>
.cluster-profile-surface {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.profile-section-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Tab Section Header */
.tab-section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.tab-section-header h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: var(--admin-text, #101c15);
}

.section-subtitle {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--admin-muted, #64748b);
}

/* Filter Bar */
.table-filter-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 0;
}

.filter-tabs {
  display: flex;
  align-items: center;
  gap: 4px;
}

.tab-btn {
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--admin-muted, #64748b);
  font-size: 13px;
  font-weight: 400;
  cursor: pointer;
  transition: all 0.15s ease;
}

.tab-btn:hover {
  color: var(--admin-text, #101c15);
  background: var(--admin-hover, #edf7ed);
}

.tab-btn.active {
  background: var(--admin-bg-soft, #f7fbf5);
  border-color: var(--admin-border-soft, #e3ece4);
  color: var(--admin-primary, #22a653);
  font-weight: 500;
}

/* State Cards */
.table-state-card {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 8px;
  padding: 36px 20px;
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px dashed var(--admin-border, #cfded1);
  border-radius: 8px;
  color: var(--admin-muted, #2f3d34);
  font-size: 13.5px;
  font-weight: 400;
  text-align: center;
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

/* Audit History List (Timeline đồng bộ 1:1 với Hồ sơ đối tác) */
.audit-history-list {
  display: flex;
  flex-direction: column;
  padding-left: 4px;
}

.history-audit-item {
  display: flex;
  gap: 16px;
  position: relative;
}

.audit-axis-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 16px;
  flex-shrink: 0;
}

.audit-status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--admin-muted, #94a3b8);
  margin-top: 5px;
  z-index: 2;
  flex-shrink: 0;
}

.audit-status-dot.dot--approved { background: #16a34a; }
.audit-status-dot.dot--pending { background: #d97706; }
.audit-status-dot.dot--rejected { background: #dc2626; }
.audit-status-dot.dot--cancelled { background: #94a3b8; }

.audit-status-line {
  flex: 1;
  width: 2px;
  background: var(--admin-border-soft, #e2e8f0);
  margin-top: 4px;
  margin-bottom: -4px;
}

.audit-item-body {
  flex: 1;
  padding-bottom: 24px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.audit-item-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.audit-title-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.audit-code {
  font-size: 13.5px;
  font-weight: 600;
  font-family: monospace;
  color: var(--admin-text, #101c15);
}

.req-status-pill {
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11.5px;
  font-weight: 500;
}

.req-status-pill.pill-pending { background: #fef9c3; color: #854d0e; }
.req-status-pill.pill-pending_owner_signature,
.req-status-pill.pill-need_supplement { background: #fef9c3; color: #854d0e; }
.req-status-pill.pill-approved { background: #dcfce7; color: #166534; }
.req-status-pill.pill-approved_pending_appendix,
.req-status-pill.pill-completed { background: #dcfce7; color: #166534; }
.req-status-pill.pill-rejected { background: #fee2e2; color: #991b1b; }
.req-status-pill.pill-cancelled { background: #f1f5f9; color: #64748b; }

.audit-timestamp {
  font-size: 12px;
  color: var(--admin-muted, #64748b);
}

.audit-note-text {
  margin: 0;
  font-size: 13px;
  color: var(--admin-muted, #64748b);
}

.note-label {
  font-weight: 500;
  color: var(--admin-muted, #64748b);
}

/* Meta Info Grid (Lưới thông tin 2 cột phẳng đồng bộ) */
.meta-info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px 24px;
  background: var(--admin-bg-soft, #f7fbf5);
  padding: 12px 14px;
  border-radius: 8px;
  margin-top: 4px;
}

.meta-info-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.meta-info-item.full-width {
  grid-column: 1 / -1;
}

.meta-info-label {
  font-size: 12px;
  color: var(--admin-muted, #64748b);
  font-weight: 400;
}

.meta-info-value {
  font-size: 13px;
  color: var(--admin-text, #101c15);
  font-weight: 400;
}

.meta-info-value.highlight {
  color: var(--admin-primary, #22a653);
  font-weight: 500;
}

.desc-text {
  line-height: 1.4;
}

.req-img-thumbs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.req-img-thumb {
  width: 64px;
  height: 64px;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid var(--admin-border-soft, #e3ece4);
}

.req-img-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.req-admin-feedback {
  background: #f8fafc;
  border: 1px dashed var(--admin-border, #cfded1);
  border-radius: 6px;
  padding: 8px 12px;
  font-size: 12px;
  margin-top: 2px;
}

.feedback-head {
  font-weight: 500;
  color: var(--admin-muted, #64748b);
  margin-bottom: 2px;
}

.feedback-text {
  margin: 0;
  color: var(--admin-text, #101c15);
}

.audit-actions {
  display: flex;
  margin-top: 2px;
}

.btn-cancel-req {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  border: 1px solid #fca5a5;
  background: #ffffff;
  color: #dc2626;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-cancel-req:hover {
  background: #fef2f2;
}

.btn-cancel-req:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Dark Theme overrides */
[data-theme="dark"] .meta-info-grid {
  background: #18181b !important;
}

[data-theme="dark"] .req-admin-feedback {
  background: #18181b !important;
  border-color: #3f3f46 !important;
}

[data-theme="dark"] .audit-code,
[data-theme="dark"] .meta-info-value,
[data-theme="dark"] .feedback-text {
  color: #f4f4f5 !important;
}

@media (max-width: 640px) {
  .meta-info-grid {
    grid-template-columns: 1fr;
  }
}
</style>
