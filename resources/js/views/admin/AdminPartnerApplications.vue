<template>
  <div class="cluster-profile-surface standalone">
    <!-- Top Integrated Tabs Row -->
    <div class="partner-header-hero">
      <div class="hero-integrated-tabs">
        <AppTabs
          :tabs="listTabsForAppTabs"
          :model-value="filters.tab"
          @update:model-value="selectListTab"
        />
      </div>
    </div>

    <!-- Main Content Surface -->
    <div class="profile-section-card applications-main-content">
      <div v-if="message" class="alert success">{{ message }}</div>
      <div v-if="error" class="alert error">{{ error }}</div>

      <!-- Summary Statistics Grid -->
      <div class="summary-grid">
        <article class="summary-item" :class="{ highlight: summaryCards.review > 0 }">
          <span class="summary-label">Cần duyệt</span>
          <strong class="summary-value">{{ summaryCards.review }}</strong>
          <small class="summary-sub">Hồ sơ chờ admin xử lý</small>
        </article>
        <article class="summary-item">
          <span class="summary-label">Chờ ký hợp đồng</span>
          <strong class="summary-value">{{ summaryCards.signature }}</strong>
          <small class="summary-sub">Chờ chủ sân hoặc SportGo ký</small>
        </article>
        <article class="summary-item">
          <span class="summary-label">Đang chấm dứt</span>
          <strong class="summary-value danger-text">{{ summaryCards.terminating }}</strong>
          <small class="summary-sub">Yêu cầu thanh lý hợp đồng</small>
        </article>
        <article class="summary-item">
          <span class="summary-label">Tổng hồ sơ</span>
          <strong class="summary-value">{{ summaryCards.total }}</strong>
          <small class="summary-sub">Theo bộ lọc hiện tại</small>
        </article>
      </div>

      <!-- Toolbar Search & Filter -->
      <div class="table-toolbar">
        <div class="search-box">
          <input
            v-model.trim="filters.search"
            type="search"
            placeholder="Tìm kiếm mã đối tác, họ tên, điện thoại, email, cụm sân..."
            @input="onFilterChange"
          />
        </div>
        <div class="filter-box">
          <select v-model="statusFilter" class="custom-select" @change="applyStatusFilter">
            <option v-for="option in statusFilterOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>

      <!-- Services-style Table Section -->
      <div class="services-table-section">
        <div v-if="loading" class="table-state-card">
          <div class="spinner-sm"></div>
          <span>Đang tải danh sách hồ sơ đối tác...</span>
        </div>

        <div v-else-if="applications.length === 0" class="table-state-card">
          <span>Không tìm thấy hồ sơ đối tác nào.</span>
        </div>

        <div v-else class="services-table-wrapper">
          <table class="services-data-table partner-table">
            <thead>
              <tr>
                <th>Mã đối tác</th>
                <th>Đối tác</th>
                <th>Cụm sân</th>
                <th>Trạng thái</th>
                <th class="right">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="application in applications" :key="application.id">
                <td>
                  <div class="cell-primary-text">{{ application.partner_code }}</div>
                  <div class="cell-sub-text">{{ application.application_count || 1 }} hồ sơ</div>
                </td>
                <td>
                  <div class="cell-primary-text">{{ application.partner_name || '-' }}</div>
                  <div class="cell-sub-text">{{ application.partner_phone || '-' }} · {{ application.partner_email || '-' }}</div>
                </td>
                <td>
                  <div class="cell-primary-text">{{ application.managed_clusters_count || 0 }} sân</div>
                  <div class="cell-sub-text">{{ (application.venue_names || []).slice(0, 2).join(', ') || application.venue_name || '-' }}</div>
                </td>
                <td>
                  <div class="status-stack">
                    <span class="status" :class="`status-${application.partner_status || application.status}`">
                      {{ statusLabel(application.partner_status || application.status) }}
                    </span>
                    <small>Hợp đồng: {{ contractStatusLabel(application.contract_status) }}</small>
                  </div>
                </td>
                <td class="right">
                  <button class="open-record-btn" type="button" @click="openDetail(application)">
                    Mở hồ sơ <AppIcon name="arrowRight" size="14" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="pagination.last_page > 1" class="table-pagination">
            <button class="icon-btn" type="button" :disabled="pagination.current_page <= 1" @click="loadApplications(pagination.current_page - 1)">
              <AppIcon name="chevronLeft" size="16" />
            </button>
            <span>Trang {{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button class="icon-btn" type="button" :disabled="pagination.current_page >= pagination.last_page" @click="loadApplications(pagination.current_page + 1)">
              <AppIcon name="chevronRight" size="16" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import AppTabs from '../../components/common/AppTabs.vue';
import { adminPartnerApplicationService } from '../../services/adminPartnerApplications.js';

export default {
  name: 'AdminPartnerApplications',
  components: { AppIcon, AppTabs },
  data() {
    return {
      applications: [],
      loading: true,
      error: '',
      message: '',
      filterTimer: null,
      statusFilter: 'all',
      filters: { tab: 'all', search: '', status: '' },
      pagination: { current_page: 1, last_page: 1, total: 0 },
      statusFilterOptions: [
        { value: 'all', label: 'Tất cả trạng thái' },
        { value: 'tab:pending_review', label: 'Cần duyệt hồ sơ' },
        { value: 'status:reviewing', label: 'Đang xem xét' },
        { value: 'status:contract_pending_owner_signature', label: 'Chờ chủ sân ký hợp đồng' },
        { value: 'status:contract_pending_sportgo_signature', label: 'Chờ SportGo ký hợp đồng' },
        { value: 'tab:active', label: 'Đang hoạt động' },
        { value: 'tab:terminating', label: 'Đang chấm dứt' },
        { value: 'tab:terminated', label: 'Đã chấm dứt' },
        { value: 'status:rejected', label: 'Đã từ chối' },
      ],
    };
  },
  computed: {
    listTabsForAppTabs() {
      return [
        { key: 'all', label: 'Tất cả' },
        { key: 'pending_review', label: 'Chờ duyệt' },
        { key: 'pending_signature', label: 'Chờ ký hợp đồng' },
        { key: 'active', label: 'Đang hoạt động' },
        { key: 'terminating', label: 'Đang chấm dứt' },
        { key: 'terminated', label: 'Đã chấm dứt' },
        { key: 'rejected', label: 'Từ chối' },
      ];
    },
    summaryCards() {
      const review = this.listTabCount('pending_review');
      const signature = this.listTabCount('pending_signature');
      const terminating = this.listTabCount('terminating');

      return {
        total: this.pagination.total || this.applications.length,
        review: typeof review === 'number' ? review : 0,
        signature: typeof signature === 'number' ? signature : 0,
        terminating: typeof terminating === 'number' ? terminating : 0,
      };
    },
  },
  mounted() {
    this.loadApplications();
  },
  methods: {
    selectListTab(tabKey) {
      this.filters.tab = tabKey;
      this.filters.status = '';
      this.statusFilter = tabKey === 'all' ? 'all' : `tab:${tabKey}`;
      this.loadApplications(1);
    },
    listTabCount(tab) {
      const currentTab = this.filters.status ? null : (this.filters.tab || 'all');
      if (tab === currentTab) {
        return this.pagination.total || this.applications.length;
      }

      if (currentTab !== 'all' || this.pagination.last_page > 1) {
        return '—';
      }

      return this.applications.filter((application) => this.applicationMatchesTab(application, tab)).length;
    },
    applicationMatchesTab(application, tab) {
      const status = application.partner_status || application.status;
      const matches = {
        all: true,
        pending_review: ['pending', 'reviewing', 'submitted', 'need_supplement', 'pending_review'].includes(status),
        pending_signature: ['contract_pending_owner_signature', 'contract_pending_sportgo_signature', 'pending_signature'].includes(status),
        active: ['active', 'completed'].includes(status),
        terminating: status === 'terminating',
        terminated: status === 'terminated',
        rejected: ['rejected', 'cancelled'].includes(status),
      };

      return matches[tab] ?? false;
    },
    async loadApplications(page = 1) {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminPartnerApplicationService.list({ ...this.filters, page });
        const paginator = response.data || {};
        this.applications = paginator.data || [];
        this.pagination = {
          current_page: paginator.current_page || 1,
          last_page: paginator.last_page || 1,
          total: paginator.total || this.applications.length,
        };
      } catch (err) {
        this.error = err.message || 'Không tải được hồ sơ đối tác.';
      } finally {
        this.loading = false;
      }
    },
    applyStatusFilter() {
      const [type, value] = this.statusFilter.split(':');
      this.filters.tab = type === 'tab' ? value : 'all';
      this.filters.status = type === 'status' ? value : '';
      this.loadApplications(1);
    },
    onFilterChange() {
      clearTimeout(this.filterTimer);
      this.filterTimer = setTimeout(() => this.loadApplications(1), 300);
    },
    refresh() {
      this.loadApplications(this.pagination.current_page);
    },
    async openDetail(application, action = '') {
      this.clearAlerts();
      this.$router.push({
        name: 'admin-partner-application-detail',
        params: { id: application.latest_application_id || application.id },
        query: action ? { action } : {},
      });
    },
    statusLabel(status) {
      return {
        pending_review: 'Chờ duyệt hồ sơ',
        pending_signature: 'Chờ ký hợp đồng',
        completed: 'Đang hoạt động',
        terminating: 'Đang yêu cầu chấm dứt',
        terminated: 'Đã chấm dứt',
        rejected: 'Từ chối',
        cancelled: 'Đã hủy',
      }[status] || status || '-';
    },
    contractStatusLabel(status) {
      return {
        pending_sportgo_signature: 'Chờ SportGo ký',
        pending_owner_signature: 'Chờ chủ sân ký',
        signed_active: 'Đang hiệu lực',
        terminated: 'Đã chấm dứt',
        cancelled: 'Đã hủy',
      }[status] || 'Chưa có';
    },
    formatDate(value) {
      if (!value) return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleString('vi-VN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
    },
    clearAlerts() {
      this.error = '';
      this.message = '';
    },
  },
};
</script>

<style scoped>
.partner-header-hero {
  background: var(--admin-surface, #ffffff);
  padding: 10px 10px 0 10px;
  display: flex;
  align-items: center;
}

.hero-integrated-tabs {
  flex: 1;
}

.profile-section-card.applications-main-content {
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

/* Summary Statistics Grid */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}

.summary-item {
  background: #ffffff;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 8px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.summary-item.highlight {
  border-color: #f59e0b;
  background: #fffbe8;
}

.summary-label {
  font-size: 12px;
  color: var(--admin-muted, #64748b);
  font-weight: 500;
}

.summary-value {
  font-size: 24px;
  font-weight: 700;
  color: var(--admin-text, #0f172a);
  line-height: 1.2;
}

.summary-value.danger-text {
  color: #ef4444;
}

.summary-sub {
  font-size: 11px;
  color: var(--admin-muted, #94a3b8);
}

/* Toolbar Search & Filter */
.table-toolbar {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
}

.search-box {
  flex: 1;
  max-width: 480px;
}

.search-box input {
  width: 100%;
  height: 38px;
  padding: 0 12px;
  border: 1px solid var(--admin-border-soft, #cbd5e1);
  border-radius: 8px;
  font-size: 13px;
  outline: none;
  transition: border-color 0.15s;
}

.search-box input:focus {
  border-color: #10b981;
}

.filter-box {
  min-width: 200px;
}

.custom-select {
  width: 100%;
  height: 38px;
  padding: 0 32px 0 12px;
  border: 1px solid var(--admin-border-soft, #cbd5e1);
  border-radius: 8px;
  font-size: 13px;
  background: #ffffff;
  outline: none;
  cursor: pointer;
}

/* Alerts */
.alert {
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
}

.alert.success {
  background: #dcfce7;
  color: #15803d;
}

.alert.error {
  background: #fee2e2;
  color: #b91c1c;
}

/* Table styling */
.services-table-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.table-state-card {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 40px 20px;
  color: var(--admin-muted, #64748b);
  font-size: 14px;
}

.spinner-sm {
  width: 20px;
  height: 20px;
  border: 2px solid #e2e8f0;
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.services-table-wrapper {
  overflow-x: auto;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 8px;
}

.services-data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 13px;
}

.services-data-table th {
  background: #f8fafc;
  padding: 12px 16px;
  font-weight: 600;
  color: #475569;
  border-bottom: 1px solid #e2e8f0;
  text-transform: uppercase;
  font-size: 11px;
  letter-spacing: 0.03em;
}

.services-data-table td {
  padding: 12px 16px;
  border-bottom: 1px solid #e2e8f0;
  vertical-align: middle;
}

.services-data-table tr:last-child td {
  border-bottom: none;
}

.cell-primary-text {
  font-weight: 600;
  color: #0f172a;
}

.cell-sub-text {
  font-size: 12px;
  color: #64748b;
  margin-top: 2px;
}

.status-stack {
  display: flex;
  flex-direction: column;
  gap: 3px;
  align-items: flex-start;
}

.status-stack small {
  font-size: 11px;
  color: #64748b;
}

.status {
  display: inline-flex;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 500;
}

.status-pending_review,
.status-pending_signature {
  background: #fef3c7;
  color: #92400e;
}

.status-completed {
  background: #dcfce7;
  color: #166534;
}

.status-terminating {
  background: #ffedd5;
  color: #9a3412;
}

.status-terminated {
  background: #f1f5f9;
  color: #475569;
}

.status-rejected,
.status-cancelled {
  background: #fee2e2;
  color: #991b1b;
}

.open-record-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 32px;
  padding: 0 12px;
  border: 1px solid #10b981;
  border-radius: 6px;
  background: #ffffff;
  color: #059669;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
}

.open-record-btn:hover {
  background: #ecfdf5;
}

.table-pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 10px 16px;
  border-top: 1px solid #e2e8f0;
  font-size: 13px;
  color: #64748b;
}

.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
  cursor: pointer;
}

.icon-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.right {
  text-align: right;
}

@media (max-width: 768px) {
  .table-toolbar {
    flex-direction: column;
    align-items: stretch;
  }
  .search-box {
    max-width: none;
  }
}
</style>
