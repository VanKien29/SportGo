<template>
  <div class="partner-page">
    <header class="page-header">
      <div>
        <h2>Quản lý hồ sơ đối tác</h2>
        <p>Theo dõi hồ sơ, hợp đồng, chữ ký điện tử và chấm dứt hợp tác của chủ sân.</p>
      </div>
      <button class="icon-btn" type="button" title="Làm mới" @click="refresh">
        <AppIcon name="refresh" size="16" />
      </button>
    </header>

    <div class="tabs">
      <button
        v-for="tab in listTabs"
        :key="tab.value"
        class="tab-btn"
        :class="{ active: filters.tab === tab.value }"
        type="button"
        @click="selectListTab(tab.value)"
      >
        {{ tab.label }}
      </button>
    </div>

    <div class="toolbar card">
      <label class="field">
        <span>Tìm kiếm</span>
        <input v-model.trim="filters.search" type="search" placeholder="Tên sân, chủ sân, email, MST" @input="onFilterChange" />
      </label>
      <label class="field">
        <span>Trạng thái</span>
        <select v-model="filters.status" @change="loadApplications(1)">
          <option value="">Tất cả</option>
          <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </label>
    </div>

    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải hồ sơ...</p>
    </div>

    <div v-else-if="applications.length === 0" class="state-box card">
      <p>Không có hồ sơ phù hợp.</p>
    </div>

    <div v-else class="table-card card">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Hồ sơ</th>
              <th>Người nộp</th>
              <th>Sân</th>
              <th>Ngày gửi</th>
              <th class="center">Trạng thái</th>
              <th class="right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="application in applications" :key="application.id">
              <td>
                <div class="strong">{{ application.venue_name }}</div>
                <div class="muted">{{ application.business_name }}</div>
              </td>
              <td>
                <div class="strong">{{ application.user?.full_name || application.user?.username || '-' }}</div>
                <div class="muted">{{ application.user?.email || application.user?.phone || '-' }}</div>
              </td>
              <td>{{ application.courts_count || 0 }}</td>
              <td>{{ formatDate(application.submitted_at) }}</td>
              <td class="center">
                <span class="status" :class="`status-${application.status}`">{{ statusLabel(application.status) }}</span>
              </td>
              <td class="right">
                <div class="actions">
                  <button class="icon-btn" type="button" title="Chi tiết" @click="openDetail(application)">
                    <AppIcon name="eye" size="16" />
                  </button>
                  <button v-if="isReviewable(application.status)" class="icon-btn approve" type="button" title="Mở hồ sơ để duyệt" @click="openDetail(application, 'approve')">
                    <AppIcon name="check" size="16" />
                  </button>
                  <button v-if="isReviewable(application.status)" class="icon-btn danger" type="button" title="Mở hồ sơ để từ chối" @click="openDetail(application, 'reject')">
                    <AppIcon name="x" size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="pagination.last_page > 1" class="pagination">
        <button class="icon-btn" type="button" :disabled="pagination.current_page <= 1" @click="loadApplications(pagination.current_page - 1)">
          <AppIcon name="chevronLeft" size="16" />
        </button>
        <span>{{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button class="icon-btn" type="button" :disabled="pagination.current_page >= pagination.last_page" @click="loadApplications(pagination.current_page + 1)">
          <AppIcon name="chevronRight" size="16" />
        </button>
      </div>
    </div>

  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminPartnerApplicationService } from '../../services/adminPartnerApplications.js';

export default {
  name: 'AdminPartnerApplications',
  components: { AppIcon },
  data() {
    return {
      applications: [],
      loading: true,
      error: '',
      message: '',
      filterTimer: null,
      filters: { tab: 'pending', search: '', status: '' },
      pagination: { current_page: 1, last_page: 1, total: 0 },
      listTabs: [
        { value: 'pending', label: 'Chờ xử lý' },
        { value: 'active', label: 'Hợp đồng & hoạt động' },
        { value: 'terminating', label: 'Đang chấm dứt' },
      ],
      statusOptions: [
        { value: 'submitted', label: 'Chờ duyệt' },
        { value: 'reviewing', label: 'Đang xem xét' },
        { value: 'contract_pending_owner_signature', label: 'Chờ chủ sân ký' },
        { value: 'contract_pending_sportgo_signature', label: 'Chờ SportGo ký' },
        { value: 'completed', label: 'Đang hoạt động' },
        { value: 'rejected', label: 'Từ chối' },
      ],
    };
  },
  mounted() {
    this.loadApplications();
  },
  methods: {
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
    selectListTab(tab) {
      this.filters.tab = tab;
      this.filters.status = '';
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
        params: { id: application.id },
        query: action ? { action } : {},
      });
    },
    isReviewable(status) {
      return ['pending', 'reviewing', 'submitted'].includes(status);
    },
    statusLabel(status) {
      return {
        pending: 'Chờ duyệt',
        submitted: 'Chờ duyệt',
        reviewing: 'Đang xem xét',
        need_supplement: 'Cần bổ sung',
        approved_pending_contract: 'Đã duyệt, chờ hợp đồng',
        contract_pending_owner_signature: 'Chờ chủ sân ký',
        contract_pending_sportgo_signature: 'Chờ SportGo ký',
        completed: 'Đang hoạt động',
        rejected: 'Từ chối',
        cancelled: 'Đã hủy',
      }[status] || status || '-';
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
.partner-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1400px;
  margin: 0 auto;
}

.card {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: 8px;
}

.tabs,
.actions,
.pagination {
  display: flex;
  align-items: center;
  gap: 8px;
}

.tab-btn {
  min-height: 36px;
  padding: 0 14px;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  background: var(--admin-surface);
  color: var(--admin-muted);
  font-weight: 800;
  cursor: pointer;
}

.tab-btn.active {
  background: #0f172a;
  border-color: #0f172a;
  color: #fff;
}

.toolbar {
  display: grid;
  grid-template-columns: minmax(260px, 1fr) minmax(180px, 260px);
  gap: 12px;
  padding: 14px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 800;
}

.field.full {
  grid-column: 1 / -1;
}

.field input,
.field select,
.field textarea {
  width: 100%;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 0 12px;
  color: var(--admin-text);
  background: var(--admin-surface);
}

.field input,
.field select {
  height: 40px;
}

.field textarea {
  min-height: 110px;
  padding-top: 10px;
  resize: vertical;
}

.notice {
  padding: 12px 14px;
  border-radius: 8px;
  font-weight: 800;
}

.notice.success {
  color: #166534;
  background: #dcfce7;
}

.notice.error {
  color: #991b1b;
  background: #fee2e2;
}

.state-box {
  min-height: 220px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: var(--admin-muted);
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #0f172a;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.table-card {
  overflow: hidden;
}

.table-scroll {
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 980px;
  border-collapse: collapse;
}

th,
td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--admin-border);
  text-align: left;
  vertical-align: middle;
}

