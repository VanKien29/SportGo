<template>
  <div class="partner-page">
    <header class="partner-list-header">
      <div>
        <h1>Hồ sơ đối tác</h1>
        <p>Duyệt hồ sơ, ký văn bản và theo dõi chấm dứt hợp tác tại một nơi.</p>
      </div>
    </header>

    <section class="partner-kpis">
      <article v-for="card in summaryCards" :key="card.key" class="partner-kpi-card">
        <span>{{ card.label }}</span>
        <strong>{{ card.value }}</strong>
        <small>{{ card.hint }}</small>
      </article>
    </section>

    <div class="tabs">
      <button
        v-for="tab in listTabsUi"
        :key="tab.value"
        class="tab-btn"
        :class="{ active: filters.tab === tab.value }"
        type="button"
        @click="selectListTab(tab.value)"
      >
        <span>{{ tab.label }}</span>
        <strong>{{ listTabCount(tab.value) }}</strong>
      </button>
    </div>

    <div class="toolbar card">
      <label class="field">
        <span>Tìm kiếm</span>
        <input v-model.trim="filters.search" type="search" placeholder="Mã đối tác, họ tên, điện thoại, email, cụm sân" @input="onFilterChange" />
      </label>
      <label class="field">
        <span>Trạng thái</span>
        <select v-model="statusFilter" @change="applyStatusFilter">
          <option v-for="option in statusFilterOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
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
      <div class="partner-mobile-list">
        <article v-for="application in applications" :key="`mobile-${application.id}`" class="partner-mobile-row">
          <div class="partner-mobile-heading">
            <div>
              <small>{{ application.partner_code }}</small>
              <strong>{{ application.partner_name || '-' }}</strong>
            </div>
            <span class="status" :class="`status-${application.partner_status || application.status}`">{{ statusLabel(application.partner_status || application.status) }}</span>
          </div>
          <p>{{ application.partner_phone || '-' }} · {{ application.partner_email || '-' }}</p>
          <div class="partner-mobile-facts">
            <span><small>Cụm sân</small><strong>{{ (application.venue_names || []).slice(0, 1).join(', ') || application.venue_name || 'Chưa có' }}</strong></span>
            <span><small>Hợp đồng</small><strong>{{ contractStatusLabel(application.contract_status) }}</strong></span>
          </div>
          <button class="open-record-btn" type="button" @click="openDetail(application)">
            Mở hồ sơ <AppIcon name="arrowRight" size="16" />
          </button>
        </article>
      </div>
      <div class="table-scroll partner-desktop-table">
        <table>
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
                <div class="strong">{{ application.partner_code }}</div>
                <div class="muted">{{ application.application_count || 1 }} hồ sơ</div>
              </td>
              <td>
                <div class="strong">{{ application.partner_name || '-' }}</div>
                <div class="muted">{{ application.partner_phone || '-' }} · {{ application.partner_email || '-' }}</div>
              </td>
              <td>
                <div class="strong">{{ application.managed_clusters_count || 0 }}</div>
                <div class="muted">{{ (application.venue_names || []).slice(0, 2).join(', ') || application.venue_name || '-' }}</div>
              </td>
              <td>
                <div class="status-stack">
                  <span class="status" :class="`status-${application.partner_status || application.status}`">{{ statusLabel(application.partner_status || application.status) }}</span>
                  <small>Hợp đồng: {{ contractStatusLabel(application.contract_status) }}</small>
                </div>
              </td>
              <td class="right">
                <button class="open-record-btn" type="button" @click="openDetail(application)">
                  <AppIcon name="arrowRight" size="16" /> Mở hồ sơ
                </button>
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
    listTabsUi() {
      return [
        { value: 'all', label: 'Tất cả' },
        { value: 'pending_review', label: 'Chờ duyệt' },
        { value: 'pending_signature', label: 'Chờ ký hợp đồng' },
        { value: 'active', label: 'Đang hoạt động' },
        { value: 'terminating', label: 'Đang chấm dứt' },
        { value: 'terminated', label: 'Đã chấm dứt' },
        { value: 'rejected', label: 'Từ chối' },
      ];
    },
    summaryCards() {
      const review = this.listTabCount('pending_review');
      const signature = this.listTabCount('pending_signature');
      const terminating = this.listTabCount('terminating');

      return [
        { key: 'total', label: 'Hồ sơ đang hiển thị', value: this.pagination.total || this.applications.length, hint: 'Theo bộ lọc hiện tại' },
        { key: 'review', label: 'Cần duyệt', value: review, hint: 'Hồ sơ chờ admin xử lý' },
        { key: 'signature', label: 'Chờ ký', value: signature, hint: 'Hợp đồng hoặc văn bản đang chờ ký' },
        { key: 'terminating', label: 'Chấm dứt', value: terminating, hint: 'Hồ sơ đang thanh lý/chấm dứt' },
      ];
    },
  },
  mounted() {
    this.loadApplications();
  },
  methods: {
    selectListTab(tab) {
      this.filters.tab = tab;
      this.filters.status = '';
      this.statusFilter = tab === 'all' ? 'all' : `tab:${tab}`;
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
.partner-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1400px;
  margin: 0 auto;
}

.partner-list-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 8px 0 2px;
}

