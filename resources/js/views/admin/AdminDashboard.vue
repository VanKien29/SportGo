<template>
  <section class="admin-dashboard-page">
    <header class="ad-hero">
      <div>
        <span class="ad-eyebrow">SportGo Admin · Trung tâm điều hành</span>
        <h1>Điều hành hệ thống</h1>
        <p>Ưu tiên công việc tồn đọng, theo dõi dòng tiền và kiểm soát rủi ro nền tảng.</p>
      </div>
      <div class="ad-controls">
        <label>
          <span>Kỳ tài chính</span>
          <select v-model="financePeriod" @change="loadDashboard">
            <option value="week">Tuần này</option>
            <option value="month">Tháng này</option>
            <option value="year">Năm nay</option>
          </select>
        </label>
        <button type="button" class="ad-icon-button" title="Làm mới dữ liệu" @click="loadDashboard">
          <AppIcon name="refresh" size="17" />
        </button>
      </div>
    </header>

    <div v-if="error" class="ad-alert" role="alert">
      <AppIcon name="alertCircle" size="16" />
      <span>{{ error }}</span>
      <button type="button" @click="loadDashboard">Thử lại</button>
    </div>

    <section class="ad-kpi-grid" aria-label="Chỉ số hệ thống">
      <article v-for="metric in primaryMetrics" :key="metric.key" class="ad-kpi-card" :class="`ad-kpi-card--${metric.tone}`">
        <div class="ad-kpi-heading"><span>{{ metric.label }}</span><AppIcon :name="metric.icon" size="17" /></div>
        <strong>{{ isLoading ? '...' : metric.value }}</strong>
        <small>{{ metric.caption }}</small>
      </article>
    </section>

    <section class="ad-panel ad-action-panel" aria-labelledby="ad-action-title">
      <div class="ad-panel-heading">
        <div>
          <span class="ad-eyebrow">Trung tâm công việc</span>
          <h2 id="ad-action-title">Cần xử lý trước</h2>
        </div>
        <span class="ad-muted">{{ actionCount }} việc · {{ unreadCount }} thông báo chưa đọc</span>
      </div>

      <div class="ad-action-summary">
        <router-link v-for="item in actionBuckets" :key="item.key" :to="item.to" class="ad-action-bucket" :class="{ 'has-count': item.count > 0 }">
          <span class="ad-action-icon"><AppIcon :name="item.icon" size="17" /></span>
          <span><strong>{{ item.count }}</strong><small>{{ item.label }}</small></span>
          <AppIcon name="chevronRight" size="15" />
        </router-link>
      </div>

      <div v-if="isLoading && !tasks.length" class="ad-task-state">Đang tải danh sách công việc...</div>
      <div v-else-if="!tasks.length" class="ad-task-state ad-task-state--empty">
        <AppIcon name="circleCheck" size="21" />
        <span>Không có chi tiết công việc mới; hãy mở nhóm cần xử lý ở phía trên.</span>
      </div>
      <div v-else class="ad-task-list">
        <button v-for="task in visibleTasks" :key="task.id" type="button" class="ad-task-row" :class="`priority-${task.priority}`" @click="openTask(task)">
          <span class="ad-task-priority">{{ priorityLabel(task.priority) }}</span>
          <span class="ad-task-copy"><strong>{{ task.title }}</strong><small>{{ task.description }}</small></span>
          <time>{{ formatRelative(task.created_at) }}</time>
          <span class="ad-task-action">{{ task.action_label }} <AppIcon name="chevronRight" size="14" /></span>
        </button>
      </div>
      <div v-if="tasks.length > visibleTasks.length" class="ad-task-footer">Đang hiển thị {{ visibleTasks.length }}/{{ tasks.length }} việc ưu tiên trong trung tâm công việc.</div>
    </section>

    <div class="ad-operations-grid">
      <section class="ad-panel" aria-labelledby="ad-partner-title">
        <div class="ad-panel-heading">
          <div><span class="ad-eyebrow">Vòng đời đối tác</span><h2 id="ad-partner-title">Hồ sơ và cụm sân</h2></div>
          <router-link to="/admin/partner-applications" class="ad-text-link">Mở danh sách</router-link>
        </div>
        <div class="ad-pipeline">
          <div><strong>{{ pendingCounts.partner_applications }}</strong><span>Hồ sơ chờ duyệt</span></div>
          <div><strong>{{ pendingCounts.detail?.scale_approvals || 0 }}</strong><span>Thay đổi quy mô</span></div>
          <div><strong>{{ pendingCounts.detail?.location_changes || 0 }}</strong><span>Thay đổi vị trí</span></div>
          <div><strong>{{ pendingCounts.detail?.info_changes || 0 }}</strong><span>Cập nhật thông tin</span></div>
        </div>
        <div class="ad-progress-line"><span>Đang hoạt động</span><strong>{{ activeClusterLabel }}</strong></div>
      </section>

      <section class="ad-panel" aria-labelledby="ad-risk-title">
        <div class="ad-panel-heading">
          <div><span class="ad-eyebrow">Rủi ro vận hành</span><h2 id="ad-risk-title">Tài chính và hỗ trợ</h2></div>
          <router-link to="/admin/finance-operations" class="ad-text-link">Mở tài chính</router-link>
        </div>
        <div class="ad-risk-list">
          <router-link to="/admin/finance-operations?tab=refunds"><span>Hoàn tiền chờ xử lý</span><strong>{{ pendingCounts.detail?.refunds || 0 }}</strong></router-link>
          <router-link to="/admin/finance-operations?tab=withdrawals"><span>Rút tiền chờ xử lý</span><strong>{{ pendingCounts.detail?.withdrawals || 0 }}</strong></router-link>
          <router-link to="/admin/reports-complaints?tab=reports"><span>Báo cáo chờ xử lý</span><strong>{{ pendingCounts.detail?.reports || 0 }}</strong></router-link>
          <router-link to="/admin/reports-complaints?tab=complaints"><span>Khiếu nại và hỗ trợ</span><strong>{{ pendingCounts.moderation_support || 0 }}</strong></router-link>
        </div>
      </section>
    </div>

    <section class="ad-chart-grid">
      <article class="ad-panel ad-chart-panel">
        <div class="ad-panel-heading">
          <div><span class="ad-eyebrow">Dòng tiền</span><h2>Tiền vào và tiền ra</h2></div>
          <span class="ad-muted">{{ periodLabel }}</span>
        </div>
        <div class="ad-chart-canvas"><canvas ref="cashFlowChart"></canvas></div>
      </article>
      <article class="ad-panel ad-chart-panel">
        <div class="ad-panel-heading">
          <div><span class="ad-eyebrow">Cơ cấu số dư</span><h2>Tiền đang quản lý</h2></div>
        </div>
        <div class="ad-chart-canvas ad-chart-canvas--donut"><canvas ref="compositionChart"></canvas></div>
      </article>
    </section>

    <section class="ad-panel ad-summary-panel" aria-labelledby="ad-summary-title">
      <div class="ad-panel-heading"><div><span class="ad-eyebrow">Đối soát nhanh</span><h2 id="ad-summary-title">Tổng hợp tài chính {{ periodLabel }}</h2></div><router-link to="/admin/platform-fee-ledgers" class="ad-text-link">Phí nền tảng</router-link></div>
      <div class="ad-finance-grid">
        <div><span>Booking thu hộ</span><strong>{{ money(overview.booking_collected_total) }}</strong></div>
        <div><span>Tiền chi rút ví</span><strong>{{ money(overview.withdrawal_total) }}</strong></div>
        <div><span>Voucher hệ thống</span><strong>{{ money(overview.voucher_cost_total) }}</strong></div>
        <div><span>Phí nền tảng đã thu</span><strong>{{ money(overview.platform_fee_revenue_total) }}</strong></div>
        <div><span>Gói hội viên</span><strong>{{ money(overview.membership_revenue_total) }}</strong></div>
      </div>
    </section>

    <section class="ad-panel ad-ledger-panel" aria-labelledby="ad-ledger-title">
      <div class="ad-panel-heading ad-ledger-heading">
        <div><span class="ad-eyebrow">Đối soát chi tiết</span><h2 id="ad-ledger-title">{{ activeTable.title }}</h2><p>{{ activeTable.caption }}</p></div>
        <div class="ad-ledger-tabs" role="tablist">
          <button v-for="tab in tableTabs" :key="tab.key" type="button" :class="{ active: currentTab === tab.key }" @click="currentTab = tab.key">{{ tab.label }}</button>
        </div>
      </div>
      <div class="ad-table-wrap">
        <table class="ad-table">
          <thead><tr><th v-for="column in activeTable.columns" :key="column.key">{{ column.label }}</th></tr></thead>
          <tbody>
            <tr v-if="isLoading"><td :colspan="activeTable.columns.length">Đang tải dữ liệu kế toán...</td></tr>
            <tr v-else-if="!activeRows.length"><td :colspan="activeTable.columns.length">Chưa có dữ liệu trong kỳ này.</td></tr>
            <tr v-for="row in activeRows" v-else :key="row.id"><td v-for="column in activeTable.columns" :key="column.key" :class="{ amount: column.type === 'money' }">{{ formatCell(row, column) }}</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>

