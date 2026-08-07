<template>
  <div class="cluster-profile-surface standalone">
    <div v-if="message" class="alert success">{{ message }}</div>
    <div v-if="error" class="alert error">{{ error }}</div>

    <!-- Top Integrated Tabs Row matching Services & Refund Requests -->
    <div class="complaints-header-hero">
      <div class="hero-integrated-tabs">
        <AppTabs
          :tabs="complaintsTabsForAppTabs"
          :model-value="activeTab"
          @update:model-value="changeTab"
        />
      </div>
    </div>

    <div class="profile-section-card complaints-main-content">

      <!-- Services-style Table Section -->
      <div class="services-table-section">
        <!-- Loading Screen -->
        <div v-if="loading" class="table-state-card">
          <div class="spinner-sm"></div>
          <span>Đang tải danh sách khiếu nại...</span>
        </div>

        <!-- Empty Screen -->
        <div v-else-if="complaints.length === 0" class="table-state-card">
          <span>Không tìm thấy khiếu nại nào.</span>
        </div>

        <!-- Complaints Data Table -->
        <div v-else class="services-table-wrapper">
          <table class="services-data-table complaints-table">
            <thead>
              <tr>
                <th>Khách hàng</th>
                <th>Nội dung khiếu nại</th>
                <th>Cụm sân / Booking</th>
                <th>Trạng thái</th>
                <th>Ngày tạo</th>
                <th class="action-col">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="complaint in complaints" :key="complaint.id">
                <td class="cell-name">
                  <strong class="customer-name">{{ complaint.customer?.full_name || 'Khách hàng' }}</strong>
                  <small class="cell-sub">{{ complaint.customer?.phone || complaint.customer?.email || 'Không có liên hệ' }}</small>
                </td>
                <td class="cell-desc">
                  <div class="post-title">{{ truncate(complaint.content, 60) }}</div>
                  <small class="cell-sub">{{ getComplaintTypeLabel(complaint.complaint_type) }}</small>
                </td>
                <td>
                  <div v-if="complaint.venue_cluster" class="cluster-name">
                    <strong>{{ complaint.venue_cluster.name }}</strong>
                  </div>
                  <small v-if="complaint.booking" class="booking-code">
                    Booking: {{ complaint.booking.booking_code }}
                  </small>
                </td>
                <td class="cell-status">
                  <span class="status-pill" :class="getStatusClass(complaint.status)">
                    {{ getStatusLabel(complaint.status) }}
                  </span>
                </td>
                <td>
                  <small class="cell-sub">{{ formatDate(complaint.created_at) }}</small>
                </td>
                <td class="action-col">
                  <div class="table-actions">
                    <router-link
                      :to="{ name: 'owner-complaint-detail', params: { id: complaint.id } }"
                      class="action-btn view-btn"
                    >
                      Chi tiết
                    </router-link>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="pagination" v-if="totalPages > 1">
          <ActionIconButton
            icon="chevronLeft"
            label="Trang trước"
            :disabled="currentPage === 1"
            @click="loadComplaints(currentPage - 1)"
          />
          <span class="page-info">Trang {{ currentPage }} / {{ totalPages }}</span>
          <ActionIconButton
            icon="chevronRight"
            label="Trang sau"
            :disabled="currentPage === totalPages"
            @click="loadComplaints(currentPage + 1)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { api } from '../../services/api.js';
import AppIcon from '../../components/AppIcon.vue';
import AppTabs from '../../components/common/AppTabs.vue';
import ActionIconButton from '../../components/ActionIconButton.vue';

const loading = ref(true);
const complaints = ref([]);
const message = ref('');
const error = ref('');

const currentPage = ref(1);
const totalPages = ref(1);

const complaintsTabsForAppTabs = [
  { key: '', value: '', label: 'Tất cả' },
  { key: 'open', value: 'open', label: 'Chờ xử lý' },
  { key: 'processing', value: 'processing', label: 'Đang xử lý' },
  { key: 'resolved', value: 'resolved', label: 'Đã giải quyết' },
  { key: 'rejected', value: 'rejected', label: 'Bị từ chối' },
];
const activeTab = ref('');

const summary = ref({
  total: 0,
  open: 0,
  processing: 0,
  resolved: 0,
});

onMounted(() => {
  loadComplaints(1);
});

const loadComplaints = async (page = 1) => {
  loading.value = true;
  error.value = '';
  try {
    const params = {
      page,
      per_page: 15,
      status: activeTab.value,
    };
    
    const validParams = Object.fromEntries(Object.entries(params).filter(([_, v]) => v !== '' && v !== null));
    const query = new URLSearchParams(validParams);
    
    const response = await api(`/api/owner/complaints?${query.toString()}`);
    complaints.value = response.data.data;
    currentPage.value = response.data.current_page;
    totalPages.value = response.data.last_page;
    summary.value = response.summary;
  } catch (err) {
    console.error(err);
    error.value = 'Lỗi tải danh sách khiếu nại.';
  } finally {
    loading.value = false;
  }
};

