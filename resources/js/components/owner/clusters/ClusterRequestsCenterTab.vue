<template>
  <div class="cluster-requests-surface">
    <!-- Sub Category Switcher -->
    <div class="sub-category-bar">
      <button
        type="button"
        class="category-chip"
        :class="{ active: subTab === 'scale' }"
        @click="subTab = 'scale'"
      >
        <AppIcon name="layers" size="14" />
        <span>Quy mô sân con</span>
        <span v-if="scaleRequests.length > 0" class="chip-badge">{{ scaleRequests.length }}</span>
      </button>

      <button
        type="button"
        class="category-chip"
        :class="{ active: subTab === 'info' }"
        @click="subTab = 'info'"
      >
        <AppIcon name="fileText" size="14" />
        <span>Thông tin chung</span>
        <span v-if="infoRequests.length > 0" class="chip-badge">{{ infoRequests.length }}</span>
      </button>

      <button
        type="button"
        class="category-chip"
        :class="{ active: subTab === 'location' }"
        @click="subTab = 'location'"
      >
        <AppIcon name="mapPin" size="14" />
        <span>Vị trí bản đồ</span>
        <span v-if="locationRequests.length > 0" class="chip-badge">{{ locationRequests.length }}</span>
      </button>

      <button
        v-if="isModerationLocked || unlockRequests.length > 0"
        type="button"
        class="category-chip danger"
        :class="{ active: subTab === 'unlock' }"
        @click="subTab = 'unlock'"
      >
        <AppIcon name="lock" size="14" />
        <span>Yêu cầu mở khóa</span>
        <span v-if="unlockRequests.length > 0" class="chip-badge">{{ unlockRequests.length }}</span>
      </button>
    </div>

    <!-- Status Filters Bar & Action Header -->
    <div class="requests-main-content">
      <div class="approval-list-header">
        <div class="header-left">
          <h3>Lịch sử {{ currentSubTabLabel }}</h3>
          <div class="status-filter-pills">
            <button
              v-for="status in statusOptions"
              :key="status.value"
              type="button"
              class="filter-pill"
              :class="{ active: statusFilter === status.value }"
              @click="statusFilter = status.value"
            >
              {{ status.label }}
            </button>
          </div>
        </div>

        <button
          v-if="subTab === 'scale'"
          type="button"
          class="btn btn-primary btn-sm"
          :disabled="isClusterLocked"
          @click="$emit('open-scale-request-modal')"
        >
          <AppIcon name="plus" size="14" />
          <span>Tạo yêu cầu quy mô mới</span>
        </button>

        <button
          v-else-if="subTab === 'unlock' && isModerationLocked"
          type="button"
          class="btn btn-primary btn-sm"
          @click="$emit('open-unlock-modal')"
        >
          <AppIcon name="unlock" size="14" />
          <span>Tạo giải trình mở khóa</span>
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Đang tải danh sách yêu cầu...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredRequests.length === 0" class="empty-state">
        <AppIcon name="inbox" size="28" />
        <p>Không tìm thấy yêu cầu nào theo bộ lọc.</p>
      </div>

      <!-- Requests Cards Grid -->
      <div v-else class="requests-timeline-grid">
        <div v-for="req in filteredRequests" :key="req.id" class="request-item-card">
          <div class="request-card-header">
            <div class="request-meta">
              <span class="request-id">#{{ req.id }}</span>
              <span class="status-pill" :class="req.status">
                {{ formatStatusLabel(req.status) }}
              </span>
              <span class="request-date">{{ formatDate(req.created_at) }}</span>
            </div>
            <button
              v-if="req.status === 'pending'"
              type="button"
              class="btn btn-sm btn-outline-danger"
              @click="$emit('cancel-request', { type: subTab, id: req.id })"
            >
              Hủy yêu cầu
            </button>
          </div>

          <div class="request-card-body">
            <h4 class="request-title">{{ getRequestTitle(req) }}</h4>
            <p v-if="req.reason || req.note || req.description" class="request-desc">
              {{ req.reason || req.note || req.description }}
            </p>

            <div v-if="req.admin_note" class="admin-feedback-box">
              <strong>Phản hồi từ Ban quản trị:</strong>
              <p>{{ req.admin_note }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../AppIcon.vue';

export default {
  name: 'ClusterRequestsCenterTab',
  components: { AppIcon },
  props: {
    scaleRequests: { type: Array, default: () => [] },
    infoRequests: { type: Array, default: () => [] },
    locationRequests: { type: Array, default: () => [] },
    unlockRequests: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    isClusterLocked: { type: Boolean, default: false },
    isModerationLocked: { type: Boolean, default: false },
  },
  emits: ['open-scale-request-modal', 'open-unlock-modal', 'cancel-request'],
  data() {
    return {
      subTab: 'scale',
      statusFilter: '',
      statusOptions: [
        { value: '', label: 'Tất cả' },
        { value: 'pending', label: 'Chờ duyệt' },
        { value: 'approved', label: 'Đã duyệt' },
        { value: 'rejected', label: 'Từ chối' },
        { value: 'cancelled', label: 'Đã hủy' },
      ],
    };
  },
  computed: {
    currentSubTabLabel() {
      const labels = {
        scale: 'yêu cầu quy mô',
        info: 'yêu cầu thông tin',
        location: 'yêu cầu vị trí',
        unlock: 'yêu cầu mở khóa',
      };
      return labels[this.subTab] || 'yêu cầu';
    },
    activeList() {
      if (this.subTab === 'info') return this.infoRequests;
      if (this.subTab === 'location') return this.locationRequests;
      if (this.subTab === 'unlock') return this.unlockRequests;
      return this.scaleRequests;
    },
    filteredRequests() {
      if (!this.statusFilter) return this.activeList;
      return this.activeList.filter((r) => r.status === this.statusFilter);
    },
  },
  methods: {
    formatStatusLabel(status) {
      const map = {
        pending: 'Chờ duyệt',
        approved: 'Đã duyệt',
        rejected: 'Từ chối',
        cancelled: 'Đã hủy',
      };
      return map[status] || status;
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
    getRequestTitle(req) {
      if (req.title) return req.title;
      if (req.type) return `Yêu cầu điều chỉnh: ${req.type}`;
      if (req.address) return `Thay đổi địa chỉ: ${req.address}`;
      return 'Yêu cầu cập nhật hệ thống';
    },
  },
};
</script>

<style scoped>
.cluster-requests-surface {
  background: var(--admin-surface, #ffffff);
  border-radius: 12px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.sub-category-bar {
  display: flex;
  gap: 10px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.category-chip {
  height: 36px;
  padding: 0 16px;
  border-radius: 999px;
  border: 1px solid var(--admin-border-soft, rgba(255, 255, 255, 0.1));
  background: var(--admin-hover, rgba(255, 255, 255, 0.06));
  color: var(--admin-text, #f8fafc);
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.18s ease;
}

.category-chip.active {
  background: var(--admin-primary, #16a34a);
  color: #ffffff;
  border-color: #16a34a;
}

.chip-badge {
  padding: 1px 6px;
  border-radius: 999px;
  font-size: 11px;
  background: rgba(255, 255, 255, 0.2);
}

.requests-main-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.approval-list-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.header-left h3 {
  margin: 0 0 8px;
  font-size: 16px;
  font-weight: 600;
  color: var(--admin-text, #0f172a);
}

.status-filter-pills {
  display: flex;
  gap: 6px;
}

.filter-pill {
  padding: 4px 10px;
  border-radius: 6px;
  border: none;
  background: var(--admin-bg, #f1f5f9);
  color: var(--admin-muted, #64748b);
  font-size: 12px;
  cursor: pointer;
}

.filter-pill.active {
  background: var(--admin-hover, #e2e8f0);
  color: var(--admin-text, #0f172a);
  font-weight: 500;
}

.requests-timeline-grid {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.request-item-card {
  background: var(--admin-bg, #f8fafc);
  border-radius: 10px;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.request-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.request-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
  color: var(--admin-muted, #64748b);
}

.status-pill {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11px;
}

.status-pill.pending { background: #fef9c3; color: #854d0e; }
.status-pill.approved { background: #dcfce7; color: #166534; }
.status-pill.rejected, .status-pill.cancelled { background: #fee2e2; color: #991b1b; }

.request-title {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--admin-text, #0f172a);
}

.request-desc {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--admin-muted, #64748b);
  line-height: 1.4;
}

.admin-feedback-box {
  margin-top: 8px;
  padding: 10px 14px;
  border-radius: 6px;
  background: var(--admin-surface, #ffffff);
  font-size: 12px;
  color: var(--admin-text, #0f172a);
}
</style>