<script>
import Chart from 'chart.js/auto';
import AppIcon from '../../components/AppIcon.vue';
import { api } from '../../services/api.js';

const emptyAccounting = () => ({
  period_label: 'Kỳ hiện tại',
  overview: {},
  charts: { cash_flow: [], managed_composition: [] },
  tables: {},
});

const emptyPendingCounts = () => ({
  partner_applications: 0,
  venue_clusters: 0,
  finance: 0,
  moderation_support: 0,
  detail: { scale_approvals: 0, location_changes: 0, info_changes: 0, refunds: 0, withdrawals: 0, reports: 0, moderation_posts: 0 },
});

export default {
  name: 'AdminDashboard',
  components: { AppIcon },
  data() {
    return {
      financePeriod: 'month',
      accounting: emptyAccounting(),
      pendingCounts: emptyPendingCounts(),
      workCenter: { summary: {}, tasks: [], notifications: [] },
      isLoading: true,
      error: null,
      currentTab: 'booking_ledgers',
      cashFlowChart: null,
      compositionChart: null,
      tableTabs: [
        { key: 'booking_ledgers', label: 'Booking thu hộ', title: 'Tổng hợp booking', caption: 'Các khoản tiền booking online hệ thống đã nhận hộ chủ sân.', columns: [{ key: 'code', label: 'Payment' }, { key: 'booking_code', label: 'Booking' }, { key: 'customer', label: 'Khách' }, { key: 'venue_cluster', label: 'Cụm sân' }, { key: 'amount', label: 'Số tiền', type: 'money' }, { key: 'method', label: 'Phương thức' }, { key: 'paid_at', label: 'Thời gian', type: 'date' }] },
        { key: 'withdrawal_ledgers', label: 'Yêu cầu rút', title: 'Tổng hợp yêu cầu rút', caption: 'Các khoản chi ra cho chủ sân và người dùng.', columns: [{ key: 'code', label: 'Mã yêu cầu' }, { key: 'type', label: 'Loại' }, { key: 'requester', label: 'Người nhận' }, { key: 'scope', label: 'Phạm vi' }, { key: 'amount', label: 'Số tiền', type: 'money' }, { key: 'status', label: 'Trạng thái', type: 'status' }, { key: 'requested_at', label: 'Ngày yêu cầu', type: 'date' }] },
        { key: 'owner_debts', label: 'Công nợ chủ sân', title: 'Công nợ chủ sân', caption: 'Số tiền hệ thống còn đang giữ cho từng ví chủ sân.', columns: [{ key: 'owner', label: 'Chủ sân' }, { key: 'venue_cluster', label: 'Cụm sân' }, { key: 'available_balance', label: 'Có thể rút', type: 'money' }, { key: 'pending_balance', label: 'Đang giữ', type: 'money' }, { key: 'debt_total', label: 'Tổng công nợ', type: 'money' }] },
        { key: 'customer_debts', label: 'Công nợ khách', title: 'Công nợ khách hàng', caption: 'Số dư ví và số dư đang khóa của khách hàng.', columns: [{ key: 'customer', label: 'Khách hàng' }, { key: 'contact', label: 'Liên hệ' }, { key: 'balance', label: 'Số dư', type: 'money' }, { key: 'locked_balance', label: 'Đang khóa', type: 'money' }, { key: 'debt_total', label: 'Tổng', type: 'money' }, { key: 'status', label: 'Trạng thái', type: 'status' }] },
        { key: 'voucher_ledgers', label: 'Voucher hệ thống', title: 'Lịch sử trừ tiền voucher', caption: 'Các khoản hệ thống bù voucher cho chủ sân.', columns: [{ key: 'code', label: 'Mã' }, { key: 'amount', label: 'Số tiền', type: 'money' }, { key: 'balance_after', label: 'Số dư sau', type: 'money' }, { key: 'reference', label: 'Tham chiếu' }, { key: 'description', label: 'Mô tả' }, { key: 'transacted_at', label: 'Thời gian', type: 'date' }] },
        { key: 'revenue_ledgers', label: 'Doanh thu', title: 'Lịch sử cộng doanh thu', caption: 'Phí nền tảng và thanh toán gói hội viên hệ thống.', columns: [{ key: 'label', label: 'Nguồn thu' }, { key: 'source', label: 'Đối tượng' }, { key: 'amount', label: 'Số tiền', type: 'money' }, { key: 'note', label: 'Ghi chú' }, { key: 'paid_at', label: 'Thời gian', type: 'date' }] },
      ],
    };
  },
  computed: {
    overview() { return this.accounting.overview || {}; },
    periodLabel() { return this.accounting.period_label || 'Kỳ hiện tại'; },
    actionCount() { return Number(this.workCenter.summary?.action_count || this.pendingCounts.partner_applications + this.pendingCounts.venue_clusters + this.pendingCounts.finance + this.pendingCounts.moderation_support || 0); },
    unreadCount() { return Number(this.workCenter.summary?.unread_notification_count || 0); },
    tasks() { return this.workCenter.tasks || []; },
    visibleTasks() { return this.tasks.slice(0, 6); },
    activeClusterLabel() { return this.pendingCounts.venue_clusters ? `${this.pendingCounts.venue_clusters} yêu cầu chờ duyệt` : 'Không có yêu cầu tồn'; },
    primaryMetrics() {
      return [
        { key: 'cash', label: 'Tiền hệ thống còn lại', value: this.money(this.overview.system_cash_balance), caption: 'Sau khi trừ công nợ đang giữ', tone: Number(this.overview.system_cash_balance || 0) >= 0 ? 'green' : 'red', icon: 'wallet' },
        { key: 'revenue', label: 'Doanh thu hệ thống', value: this.money(this.overview.system_revenue), caption: 'Phí nền tảng và gói hội viên', tone: 'mint', icon: 'trending-up' },
        { key: 'ownerDebt', label: 'Tiền phải trả chủ sân', value: this.money(this.overview.owner_debt_total), caption: 'Ví chủ sân có thể rút hoặc đang giữ', tone: 'blue', icon: 'building' },
        { key: 'customerDebt', label: 'Tiền ví khách hàng', value: this.money(this.overview.customer_debt_total), caption: 'Số dư và giao dịch đang khóa', tone: 'orange', icon: 'users' },
        { key: 'platformFee', label: 'Phí nền tảng đã thu', value: this.money(this.overview.platform_fee_revenue_total), caption: this.periodLabel, tone: 'purple', icon: 'layers' },
        { key: 'actions', label: 'Việc cần xử lý', value: this.formatNumber(this.actionCount), caption: 'Từ hồ sơ, tài chính và hỗ trợ', tone: this.actionCount ? 'warning' : 'green', icon: 'alertCircle' },
      ];
    },
    actionBuckets() {
      return [
        { key: 'partner', label: 'Hồ sơ đối tác', count: this.pendingCounts.partner_applications, icon: 'fileCheck', to: '/admin/partner-applications' },
        { key: 'venue', label: 'Yêu cầu cụm sân', count: this.pendingCounts.venue_clusters, icon: 'building2', to: '/admin/venue-clusters' },
        { key: 'finance', label: 'Tài chính', count: this.pendingCounts.finance, icon: 'walletCards', to: '/admin/finance-operations' },
        { key: 'support', label: 'Báo cáo & hỗ trợ', count: this.pendingCounts.moderation_support, icon: 'messageWarning', to: '/admin/reports-complaints' },
      ];
    },
    activeTable() { return this.tableTabs.find((tab) => tab.key === this.currentTab) || this.tableTabs[0]; },
    activeRows() { return this.accounting.tables?.[this.currentTab] || []; },
  },
  async mounted() { await this.loadDashboard(); },
  beforeUnmount() { this.cashFlowChart?.destroy(); this.compositionChart?.destroy(); },
  methods: {
    async loadDashboard() {
      this.isLoading = true;
      this.error = null;
      const results = await Promise.allSettled([
        api(`/api/admin/dashboard?finance_period=${this.financePeriod}`),
        api('/api/admin/pending-counts'),
        api('/api/admin/work-center'),
      ]);
      const accountingResult = results[0];
      if (accountingResult.status === 'fulfilled') this.accounting = { ...emptyAccounting(), ...(accountingResult.value?.accounting || {}) };
      else this.error = accountingResult.reason?.message || 'Không thể tải dữ liệu kế toán.';
      const pendingResult = results[1];
      if (pendingResult.status === 'fulfilled') this.pendingCounts = { ...emptyPendingCounts(), ...(pendingResult.value?.data || {}), detail: { ...emptyPendingCounts().detail, ...(pendingResult.value?.data?.detail || {}) } };
      const workResult = results[2];
      if (workResult.status === 'fulfilled') this.workCenter = workResult.value?.data || { summary: {}, tasks: [], notifications: [] };
      await this.$nextTick();
      this.renderCharts();
      this.isLoading = false;
    },
    async openTask(task) { if (task?.target) await this.$router.push(task.target).catch(() => {}); },
    priorityLabel(priority) { return priority === 'critical' ? 'Khẩn cấp' : priority === 'high' ? 'Ưu tiên cao' : 'Theo dõi'; },
    formatRelative(value) {
      if (!value) return '-';
      const timestamp = new Date(value).getTime();
      const minutes = Math.max(0, Math.floor((Date.now() - timestamp) / 60000));
      if (minutes < 60) return `${minutes || 1} phút trước`;
      if (minutes < 1440) return `${Math.floor(minutes / 60)} giờ trước`;
      return `${Math.floor(minutes / 1440)} ngày trước`;
    },
    formatNumber(value) { return Number(value || 0).toLocaleString('vi-VN'); },
    money(amount) { return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(amount || 0)); },
    shortMoney(amount) { const value = Number(amount || 0); if (Math.abs(value) >= 1000000000) return `${(value / 1000000000).toFixed(1)} tỷ`; if (Math.abs(value) >= 1000000) return `${Math.round(value / 1000000)}tr`; if (Math.abs(value) >= 1000) return `${Math.round(value / 1000)}k`; return value.toLocaleString('vi-VN'); },
    formatDate(value) { if (!value) return '-'; return new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value)); },
    formatCell(row, column) { const value = row[column.key]; if (column.type === 'money') return this.money(value); if (column.type === 'date') return this.formatDate(value); if (column.type === 'status') return this.statusLabel(value); return value || '-'; },
    statusLabel(status) { return ({ pending: 'Chờ xử lý', approved: 'Đã duyệt', paid: 'Đã chi', completed: 'Hoàn tất', rejected: 'Từ chối', cancelled: 'Đã hủy', active: 'Hoạt động', locked: 'Đang khóa', suspended: 'Tạm ngưng', owner: 'Chủ sân', user: 'Người dùng' })[status] || status || '-'; },
    renderCharts() { this.renderCashFlowChart(); this.renderCompositionChart(); },
    renderCashFlowChart() {
      const canvas = this.$refs.cashFlowChart;
      if (!canvas) return;
      this.cashFlowChart?.destroy();
      const values = this.accounting.charts?.cash_flow || [];
      this.cashFlowChart = new Chart(canvas, { type: 'line', data: { labels: values.map((item) => item.label), datasets: [{ label: 'Tiền vào', data: values.map((item) => item.money_in), borderColor: '#15803d', backgroundColor: 'rgba(21,128,61,.08)', fill: true, tension: .28, pointRadius: 0, pointHoverRadius: 4, borderWidth: 2.5 }, { label: 'Tiền ra', data: values.map((item) => item.money_out), borderColor: '#d97706', backgroundColor: 'rgba(217,119,6,.06)', fill: false, tension: .28, pointRadius: 0, pointHoverRadius: 4, borderWidth: 2.5 }, { label: 'Chênh lệch', data: values.map((item) => item.net_movement), borderColor: '#64748b', fill: false, tension: .2, pointRadius: 0, pointHoverRadius: 4, borderWidth: 1.8, borderDash: [4, 4] }] }, options: this.chartOptions() });
    },
    renderCompositionChart() {
      const canvas = this.$refs.compositionChart;
      if (!canvas) return;
      this.compositionChart?.destroy();
      const slices = (this.accounting.charts?.managed_composition || []).filter((item) => Number(item.value || 0) > 0);
      const values = slices.length ? slices : [{ label: 'Chưa có dữ liệu', value: 1, group: 'empty' }];
      const colors = { cash: '#15803d', owner_debt: '#2563eb', customer_debt: '#d97706', empty: '#e2e8f0' };
      this.compositionChart = new Chart(canvas, { type: 'doughnut', data: { labels: values.map((item) => item.label), datasets: [{ data: values.map((item) => item.value), backgroundColor: values.map((item) => colors[item.group] || '#94a3b8'), borderColor: '#fff', borderWidth: 4 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, color: '#475569', font: { size: 11, weight: 600 } } }, tooltip: { callbacks: { label: (context) => values[context.dataIndex]?.group === 'empty' ? 'Chưa có dữ liệu' : `${context.label}: ${this.money(context.parsed)}` } } } } });
    },
    chartOptions() { return { responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' }, scales: { x: { grid: { display: false }, border: { display: false }, ticks: { color: '#64748b', maxRotation: 0, font: { size: 10 } } }, y: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(148,163,184,.16)', drawTicks: false }, ticks: { color: '#64748b', padding: 8, callback: (value) => this.shortMoney(value) } } }, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true, color: '#475569', font: { size: 11, weight: 600 } } }, tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${this.money(context.parsed.y)}` } } } }; },
  },
};
</script>

