<template>
  <div class="complaints-page">
    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <div class="filter-toolbar card">
      <!-- Tabs -->
      <div class="tabs-header">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          class="tab-btn"
          :class="{ active: activeTab === tab.value }"
          type="button"
          @click="changeTab(tab.value)"
        >
          <AppIcon :name="tab.icon" size="16" />
          <span>{{ tab.label }}</span>
        </button>
      </div>

      <!-- Filter and Search -->
      <div class="filters-row">
        <label class="field compact search-field">
          <AppIcon name="search" size="16" />
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Tìm theo nội dung, người gửi, mã booking..."
            @input="onSearchInput"
          />
        </label>
      </div>
    </div>

    <!-- Loading Screen -->
    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải danh sách khiếu nại...</p>
    </div>

    <!-- Empty Screen -->
    <div v-else-if="complaints.length === 0" class="state-box card">
      <AppIcon name="fileText" size="36" />
      <p>Không tìm thấy khiếu nại nào.</p>
    </div>

    <!-- Complaints Table -->
    <div v-else class="table-container card">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Khách hàng</th>
              <th>Nội dung khiếu nại</th>
              <th>Cụm sân / Booking</th>
              <th>Trạng thái</th>
              <th>Ngày tạo</th>
              <th class="center" style="width: 120px;">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="complaint in complaints" :key="complaint.id" class="complaint-row">
              <td>
                <div class="author-cell">
                  <strong>{{ complaint.customer?.full_name || 'Khách hàng' }}</strong>
                  <span class="muted small">{{ complaint.customer?.phone || 'Không có SĐT' }}</span>
                  <span class="muted small">{{ complaint.customer?.email || '' }}</span>
                </div>
              </td>
              <td>
                <div class="info-cell">
                  <div class="post-title">{{ truncate(complaint.content, 60) }}</div>
                  <div class="complaint-type">
                    <span class="type-badge">{{ getComplaintTypeLabel(complaint.complaint_type) }}</span>
                  </div>
                </div>
              </td>
              <td>
                <div class="info-cell">
                  <div v-if="complaint.venue_cluster" class="post-court">
                    <AppIcon name="building" size="14" class="muted-icon" />
                    <span>{{ complaint.venue_cluster.name }}</span>
                  </div>
                  <div v-if="complaint.booking" class="booking-link-cell mt-1">
                    <span class="booking-code">Booking: {{ complaint.booking.booking_code }}</span>
                  </div>
                </div>
              </td>
              <td>
                <span class="status-badge" :class="getStatusClass(complaint.status)">
                  {{ getStatusLabel(complaint.status) }}
                </span>
              </td>
              <td>
                <span class="date-cell">{{ formatDate(complaint.created_at) }}</span>
              </td>
              <td class="center">
                <div class="actions-cell">
                  <router-link
                    :to="{ name: 'owner-complaint-detail', params: { id: complaint.id } }"
                    class="btn ghost btn-sm"
                  >
                    <span>Xem chi tiết</span>
                  </router-link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination" v-if="totalPages > 1">
        <button
          class="btn ghost icon-only"
          :disabled="currentPage === 1"
          @click="loadComplaints(currentPage - 1)"
        >
          <AppIcon name="chevronLeft" size="16" />
        </button>
        <span class="page-info">Trang {{ currentPage }} / {{ totalPages }}</span>
        <button
          class="btn ghost icon-only"
          :disabled="currentPage === totalPages"
          @click="loadComplaints(currentPage + 1)"
        >
          <AppIcon name="chevronRight" size="16" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { api } from '../../services/api.js';
import AppIcon from '../../components/AppIcon.vue';

const loading = ref(true);
const complaints = ref([]);
const message = ref('');
const error = ref('');

const currentPage = ref(1);
const totalPages = ref(1);

const tabs = [
  { label: 'Tất cả', value: '', icon: 'list' },
  { label: 'Chờ xử lý', value: 'open', icon: 'clock' },
  { label: 'Đang xử lý', value: 'processing', icon: 'loader' },
  { label: 'Đã giải quyết', value: 'resolved', icon: 'checkCircle' },
  { label: 'Bị từ chối', value: 'rejected', icon: 'xCircle' },
];
const activeTab = ref('');

