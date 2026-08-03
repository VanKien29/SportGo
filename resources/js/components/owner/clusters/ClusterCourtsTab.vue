<template>
  <div class="profile-section-card">
    <!-- Header Section (Đồng bộ 1:1 với hệ thống) -->
    <div class="tab-section-header">
      <div>
        <h2>Danh sách sân con</h2>
        <p class="section-subtitle">Quản lý danh sách, loại sân và trạng thái kinh doanh của các sân con tại cụm sân.</p>
      </div>
      <div class="header-actions">
        <button
          type="button"
          class="btn btn-outline"
          @click="$emit('open-spatial-editor')"
        >
          <AppIcon name="maximize" size="14" />
          <span>Sơ đồ mặt bằng</span>
        </button>
        <button
          type="button"
          class="btn btn-primary"
          style="background-color: #22a653 !important; background: #22a653 !important; color: #ffffff !important; border: 1px solid #22a653 !important; transform: none !important; box-shadow: none !important;"
          :disabled="isClusterLocked"
          @click="$emit('open-scale-request')"
        >
          <AppIcon name="plus" size="14" />
          <span>Yêu cầu thêm sân</span>
        </button>
      </div>
    </div>

    <!-- Filter Bar (Đã loại bỏ padding và ô tìm kiếm) -->
    <div v-if="courts.length > 0" class="table-filter-bar">
      <div class="filter-tabs">
        <button
          type="button"
          class="tab-btn"
          :class="{ active: filterStatus === '' }"
          @click="filterStatus = ''"
        >
          Tất cả ({{ courts.length }})
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: filterStatus === 'active' }"
          @click="filterStatus = 'active'"
        >
          Đang hoạt động ({{ countStatus('active') }})
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: filterStatus === 'inactive' }"
          @click="filterStatus = 'inactive'"
        >
          Tạm ngưng ({{ countStatus('inactive') }})
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: filterStatus === 'maintenance' }"
          @click="filterStatus = 'maintenance'"
        >
          Bảo trì ({{ countStatus('maintenance') }})
        </button>
      </div>
    </div>

    <!-- Loading State Card -->
    <div v-if="loading" class="table-state-card">
      <div class="spinner-sm"></div>
      <span>Đang tải danh sách sân con...</span>
    </div>

    <!-- Error State Card -->
    <div v-else-if="error" class="table-state-card text-danger">
      <span>{{ error }}</span>
    </div>

    <!-- Empty State Card (Khi chưa có sân con nào) -->
    <div v-else-if="!courts.length" class="table-state-card">
      <span>Cụm sân này chưa có sân con nào được tạo.</span>
      <button
        type="button"
        class="btn btn-outline"
        style="margin-top: 8px;"
        :disabled="isClusterLocked"
        @click="$emit('open-scale-request')"
      >
        + Gửi yêu cầu thêm sân con
      </button>
    </div>

    <!-- Empty Search State Card (Khi lọc không ra kết quả) -->
    <div v-else-if="filteredCourts.length === 0" class="table-state-card">
      <span>Không tìm thấy sân con nào phù hợp.</span>
      <button
        type="button"
        class="btn btn-outline"
        style="margin-top: 6px;"
        @click="searchQuery = ''; filterStatus = ''"
      >
        Xóa bộ lọc
      </button>
    </div>

    <!-- Data Table Container (Đồng bộ 1:1 với ServicesTable / SaaSTable) -->
    <div v-else class="courts-table-wrapper">
      <table class="courts-data-table">
        <thead>
          <tr>
            <th style="width: 50px;">#</th>
            <th>Tên sân con</th>
            <th>Loại sân</th>
            <th>Giá bán/thuê</th>
            <th>Trạng thái</th>
            <th class="action-col">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(item, idx) in filteredCourts"
            :key="item.id || idx"
            :class="{ 'row-inactive': item.status !== 'active' }"
          >
            <td class="cell-index">
              <span class="index-num">#{{ String(idx + 1).padStart(2, '0') }}</span>
            </td>
            <td class="cell-name">
              <span class="court-name">{{ getCourtName(item) }}</span>
            </td>
            <td class="cell-type">
              <span>{{ getCourtType(item) }}</span>
            </td>
            <td class="cell-price">
              <span class="price-value">{{ formatPrice(item.default_price || item.price_per_hour) }}</span>
            </td>
            <td class="cell-status">
              <span class="status-text" :class="'status-' + (item.status || 'inactive')">
                {{ getStatusLabel(item.status) }}
              </span>
            </td>
            <td class="action-col">
              <div class="table-actions">
                <button
                  type="button"
                  class="action-btn edit-btn"
                  title="Chỉnh sửa thông tin"
                  @click="$emit('edit-court', item)"
                >
                  Sửa
                </button>
                <button
                  type="button"
                  class="action-btn toggle-btn"
                  :title="item.status === 'active' ? 'Tạm ngưng sân' : 'Kích hoạt sân'"
                  :disabled="isClusterLocked"
                  @click="$emit('toggle-court-status', item)"
                >
                  {{ item.status === 'active' ? 'Tắt' : 'Bật' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../AppIcon.vue';

export default {
  name: 'ClusterCourtsTab',
  components: { AppIcon },
  props: {
    courts: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
    isClusterLocked: { type: Boolean, default: false },
  },
  emits: ['edit-court', 'toggle-court-status', 'open-scale-request', 'open-spatial-editor'],
  data() {
    return {
      filterStatus: '',
      searchQuery: '',
    };
  },
  computed: {
    filteredCourts() {
      if (!Array.isArray(this.courts)) return [];
      let list = [...this.courts];

      if (this.filterStatus) {
        list = list.filter((c) => (c.status || 'inactive') === this.filterStatus);
      }

      if (this.searchQuery.trim()) {
        const q = this.searchQuery.toLowerCase().trim();
        list = list.filter((c) => {
          const name = this.getCourtName(c).toLowerCase();
          const type = this.getCourtType(c).toLowerCase();
          return name.includes(q) || type.includes(q);
        });
      }

      return list;
    },
  },
  methods: {
    countStatus(status) {
      if (!Array.isArray(this.courts)) return 0;
      return this.courts.filter((c) => (c.status || 'inactive') === status).length;
    },
    getCourtName(court) {
      if (!court) return '';
      return court.name || court.court_name || `Sân con #${court.id}`;
    },
    getCourtType(court) {
      if (!court) return 'Chưa phân loại';
      if (court.court_type && typeof court.court_type === 'object') {
        return court.court_type.name || court.court_type.label || 'Chưa phân loại';
      }
      return court.court_type || court.type_name || 'Chưa phân loại';
    },
    getStatusLabel(status) {
      return (
        {
          active: 'Đang hoạt động',
          inactive: 'Tạm ngưng',
          maintenance: 'Bảo trì',
        }[status] || 'Tạm ngưng'
      );
    },
    formatPrice(val) {
      if (!val && val !== 0) return 'Theo khung giờ';
      return new Intl.NumberFormat('vi-VN').format(val) + ' đ/giờ';
    },
  },
};
</script>

<style scoped>
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

.header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
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

.search-input:focus {
  outline: none;
  border-color: var(--admin-primary, #22a653);
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

/* Data Table */
.courts-table-wrapper {
  overflow-x: auto;
  border: none;
  border-radius: 0;
}

.courts-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  text-align: left;
}

.courts-data-table th {
  background: var(--admin-bg-soft, #f7fbf5);
  color: var(--admin-text, #101c15);
  font-weight: 500;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 12px 14px;
  border-bottom: none;
}

.courts-data-table td {
  padding: 12px 14px;
  border-bottom: none;
  color: var(--admin-text, #101c15);
  font-weight: 400;
  vertical-align: middle;
}

.courts-data-table tbody tr {
  transition: background-color 0.12s ease;
}

.courts-data-table tbody tr:hover {
  background: var(--admin-hover, #edf7ed);
}

.courts-data-table tbody tr.row-inactive {
  opacity: 0.6;
}

.index-num {
  font-size: 12px;
  font-family: monospace;
  color: var(--admin-muted, #64748b);
}

.court-name {
  font-weight: 500;
  color: var(--admin-text, #101c15);
}

.price-value {
  font-weight: 400;
  color: var(--admin-text, #101c15);
}

.status-text {
  font-weight: 400;
  font-size: 12.5px;
}

.status-text.status-active {
  color: #16a34a;
}

.status-text.status-inactive {
  color: var(--admin-muted, #64748b);
}

.status-text.status-maintenance {
  color: #d97706;
}

.action-col {
  text-align: center;
  width: 130px;
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
  border-color: var(--admin-border, #cfded1);
}

.action-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* Dark theme overrides */
[data-theme="dark"] .table-state-card {
  background: #18181b !important;
  border-color: #27272a !important;
  color: #a1a1aa !important;
}

[data-theme="dark"] .courts-data-table th {
  background: #18181b !important;
  color: #f4f4f5 !important;
}

[data-theme="dark"] .courts-data-table tbody tr:hover {
  background: #27272a !important;
}

[data-theme="dark"] .tab-btn.active {
  background: #27272a !important;
  border-color: #3f3f46 !important;
  color: #22a653 !important;
}

[data-theme="dark"] .action-btn {
  background: #18181b !important;
  border-color: #27272a !important;
  color: #f4f4f5 !important;
}

[data-theme="dark"] .action-btn:hover {
  background: #27272a !important;
}
</style>