th {
  background: var(--admin-surface-muted);
  color: var(--admin-muted);
  font-size: 12px;
  text-transform: uppercase;
}

.center { text-align: center; }
.right { text-align: right; }
.strong { font-weight: 900; color: var(--admin-text); }
.muted { color: var(--admin-muted); font-size: 13px; }

.status {
  display: inline-flex;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
  background: var(--admin-border);
  color: var(--admin-text);
}

.status-pending,
.status-submitted,
.status-reviewing,
.status-contract_pending_owner_signature,
.status-contract_pending_sportgo_signature {
  background: #fef3c7;
  color: #92400e;
}

.status-completed {
  background: #dcfce7;
  color: #166534;
}

.status-rejected,
.status-cancelled {
  background: #fee2e2;
  color: #991b1b;
}

.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: 8px;
  border: 1px solid transparent;
  font-weight: 900;
  cursor: pointer;
}

.icon-btn {
  background: var(--admin-surface);
  border-color: var(--sg-border);
  color: var(--admin-text);
}

.icon-btn {
  width: 34px;
  height: 34px;
}

.icon-btn.approve { color: #15803d; }
.icon-btn.danger { color: #dc2626; }

.pagination {
  justify-content: flex-end;
  padding: 12px 16px;
}

@media (max-width: 900px) {
  .toolbar {
    grid-template-columns: 1fr;
  }

  .field.full {
    grid-column: auto;
  }
}
</style>