const searchQuery = ref('');

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
      keyword: searchQuery.value,
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

const changeTab = (tab) => {
  activeTab.value = tab;
  loadComplaints(1);
};

let searchTimeout = null;
const onSearchInput = () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadComplaints(1);
  }, 500);
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
    open: 'status-warning',
    processing: 'status-info',
    resolved: 'status-success',
    rejected: 'status-danger',
    closed: 'status-muted',
  };
  return map[status] || 'status-muted';
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
.complaints-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.notice {
  padding: 12px 16px;
  border-radius: var(--admin-radius-md);
  font-size: 14px;
  font-weight: 500;
}
.notice.success {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}
.notice.error {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

.filter-toolbar {
  display: flex;
  flex-direction: column;
}

.tabs-header {
  display: flex;
  gap: 8px;
  padding: 12px 16px;
}

.filters-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  align-items: center;
  padding: 12px 16px;
  background: var(--admin-surface-muted);
  border-top: 1px solid var(--admin-border);
}

.field.compact {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 10px;
  font-size: 11px;
  font-weight: 700;
  color: var(--admin-faint);
  letter-spacing: 0.03em;
  text-transform: uppercase;
  white-space: nowrap;
}

.search-field {
  position: relative;
  width: 320px;
  max-width: 100%;
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 0 12px;
  height: 36px;
  gap: 8px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.search-field:focus-within {
  border-color: var(--admin-blue);
  box-shadow: 0 0 0 3px var(--admin-primary-ring);
}
.search-field input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  font-weight: 500;
  color: var(--admin-text);
  padding: 0;
  height: 100%;
  text-transform: none;
}

.state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  color: var(--admin-muted);
  gap: 16px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--admin-border);
  border-top-color: var(--admin-primary);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.table-container {
  overflow: hidden;
}

.table-scroll {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  min-width: 1000px;
}

th {
  background: var(--admin-surface-muted);
  padding: 12px 16px;
  font-size: 12px;
  font-weight: 600;
  color: var(--admin-faint);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  border-bottom: 1px solid var(--admin-border);
  white-space: nowrap;
}

td {
  padding: 16px;
  border-bottom: 1px solid var(--admin-border);
  vertical-align: top;
}

th.right, td.right {
  text-align: right;
}

th.center, td.center {
  text-align: center;
}

.complaint-row:hover {
  background: var(--admin-surface-muted);
}

.author-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.author-cell strong {
  color: var(--admin-text);
  font-size: 14px;
}
.muted {
  color: var(--admin-muted);
}
.small {
  font-size: 12px;
}

.info-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.post-title {
  font-size: 14px;
  font-weight: 500;
  color: var(--admin-text);
  line-height: 1.4;
}

.type-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  background: var(--admin-surface-hover);
  color: var(--admin-text);
}

.post-court {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--admin-text);
}
.muted-icon {
  color: var(--admin-muted);
}

.booking-code {
  font-size: 12px;
  font-weight: 600;
  color: var(--admin-primary);
  background: rgba(59, 130, 246, 0.1);
  padding: 2px 8px;
  border-radius: 4px;
}

.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}
.status-warning {
  background: rgba(245, 158, 11, 0.1);
  color: #d97706;
}
.status-info {
  background: rgba(59, 130, 246, 0.1);
  color: #2563eb;
}
.status-success {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}
.status-danger {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}
.status-muted {
  background: var(--admin-surface-muted);
  color: var(--admin-muted);
}

.date-cell {
  font-size: 13px;
  color: var(--admin-muted);
}

.actions-cell {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  gap: 16px;
  border-top: 1px solid var(--admin-border);
}
.page-info {
  font-size: 14px;
  color: var(--admin-muted);
  font-weight: 500;
}
.mt-1 {
  margin-top: 4px;
}

@media (max-width: 768px) {
  .tabs-header {
    flex-wrap: nowrap;
    overflow-x: auto;
    white-space: nowrap;
    padding-bottom: 8px; /* Room for scrollbar */
  }
  .tab-btn {
    flex-shrink: 0;
  }
  .search-field {
    width: 100%;
    max-width: none;
  }
}
</style>