<style scoped>
.admin-dashboard-page { display: flex; flex-direction: column; gap: 18px; color: #17211b; }
.ad-hero { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; }
.ad-eyebrow { display: block; color: #64748b; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; }
.ad-hero h1 { margin: 5px 0 4px; color: #0f172a; font-size: 26px; font-weight: 650; letter-spacing: -.02em; }
.ad-hero p { margin: 0; color: #64748b; font-size: 13px; }
.ad-controls { display: flex; align-items: flex-end; gap: 10px; }
.ad-controls label { display: grid; gap: 5px; color: #64748b; font-size: 11px; }
.ad-controls select { min-height: 36px; min-width: 140px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #0f172a; font: inherit; padding: 0 10px; outline: none; }
.ad-controls select:focus { border-color: #15803d; box-shadow: 0 0 0 3px rgba(21,128,61,.1); }
.ad-icon-button { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #334155; cursor: pointer; }
.ad-icon-button:hover { border-color: #15803d; color: #15803d; background: #f0fdf4; }
.ad-alert { display: flex; align-items: center; gap: 8px; padding: 11px 13px; border: 1px solid #fecaca; border-radius: 6px; background: #fff7f7; color: #b91c1c; font-size: 13px; }
.ad-alert button { margin-left: auto; border: 0; background: none; color: inherit; font-size: 12px; font-weight: 650; cursor: pointer; }
.ad-panel { min-width: 0; padding: 18px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; box-shadow: 0 5px 18px rgba(15,23,42,.035); }
.ad-panel-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.ad-panel-heading h2 { margin: 3px 0 0; color: #0f172a; font-size: 15px; font-weight: 650; }
.ad-panel-heading p { margin: 5px 0 0; color: #64748b; font-size: 12px; }
.ad-muted { color: #64748b; font-size: 11px; }
.ad-text-link { color: #15803d; font-size: 12px; font-weight: 650; text-decoration: none; white-space: nowrap; }.ad-text-link:hover { text-decoration: underline; }
.ad-kpi-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; }
.ad-kpi-card { min-height: 120px; padding: 14px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; box-shadow: 0 5px 18px rgba(15,23,42,.035); }
.ad-kpi-heading { display: flex; align-items: center; justify-content: space-between; color: #64748b; font-size: 11px; }.ad-kpi-heading svg { color: #15803d; }
.ad-kpi-card > strong { display: block; margin-top: 15px; color: #0f172a; font-size: 18px; font-weight: 700; }.ad-kpi-card > small { display: block; margin-top: 5px; color: #64748b; font-size: 10px; line-height: 1.35; }
.ad-kpi-card--green { border-top: 3px solid #16a34a; }.ad-kpi-card--mint { border-top: 3px solid #34d399; }.ad-kpi-card--blue { border-top: 3px solid #60a5fa; }.ad-kpi-card--orange { border-top: 3px solid #f59e0b; }.ad-kpi-card--purple { border-top: 3px solid #a78bfa; }.ad-kpi-card--warning { border-top: 3px solid #f97316; }
.ad-action-panel { padding-bottom: 14px; }.ad-action-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 9px; margin: 15px 0; }
.ad-action-bucket { display: flex; align-items: center; gap: 9px; min-height: 60px; padding: 10px; border: 1px solid #e2e8f0; border-radius: 7px; color: #334155; text-decoration: none; }.ad-action-bucket:hover, .ad-action-bucket.has-count { border-color: #bbf7d0; background: #f0fdf4; }.ad-action-icon { display: inline-flex; align-items: center; justify-content: center; width: 29px; height: 29px; border-radius: 6px; background: #f1f5f9; color: #475569; }.ad-action-bucket.has-count .ad-action-icon { color: #15803d; background: #dcfce7; }.ad-action-bucket > span:nth-child(2) { display: grid; flex: 1; gap: 2px; }.ad-action-bucket strong { color: #0f172a; font-size: 18px; line-height: 1; }.ad-action-bucket small { color: #64748b; font-size: 10px; }
.ad-task-list { display: grid; border-top: 1px solid #eef2f7; }.ad-task-row { display: grid; grid-template-columns: 78px minmax(0, 1fr) 90px auto; align-items: center; gap: 12px; min-height: 58px; border: 0; border-bottom: 1px solid #eef2f7; background: #fff; color: #334155; text-align: left; cursor: pointer; }.ad-task-row:hover { background: #f8fafc; }.ad-task-priority { width: fit-content; padding: 4px 6px; border-radius: 4px; background: #f1f5f9; color: #64748b; font-size: 9px; font-weight: 700; text-transform: uppercase; }.ad-task-row.priority-critical .ad-task-priority { background: #fee2e2; color: #b91c1c; }.ad-task-row.priority-high .ad-task-priority { background: #fef3c7; color: #b45309; }.ad-task-copy { display: grid; min-width: 0; gap: 3px; }.ad-task-copy strong { overflow: hidden; color: #0f172a; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }.ad-task-copy small { overflow: hidden; color: #64748b; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }.ad-task-row time { color: #64748b; font-size: 10px; white-space: nowrap; }.ad-task-action { display: inline-flex; align-items: center; gap: 3px; color: #15803d; font-size: 11px; font-weight: 650; white-space: nowrap; }.ad-task-state { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 82px; color: #64748b; font-size: 12px; }.ad-task-state--empty { color: #15803d; }.ad-task-footer { padding-top: 10px; color: #94a3b8; font-size: 10px; text-align: right; }
.ad-operations-grid, .ad-chart-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }.ad-pipeline { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 20px; }.ad-pipeline div { display: grid; gap: 4px; padding-right: 8px; border-right: 1px solid #eef2f7; }.ad-pipeline div:last-child { border-right: 0; }.ad-pipeline strong { color: #0f172a; font-size: 21px; }.ad-pipeline span, .ad-progress-line { color: #64748b; font-size: 10px; line-height: 1.35; }.ad-progress-line { display: flex; justify-content: space-between; gap: 12px; margin-top: 19px; padding-top: 12px; border-top: 1px solid #eef2f7; }.ad-progress-line strong { color: #15803d; font-weight: 650; }.ad-risk-list { display: grid; gap: 0; margin-top: 10px; }.ad-risk-list a { display: flex; align-items: center; justify-content: space-between; min-height: 38px; border-bottom: 1px solid #eef2f7; color: #475569; font-size: 11px; text-decoration: none; }.ad-risk-list a:last-child { border-bottom: 0; }.ad-risk-list a:hover { color: #15803d; }.ad-risk-list strong { color: #0f172a; font-size: 15px; }
.ad-chart-panel { min-height: 330px; }.ad-chart-canvas { position: relative; height: 245px; margin-top: 16px; }.ad-chart-canvas--donut { height: 245px; max-width: 390px; margin-inline: auto; }
.ad-summary-panel { padding-bottom: 14px; }.ad-finance-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 17px; }.ad-finance-grid div { display: grid; gap: 7px; padding: 11px 12px; border: 1px solid #eef2f7; border-radius: 6px; background: #f8fafc; }.ad-finance-grid span { color: #64748b; font-size: 10px; }.ad-finance-grid strong { color: #0f172a; font-size: 13px; }
.ad-ledger-heading { align-items: flex-end; }.ad-ledger-tabs { display: flex; align-items: center; gap: 3px; max-width: 65%; overflow-x: auto; }.ad-ledger-tabs button { flex: 0 0 auto; min-height: 30px; padding: 0 9px; border: 1px solid transparent; border-radius: 5px; background: #f8fafc; color: #64748b; font-size: 10px; cursor: pointer; }.ad-ledger-tabs button:hover { color: #15803d; }.ad-ledger-tabs button.active { border-color: #bbf7d0; background: #f0fdf4; color: #15803d; font-weight: 650; }.ad-table-wrap { margin-top: 16px; overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 6px; }.ad-table { width: 100%; min-width: 700px; border-collapse: collapse; font-size: 11px; }.ad-table th { padding: 10px 11px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; font-size: 10px; font-weight: 650; text-align: left; white-space: nowrap; }.ad-table td { padding: 11px; border-bottom: 1px solid #eef2f7; color: #334155; white-space: nowrap; }.ad-table tr:last-child td { border-bottom: 0; }.ad-table td.amount { color: #0f172a; font-weight: 650; text-align: right; }
@media (max-width: 1200px) { .ad-kpi-grid { grid-template-columns: repeat(3, 1fr); }.ad-action-summary { grid-template-columns: repeat(2, 1fr); }.ad-finance-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 800px) { .ad-hero { align-items: flex-start; flex-direction: column; }.ad-controls { width: 100%; }.ad-operations-grid, .ad-chart-grid { grid-template-columns: 1fr; }.ad-task-row { grid-template-columns: 72px minmax(0, 1fr) auto; }.ad-task-row time { display: none; }.ad-ledger-heading { align-items: flex-start; flex-direction: column; }.ad-ledger-tabs { max-width: 100%; width: 100%; }.ad-chart-panel { min-height: 280px; }.ad-chart-canvas { height: 205px; } }
@media (max-width: 560px) { .ad-panel { padding: 14px; }.ad-kpi-grid, .ad-action-summary, .ad-finance-grid { grid-template-columns: 1fr 1fr; }.ad-pipeline { grid-template-columns: repeat(2, 1fr); row-gap: 14px; }.ad-pipeline div:nth-child(2) { border-right: 0; }.ad-pipeline div:nth-child(3) { padding-top: 4px; }.ad-task-row { grid-template-columns: 1fr auto; gap: 7px; padding: 10px 0; }.ad-task-priority { grid-column: 1 / -1; }.ad-task-copy small { white-space: normal; }.ad-task-action { grid-column: 2; grid-row: 2; }.ad-finance-grid strong { font-size: 12px; } }
</style>