.partner-list-header h1 {
  margin: 0 0 4px;
  color: var(--admin-text);
  font-size: 28px;
  line-height: 1.2;
}

.partner-list-header p {
  margin: 0;
  color: var(--admin-muted);
}

.card {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: 8px;
}

.partner-kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.partner-kpi-card {
  display: grid;
  gap: 4px;
  min-height: 104px;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  background: var(--admin-surface);
  padding: 14px;
}

.partner-kpi-card span {
  color: var(--admin-muted);
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}

.partner-kpi-card strong {
  color: var(--admin-text);
  font-size: 26px;
  line-height: 1;
}

.partner-kpi-card small {
  color: var(--admin-muted);
  font-size: 12px;
}

.actions,
.pagination {
  display: flex;
  align-items: center;
  gap: 8px;
}

.toolbar {
  display: grid;
  grid-template-columns: minmax(260px, 1fr) minmax(220px, 300px) auto;
  align-items: end;
  gap: 12px;
  padding: 14px;
}

.result-count {
  min-height: 40px;
  display: inline-flex;
  align-items: center;
  color: var(--admin-muted);
  font-size: 13px;
  font-weight: 800;
  white-space: nowrap;
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

.partner-mobile-list {
  display: none;
}

table {
  width: 100%;
  min-width: 820px;
  border-collapse: collapse;
}

th:nth-child(1) { width: 110px; }
th:nth-child(2) { width: 31%; }
th:nth-child(3) { width: 20%; }
th:nth-child(4) { width: 22%; }
th:last-child { width: 122px; }

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

.status-stack {
  display: grid;
  justify-items: start;
  gap: 5px;
}

.status-stack small {
  color: var(--admin-muted);
  font-size: 11px;
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

.open-record-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 36px;
  border: 1px solid #b8d5c0;
  border-radius: 8px;
  background: #fff;
  color: #176534;
  cursor: pointer;
  padding: 0 11px;
  font-weight: 800;
  white-space: nowrap;
}

.open-record-btn:hover {
  background: #edf8f0;
}

.pagination {
  justify-content: flex-end;
  padding: 12px 16px;
}

@media (max-width: 900px) {
  .partner-list-header {
    align-items: flex-start;
  }
  .partner-kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .toolbar {
    grid-template-columns: 1fr;
  }

  .field.full {
    grid-column: auto;
  }
}

@media (max-width: 560px) {
  .partner-list-header h1 {
    font-size: 24px;
  }

  .partner-kpis {
    grid-template-columns: 1fr;
  }

  .table-card {
    border: 0;
    background: transparent;
    overflow: visible;
  }

  .partner-desktop-table {
    display: none;
  }

  .partner-mobile-list {
    display: grid;
    gap: 8px;
  }

  .partner-mobile-row {
    display: grid;
    gap: 10px;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    background: var(--admin-surface);
    padding: 13px;
  }

  .partner-mobile-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    min-width: 0;
  }

  .partner-mobile-heading > div,
  .partner-mobile-facts > span {
    display: grid;
    gap: 3px;
    min-width: 0;
  }

  .partner-mobile-heading small,
  .partner-mobile-facts small {
    color: var(--admin-muted);
    font-size: 10px;
    text-transform: uppercase;
  }

  .partner-mobile-heading strong,
  .partner-mobile-facts strong {
    min-width: 0;
    overflow: hidden;
    color: var(--admin-text);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .partner-mobile-heading .status {
    flex: 0 0 auto;
    max-width: 48%;
    white-space: normal;
  }

  .partner-mobile-row > p {
    margin: 0;
    color: var(--admin-muted);
    font-size: 12px;
    overflow-wrap: anywhere;
  }

  .partner-mobile-facts {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    border-top: 1px solid var(--admin-border);
    padding-top: 9px;
  }

  .partner-mobile-row .open-record-btn {
    width: 100%;
  }
}
</style>