const changeTab = (val) => {
  activeTab.value = String(val || '');
  loadComplaints(1);
};

const truncate = (text, length) => {
  if (!text) return '';
  return text.length > length ? text.substring(0, length) + '...' : text;
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Date(dateString).toLocaleDateString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getStatusLabel = (status) => {
  const map = {
    open: 'Chờ xử lý',
    processing: 'Đang xử lý',
    resolved: 'Đã giải quyết',
    rejected: 'Bị từ chối',
    closed: 'Đã đóng',
  };
  return map[status] || status;
};

const getStatusClass = (status) => {
  const map = {
    open: 'open',
    processing: 'processing',
    resolved: 'resolved',
    rejected: 'rejected',
    closed: 'closed',
  };
  return map[status] || 'closed';
};

const getComplaintTypeLabel = (type) => {
  const map = {
    venue: 'Về sân bãi',
    system: 'Về hệ thống',
  };
  return map[type] || type;
};
</script>

<style scoped>
.cluster-profile-surface.standalone {
  width: 100%;
  min-width: 0;
  background: transparent;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Single unified main surface */
.complaints-header-hero {
  background: var(--admin-surface, #ffffff);
  padding: 10px 10px 0 10px;
  display: flex;
  align-items: center;
}

.profile-section-card.complaints-main-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 10px;
  background: var(--admin-surface, #ffffff);
  border: none;
  border-radius: 0;
  box-shadow: none;
  margin-top: 0 !important;
}

.hero-integrated-tabs {
  flex: 1;
}

.alert {
  border-radius: 8px;
  padding: 14px 16px;
  font-weight: 500;
  font-size: 13px;
}

.alert.success {
  background: var(--admin-success-soft, rgba(16, 185, 129, 0.08));
  color: var(--admin-primary, #22a653);
}

.alert.error {
  background: var(--admin-danger-soft, rgba(239, 68, 68, 0.08));
  color: var(--admin-danger, #ef4444);
}

/* Services Table Section (matching ServicesTable.vue) */
.services-table-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* States */
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

/* Services Table Wrapper & Data Table */
.services-table-wrapper {
  overflow-x: auto;
  border: none;
  border-radius: 10px;
}

.services-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  text-align: left;
}

.services-data-table th {
  background: var(--admin-bg-soft, #f7fbf5);
  color: var(--admin-text, #101c15);
  font-weight: 400;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 12px 14px;
  border-bottom: none;
}

.services-data-table td {
  padding: 12px 14px;
  border-bottom: none;
  color: var(--admin-text, #101c15);
  font-weight: 400;
  vertical-align: middle;
}

.services-data-table tbody tr {
  transition: background-color 0.12s ease;
}

.services-data-table tbody tr:hover {
  background: var(--admin-hover, #edf7ed);
}

.customer-name {
  font-weight: 400;
  color: var(--admin-text, #101c15);
}

.cell-sub {
  display: block;
  margin-top: 3px;
  color: var(--admin-muted, #64748b);
  font-size: 12px;
}

.post-title {
  font-size: 13px;
  font-weight: 400;
  color: var(--admin-text, #101c15);
  line-height: 1.4;
}

.cluster-name strong {
  font-weight: 400;
}

.booking-code {
  display: inline-block;
  margin-top: 3px;
  color: var(--admin-primary, #22a653);
}

.status-pill {
  display: inline-flex;
  border-radius: 999px;
  padding: 3px 9px;
  font-size: 11px;
  font-weight: 400;
  white-space: nowrap;
}

.status-pill.open {
  background: var(--admin-warning-soft, rgba(245, 158, 11, 0.1));
  color: var(--admin-warning, #d97706);
}

.status-pill.processing {
  background: rgba(59, 130, 246, 0.1);
  color: #2563eb;
}

.status-pill.resolved {
  background: var(--admin-success-soft, rgba(16, 185, 129, 0.1));
  color: var(--admin-primary, #22a653);
}

.status-pill.rejected {
  background: var(--admin-danger-soft, rgba(239, 68, 68, 0.1));
  color: var(--admin-danger, #ef4444);
}

.status-pill.closed {
  background: var(--admin-surface-muted, #f1f5f9);
  color: var(--admin-muted, #64748b);
}

.action-col {
  width: 1%;
  min-width: 90px;
  text-align: right;
}

.table-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 30px;
  padding: 0 12px;
  border-radius: 6px;
  border: 1px solid var(--admin-border, #cfded1);
  background: var(--admin-bg-soft, #f7fbf5);
  color: var(--admin-text, #101c15);
  font-size: 12px;
  font-weight: 400;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.12s ease;
}

.action-btn:hover {
  background: var(--admin-hover, #edf7ed);
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding-top: 12px;
}

.page-info {
  font-size: 13px;
  color: var(--admin-muted, #64748b);
}
</style>
