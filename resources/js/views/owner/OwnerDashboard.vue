<template>
  <section class="owner-dashboard-page">
    <div v-if="error" class="od-alert od-alert--error" role="alert">
      <AppIcon name="alertCircle" size="16" />
      <span>{{ error }}</span>
      <button type="button" class="od-link-button" @click="loadStats">Thử lại</button>
    </div>

    <header class="od-hero">
      <div>
        <span class="od-eyebrow">{{ greeting }}, {{ userName }}</span>
        <h1>Bảng điều hành</h1>
        <p>{{ selectedClusterLabel }} · {{ periodCaption }}</p>
      </div>
      <div class="od-controls">
        <label class="od-select-label">
          <span>Khoảng thời gian</span>
          <select v-model="periodKey" @change="selectPeriod(periodKey)">
            <option v-for="option in periodOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </label>
        <div v-if="periodKey === 'custom'" class="od-date-range">
          <input v-model="customDateFrom" type="date" :max="today" @change="loadStats" />
          <span>đến</span>
          <input v-model="customDateTo" type="date" :max="today" @change="loadStats" />
        </div>
        <button type="button" class="od-icon-button" title="Làm mới dữ liệu" @click="loadStats">
          <AppIcon name="refresh" size="17" />
        </button>
      </div>
    </header>

    <section class="od-section od-attention-section" aria-labelledby="od-attention-title">
      <div class="od-section-heading">
        <div>
          <span class="od-eyebrow">Ưu tiên hôm nay</span>
          <h2 id="od-attention-title">Cần xử lý</h2>
        </div>
        <span class="od-updated">{{ isLoading ? 'Đang đồng bộ...' : 'Cập nhật theo thời gian thực' }}</span>
      </div>
      <div class="od-attention-grid">
        <router-link
          v-for="item in attentionItems"
          :key="item.key"
          :to="item.to"
          class="od-attention-item"
          :class="`od-attention-item--${item.tone}`"
        >
          <span class="od-attention-icon"><AppIcon :name="item.icon" size="17" /></span>
          <span class="od-attention-copy">
            <strong>{{ item.value }}</strong>
            <span>{{ item.label }}</span>
          </span>
          <AppIcon name="chevronRight" size="16" />
        </router-link>
      </div>
    </section>

    <div v-if="!hasClusters" class="od-empty-state">
      <span class="od-empty-icon"><AppIcon name="building" size="22" /></span>
      <div>
        <h2>Chưa có cụm sân để vận hành</h2>
        <p>Hoàn thiện hồ sơ đối tác hoặc tạo cụm sân để bắt đầu theo dõi booking và doanh thu.</p>
      </div>
      <router-link to="/owner/venue-clusters" class="od-primary-button">Quản lý cụm sân</router-link>
    </div>

    <template v-else>
      <section class="od-kpi-grid" aria-label="Chỉ số kinh doanh">
        <article v-for="metric in periodMetrics" :key="metric.key" class="od-kpi-card">
          <div class="od-kpi-topline">
            <span>{{ metric.label }}</span>
            <span class="od-kpi-icon"><AppIcon :name="metric.icon" size="16" /></span>
          </div>
          <strong>{{ isLoading ? '...' : metric.value }}</strong>
          <small>{{ metric.note }}</small>
        </article>
      </section>

      <div class="od-main-grid">
        <section class="od-panel od-schedule-panel" aria-labelledby="od-schedule-title">
          <div class="od-panel-heading">
            <div>
              <span class="od-eyebrow">{{ formatLongDate(today) }}</span>
              <h2 id="od-schedule-title">Lịch sân hôm nay</h2>
            </div>
            <router-link to="/owner/booking-list" class="od-text-link">Mở danh sách</router-link>
          </div>

          <div class="od-today-summary">
            <div><span>Tổng lịch</span><strong>{{ stats.today_booking_summary.total }}</strong></div>
            <div><span>Chờ xử lý</span><strong class="od-value-warning">{{ todayPendingCount }}</strong></div>
            <div><span>Đã thanh toán</span><strong class="od-value-success">{{ stats.today_booking_summary.paid }}</strong></div>
            <div><span>Doanh thu</span><strong class="od-value-success">{{ formatCurrency(stats.today_booking_summary.revenue) }}</strong></div>
          </div>

          <div v-if="isLoading" class="od-loading-state">Đang tải lịch hôm nay...</div>
          <div v-else-if="!todayBookings.length" class="od-table-empty">
            <AppIcon name="calendar" size="20" />
            <span>Hôm nay chưa có booking.</span>
            <router-link to="/owner/counter-booking" class="od-text-link">Tạo booking tại quầy</router-link>
          </div>
          <div v-else class="od-booking-list">
            <router-link
              v-for="booking in todayBookings"
              :key="booking.id"
              :to="`/owner/booking-list?keyword=${encodeURIComponent(booking.booking_code)}`"
              class="od-booking-row"
            >
              <time>{{ booking.time_label }}</time>
              <span class="od-booking-main">
                <strong>{{ booking.court_label }}</strong>
                <small>{{ booking.customer_name }} · {{ booking.source_label }}</small>
              </span>
              <span class="od-booking-meta">
                <span class="od-status" :class="statusTone(booking.status)">{{ booking.status_label }}</span>
                <small>{{ booking.payment_state_label }}</small>
              </span>
              <AppIcon name="chevronRight" size="16" />
            </router-link>
          </div>
        </section>

        <aside class="od-side-column">
          <section class="od-panel od-wallet-panel" aria-labelledby="od-wallet-title">
            <div class="od-panel-heading">
              <div>
                <span class="od-eyebrow">Tài chính</span>
                <h2 id="od-wallet-title">Số dư có thể rút</h2>
              </div>
              <AppIcon name="wallet" size="20" />
            </div>
            <strong class="od-wallet-value">{{ formatCurrency(stats.wallet.available_balance) }}</strong>
            <div class="od-wallet-details">
              <span>Đang chờ rút <strong>{{ formatCurrency(stats.wallet.pending_withdrawal_balance) }}</strong></span>
              <span>Tổng đã kiếm <strong>{{ formatCurrency(stats.wallet.total_earned) }}</strong></span>
            </div>
            <router-link to="/owner/finance" class="od-primary-button od-primary-button--full">Mở tài chính</router-link>
          </section>

          <section class="od-panel" aria-labelledby="od-health-title">
            <div class="od-panel-heading">
              <div>
                <span class="od-eyebrow">Vận hành</span>
                <h2 id="od-health-title">Sức khỏe sân</h2>
              </div>
              <router-link to="/owner/venue-courts" class="od-text-link">Quản lý</router-link>
            </div>
            <div class="od-health-total"><strong>{{ stats.court_statuses.total }}</strong><span>sân con</span></div>
            <div class="od-health-list">
              <div><span><i class="od-dot od-dot--success"></i>Đang hoạt động</span><strong>{{ stats.court_statuses.active }}</strong></div>
              <div><span><i class="od-dot od-dot--warning"></i>Bảo trì</span><strong>{{ stats.court_statuses.maintenance }}</strong></div>
              <div><span><i class="od-dot od-dot--muted"></i>Tạm ngưng</span><strong>{{ stats.court_statuses.inactive }}</strong></div>
            </div>
          </section>
        </aside>
      </div>

      <div class="od-chart-grid">
        <section class="od-panel od-chart-panel" aria-labelledby="od-revenue-title">
          <div class="od-panel-heading">
            <div>
              <span class="od-eyebrow">Xu hướng</span>
              <h2 id="od-revenue-title">Doanh thu theo ngày</h2>
            </div>
            <span class="od-chart-total">{{ formatCurrency(stats.period_summary.revenue) }}</span>
          </div>
          <div v-if="trendHasData" class="od-bar-chart" :aria-label="trendAriaLabel">
            <div v-for="bar in trendBars" :key="bar.date_from" class="od-bar-column">
              <span class="od-bar-value">{{ compactCurrency(bar.revenue) }}</span>
              <span class="od-bar-track"><span class="od-bar-fill" :style="{ height: `${trendHeight(bar.revenue)}%` }"></span></span>
              <small>{{ bar.label }}</small>
            </div>
          </div>
          <div v-else class="od-chart-empty">Chưa có doanh thu trong khoảng thời gian này.</div>
        </section>

        <section class="od-panel od-chart-panel" aria-labelledby="od-status-title">
          <div class="od-panel-heading">
            <div>
              <span class="od-eyebrow">Hiệu suất</span>
              <h2 id="od-status-title">Trạng thái booking</h2>
            </div>
            <span class="od-chart-total">{{ formatNumber(stats.period_summary.bookings) }} booking</span>
          </div>
          <div class="od-status-chart">
            <div v-for="status in stats.booking_statuses" :key="status.key" class="od-status-row">
              <span>{{ status.label }}</span>
              <span class="od-status-track"><span :class="`od-status-fill od-status-fill--${status.key}`" :style="{ width: `${statusPercent(status.count)}%` }"></span></span>
              <strong>{{ status.count }}</strong>
            </div>
          </div>
        </section>
      </div>

      <div class="od-insight-grid">
        <section class="od-panel" aria-labelledby="od-court-revenue-title">
          <div class="od-panel-heading">
            <div>
              <span class="od-eyebrow">Hiệu quả sân</span>
              <h2 id="od-court-revenue-title">Doanh thu theo sân con</h2>
            </div>
            <router-link to="/owner/venue-courts" class="od-text-link">Chi tiết</router-link>
          </div>
          <div v-if="stats.court_revenues.length" class="od-ranking-list">
            <div v-for="(court, index) in stats.court_revenues.slice(0, 5)" :key="court.court_name">
              <span class="od-rank">{{ index + 1 }}</span>
              <span class="od-ranking-name">{{ court.court_name }}</span>
              <span class="od-ranking-bar"><i :style="{ width: `${rankingWidth(court.revenue, maxCourtRevenue)}%` }"></i></span>
              <strong>{{ compactCurrency(court.revenue) }}</strong>
            </div>
          </div>
          <div v-else class="od-chart-empty">Chưa có dữ liệu doanh thu theo sân.</div>
        </section>

        <section class="od-panel" aria-labelledby="od-hours-title">
          <div class="od-panel-heading">
            <div>
              <span class="od-eyebrow">Nhu cầu</span>
              <h2 id="od-hours-title">Khung giờ phổ biến</h2>
            </div>
          </div>
          <div v-if="stats.golden_hours.length" class="od-ranking-list od-hours-list">
            <div v-for="(slot, index) in stats.golden_hours" :key="slot.time_slot">
              <span class="od-rank">{{ index + 1 }}</span>
              <span class="od-ranking-name">{{ slot.time_slot }}</span>
              <span class="od-ranking-bar"><i :style="{ width: `${rankingWidth(slot.count, maxGoldenHour)}%` }"></i></span>
              <strong>{{ slot.count }} lượt</strong>
            </div>
          </div>
          <div v-else class="od-chart-empty">Chưa có dữ liệu khung giờ.</div>
        </section>
      </div>

      <section class="od-panel od-recent-panel" aria-labelledby="od-recent-title">
        <div class="od-panel-heading">
          <div>
            <span class="od-eyebrow">Hoạt động gần đây</span>
            <h2 id="od-recent-title">Booking trong kỳ</h2>
          </div>
          <router-link to="/owner/booking-list" class="od-text-link">Xem tất cả</router-link>
        </div>
        <div v-if="stats.recent_bookings.length" class="od-recent-grid">
          <router-link v-for="booking in stats.recent_bookings" :key="booking.id" :to="`/owner/booking-list?keyword=${encodeURIComponent(booking.booking_code)}`" class="od-recent-row">
            <strong>{{ booking.booking_code }}</strong>
            <span>{{ booking.customer_name }}</span>
            <span>{{ booking.court_label }}</span>
            <span>{{ formatDate(booking.booking_date) }} · {{ booking.time_label }}</span>
            <span class="od-status" :class="statusTone(booking.status)">{{ booking.status_label }}</span>
            <strong class="od-recent-amount">{{ formatCurrency(booking.total_price) }}</strong>
          </router-link>
        </div>
        <div v-else class="od-table-empty">Chưa có booking trong kỳ.</div>
      </section>
    </template>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { api } from '../../services/api.js';
import { venueClusterService } from '../../services/venueClusters.js';
import { getAuth } from '../../stores/auth.js';

const emptyStats = () => ({
  bookings: 0,
  revenue: 0,
  rating: 0,
  venue_cluster_id: null,
  period: { key: 'today', label: 'Hôm nay', date_from: null, date_to: null },
  period_summary: { bookings: 0, revenue: 0, average_booking_value: 0, completed: 0, cancelled: 0, online_bookings: 0, counter_bookings: 0 },
  booking_statuses: [
    { key: 'pending', label: 'Đang chờ xử lý', count: 0 },
    { key: 'confirmed', label: 'Đã xác nhận', count: 0 },
    { key: 'playing', label: 'Đang chơi', count: 0 },
    { key: 'completed', label: 'Hoàn thành', count: 0 },
    { key: 'cancelled', label: 'Hủy / từ chối', count: 0 },
  ],
  revenue_trend: [],
  operations: { pending_bookings: 0, pending_refunds: 0, pending_refund_amount: 0, pending_withdrawals: 0, pending_withdrawal_amount: 0, open_complaints: 0, latest_refunds: [], latest_withdrawals: [] },
  recent_bookings: [],
  court_statuses: { total: 0, active: 0, maintenance: 0, inactive: 0 },
  wallet: { available_balance: 0, pending_withdrawal_balance: 0, total_earned: 0, total_withdrawn: 0 },
  today_booking_summary: { date: null, total: 0, pending_approval: 0, pending_payment: 0, paid: 0, cancelled: 0, revenue: 0 },
  today_bookings: [],
  pending_bookings: [],
  cancelled_today: [],
  golden_hours: [],
  court_revenues: [],
  published_posts: [],
});

export default {
  name: 'OwnerDashboard',
  components: { AppIcon },
  data() {
    return {
      user: getAuth() || {},
      selectedCluster: null,
      periodKey: 'today',
      customDateFrom: '',
      customDateTo: '',
      isLoading: true,
      error: '',
      stats: emptyStats(),
      periodOptions: [
        { value: 'today', label: 'Hôm nay' },
        { value: '7_days', label: '7 ngày gần nhất' },
        { value: '30_days', label: '30 ngày gần nhất' },
        { value: 'this_month', label: 'Tháng này' },
        { value: 'custom', label: 'Tùy chọn' },
      ],
    };
  },
  computed: {
    today() {
      return this.localDateString();
    },
    userName() {
      return this.user.fullName || this.user.full_name || this.user.username || 'Chủ sân';
    },
    greeting() {
      const hour = new Date().getHours();
      return hour < 11 ? 'Chào buổi sáng' : hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối';
    },
    hasClusters() {
      return Boolean(this.selectedCluster?.id || this.stats.venue_cluster_id);
    },
    selectedClusterLabel() {
      return this.selectedCluster?.name || (this.stats.venue_cluster_id ? 'Cụm sân đang chọn' : 'Tất cả cụm sân');
    },
    periodCaption() {
      if (!this.stats.period?.date_from) return 'Đang cập nhật';
      return `${this.stats.period.label} · ${this.formatDateRange(this.stats.period.date_from, this.stats.period.date_to)}`;
    },
    todayBookings() {
      return this.stats.today_bookings || [];
    },
    todayPendingCount() {
      return Number(this.stats.today_booking_summary.pending_approval || 0) + Number(this.stats.today_booking_summary.pending_payment || 0);
    },
    attentionItems() {
      const operations = this.stats.operations || {};
      return [
        { key: 'bookings', label: 'Booking cần xử lý', value: operations.pending_bookings || 0, icon: 'calendar', tone: operations.pending_bookings ? 'warning' : 'neutral', to: '/owner/booking-list?status=pending' },
        { key: 'refunds', label: 'Yêu cầu hoàn tiền', value: operations.pending_refunds || 0, icon: 'refresh', tone: operations.pending_refunds ? 'danger' : 'neutral', to: '/owner/refunds?status=pending_owner_confirmation' },
        { key: 'withdrawals', label: 'Lệnh rút đang xử lý', value: operations.pending_withdrawals || 0, icon: 'banknote', tone: operations.pending_withdrawals ? 'info' : 'neutral', to: '/owner/finance' },
        { key: 'complaints', label: 'Khiếu nại đang mở', value: operations.open_complaints || 0, icon: 'alertCircle', tone: operations.open_complaints ? 'danger' : 'neutral', to: '/owner/complaints?status=open' },
      ];
    },
    periodMetrics() {
      const summary = this.stats.period_summary || {};
      const completionRate = summary.bookings ? Math.round((Number(summary.completed || 0) / Number(summary.bookings)) * 100) : 0;
      return [
        { key: 'bookings', label: 'Tổng booking', value: this.formatNumber(summary.bookings), note: `${summary.online_bookings || 0} online · ${summary.counter_bookings || 0} tại quầy`, icon: 'calendar' },
        { key: 'revenue', label: 'Doanh thu đã thu', value: this.formatCurrency(summary.revenue), note: 'Giao dịch đã thanh toán', icon: 'trending-up' },
        { key: 'average', label: 'Giá trị trung bình', value: this.formatCurrency(summary.average_booking_value), note: 'Trên mỗi booking trong kỳ', icon: 'barChart' },
        { key: 'completion', label: 'Tỷ lệ hoàn thành', value: `${completionRate}%`, note: `${summary.completed || 0} hoàn thành · ${summary.cancelled || 0} hủy`, icon: 'checkCircle' },
      ];
    },
    trendBars() {
      const rows = this.stats.revenue_trend || [];
      if (rows.length <= 16) return rows.map((row) => ({ ...row, date_from: row.date, date_to: row.date }));
      const size = Math.ceil(rows.length / 16);
      const grouped = [];
      for (let i = 0; i < rows.length; i += size) {
        const chunk = rows.slice(i, i + size);
        grouped.push({ date_from: chunk[0].date, date_to: chunk.at(-1).date, label: `${chunk[0].label}–${chunk.at(-1).label}`, bookings: chunk.reduce((sum, row) => sum + Number(row.bookings || 0), 0), revenue: chunk.reduce((sum, row) => sum + Number(row.revenue || 0), 0) });
      }
      return grouped;
    },
    maxTrendRevenue() {
      return Math.max(...this.trendBars.map((row) => Number(row.revenue || 0)), 0);
    },
    trendHasData() {
      return this.maxTrendRevenue > 0;
    },
    trendAriaLabel() {
      return `Biểu đồ doanh thu ${this.stats.period?.label || ''}, tổng ${this.formatCurrency(this.stats.period_summary.revenue)}`;
    },
    maxCourtRevenue() {
      return Math.max(...(this.stats.court_revenues || []).map((row) => Number(row.revenue || 0)), 0);
    },
    maxGoldenHour() {
      return Math.max(...(this.stats.golden_hours || []).map((row) => Number(row.count || 0)), 0);
    },
  },
  mounted() {
    window.addEventListener('owner-cluster-changed', this.handleClusterChange);
    this.loadStats();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.handleClusterChange);
  },
  methods: {
    async handleClusterChange(event) {
      this.selectedCluster = event?.detail || null;
      await this.loadStats();
    },
    async resolveSelectedCluster(clusterId) {
      if (!clusterId || this.selectedCluster?.id) return;
      try {
        const response = await venueClusterService.getClusters({ compact: 1 });
        const clusters = response?.data || [];
        this.selectedCluster = clusters.find((cluster) => String(cluster.id) === String(clusterId)) || null;
      } catch {
        this.selectedCluster = null;
      }
    },
    async loadStats() {
      if (this.periodKey === 'custom' && (!this.customDateFrom || !this.customDateTo || this.customDateFrom > this.customDateTo)) return;
      this.isLoading = true;
      this.error = '';
      const clusterId = this.selectedCluster?.id || localStorage.getItem('selected_cluster');
      await this.resolveSelectedCluster(clusterId);
      const params = new URLSearchParams({ period: this.periodKey });
      if (clusterId) params.set('venue_cluster_id', clusterId);
      if (this.periodKey === 'custom') {
        params.set('date_from', this.customDateFrom);
        params.set('date_to', this.customDateTo);
      }
      try {
        const response = await api(`/api/owner/dashboard?${params.toString()}`);
        const base = emptyStats();
        this.stats = {
          ...base,
          ...response,
          period_summary: { ...base.period_summary, ...(response.period_summary || {}) },
          operations: { ...base.operations, ...(response.operations || {}) },
          court_statuses: { ...base.court_statuses, ...(response.court_statuses || {}) },
          wallet: { ...base.wallet, ...(response.wallet || {}) },
          today_booking_summary: { ...base.today_booking_summary, ...(response.today_booking_summary || {}) },
          booking_statuses: response.booking_statuses || base.booking_statuses,
          revenue_trend: response.revenue_trend || base.revenue_trend,
          today_bookings: response.today_bookings || [],
          recent_bookings: response.recent_bookings || [],
          court_revenues: response.court_revenues || [],
          golden_hours: response.golden_hours || [],
        };
      } catch (requestError) {
        this.error = requestError.message || 'Không thể tải dữ liệu bảng điều hành.';
      } finally {
        this.isLoading = false;
      }
    },
    selectPeriod(value) {
      this.periodKey = value;
      if (value === 'custom') {
        if (!this.customDateFrom) this.customDateFrom = this.today;
        if (!this.customDateTo) this.customDateTo = this.today;
        return;
      }
      this.loadStats();
    },
    statusTone(status) {
      if (['confirmed', 'completed'].includes(status)) return 'od-status--success';
      if (status === 'checked_in') return 'od-status--info';
      if (['cancelled', 'rejected', 'expired'].includes(status)) return 'od-status--danger';
      return 'od-status--warning';
    },
    statusPercent(count) {
      const total = Number(this.stats.period_summary?.bookings || 0);
      return total && count ? Math.max(4, Math.round((Number(count) / total) * 100)) : 0;
    },
    trendHeight(value) {
      return this.maxTrendRevenue && value ? Math.max(8, Math.round((Number(value) / this.maxTrendRevenue) * 100)) : 0;
    },
    rankingWidth(value, max) {
      return max && value ? Math.max(8, Math.round((Number(value) / max) * 100)) : 0;
    },
    compactCurrency(value) {
      const amount = Number(value || 0);
      if (Math.abs(amount) >= 1000000000) return `${(amount / 1000000000).toFixed(1)} tỷ`;
      if (Math.abs(amount) >= 1000000) return `${(amount / 1000000).toFixed(1)}tr`;
      if (Math.abs(amount) >= 1000) return `${Math.round(amount / 1000)}k`;
      return amount.toLocaleString('vi-VN');
    },
    formatCurrency(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0));
    },
    formatNumber(value) {
      return Number(value || 0).toLocaleString('vi-VN');
    },
    formatDate(value) {
      if (!value) return 'Chưa rõ ngày';
      return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(`${String(value).slice(0, 10)}T00:00:00`));
    },
    formatLongDate(value) {
      return new Intl.DateTimeFormat('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(`${String(value).slice(0, 10)}T00:00:00`));
    },
    formatDateRange(from, to) {
      if (!from || !to) return 'Chưa chọn khoảng';
      return from === to ? this.formatDate(from) : `${this.formatDate(from)} – ${this.formatDate(to)}`;
    },
    localDateString(value = new Date()) {
      const year = value.getFullYear();
      const month = String(value.getMonth() + 1).padStart(2, '0');
      const day = String(value.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    },
  },
};
</script>

<style scoped>
.owner-dashboard-page { display: flex; flex-direction: column; gap: 20px; color: #17211b; }
.od-hero { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; padding-bottom: 2px; }
.od-eyebrow { display: block; color: #64748b; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; }
.od-hero h1 { margin: 5px 0 4px; color: #0f172a; font-size: 26px; font-weight: 650; letter-spacing: -.02em; }
.od-hero p { margin: 0; color: #64748b; font-size: 13px; }
.od-controls { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
.od-select-label { display: grid; gap: 5px; color: #64748b; font-size: 11px; }
.od-select-label select, .od-date-range input { min-height: 36px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #0f172a; font: inherit; padding: 0 10px; outline: none; }
.od-select-label select:focus, .od-date-range input:focus { border-color: #15803d; box-shadow: 0 0 0 3px rgba(21,128,61,.1); }
.od-date-range { display: flex; align-items: center; gap: 6px; color: #64748b; font-size: 12px; }
.od-icon-button { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #334155; cursor: pointer; }
.od-icon-button:hover { border-color: #15803d; color: #15803d; background: #f0fdf4; }
.od-alert { display: flex; align-items: center; gap: 8px; padding: 11px 13px; border: 1px solid; border-radius: 6px; font-size: 13px; }
.od-alert--error { border-color: #fecaca; background: #fff7f7; color: #b91c1c; }
.od-link-button { margin-left: auto; border: 0; background: none; color: inherit; font-size: 12px; font-weight: 600; cursor: pointer; }
.od-section, .od-panel, .od-empty-state { border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; box-shadow: 0 5px 18px rgba(15,23,42,.035); }
.od-section { padding: 18px; }
.od-section-heading, .od-panel-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.od-section-heading h2, .od-panel-heading h2 { margin: 3px 0 0; color: #0f172a; font-size: 15px; font-weight: 650; }
.od-updated, .od-chart-total { color: #64748b; font-size: 11px; }
.od-attention-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
.od-attention-item { display: flex; align-items: center; gap: 10px; min-height: 64px; padding: 11px 12px; border: 1px solid #e2e8f0; border-radius: 7px; color: #1e293b; text-decoration: none; transition: border-color .16s ease, transform .16s ease, background .16s ease; }
.od-attention-item:hover { transform: translateY(-1px); border-color: #94a3b8; background: #f8fafc; }
.od-attention-icon { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 6px; background: #f1f5f9; color: #475569; }
.od-attention-copy { display: grid; flex: 1; gap: 2px; }
.od-attention-copy strong { color: #0f172a; font-size: 18px; line-height: 1; }
.od-attention-copy span { color: #64748b; font-size: 11px; }
.od-attention-item--warning { border-color: #fde68a; background: #fffbeb; }
.od-attention-item--warning .od-attention-icon { color: #b45309; background: #fef3c7; }
.od-attention-item--danger { border-color: #fecaca; background: #fff7f7; }
.od-attention-item--danger .od-attention-icon { color: #b91c1c; background: #fee2e2; }
.od-attention-item--info { border-color: #bfdbfe; background: #eff6ff; }
.od-attention-item--info .od-attention-icon { color: #1d4ed8; background: #dbeafe; }
.od-empty-state { display: flex; align-items: center; gap: 14px; padding: 24px; }
.od-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 8px; background: #f0fdf4; color: #15803d; }
.od-empty-state h2 { margin: 0; font-size: 16px; }
.od-empty-state p { margin: 4px 0 0; color: #64748b; font-size: 13px; }
.od-primary-button { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; padding: 0 14px; border: 1px solid #15803d; border-radius: 6px; background: #15803d; color: #fff; font-size: 12px; font-weight: 650; text-decoration: none; white-space: nowrap; }
.od-primary-button:hover { border-color: #166534; background: #166534; }
.od-primary-button--full { width: 100%; margin-top: 14px; }
.od-kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.od-kpi-card { min-height: 118px; padding: 15px 16px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; box-shadow: 0 5px 18px rgba(15,23,42,.035); }
.od-kpi-topline { display: flex; align-items: center; justify-content: space-between; color: #64748b; font-size: 11px; }
.od-kpi-icon { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: #f0fdf4; color: #15803d; }
.od-kpi-card > strong { display: block; margin-top: 13px; color: #0f172a; font-size: 21px; font-weight: 650; }
.od-kpi-card > small { display: block; margin-top: 5px; color: #64748b; font-size: 11px; }
.od-main-grid { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(260px, .75fr); gap: 16px; align-items: start; }
.od-panel { min-width: 0; padding: 18px; }
.od-text-link { color: #15803d; font-size: 12px; font-weight: 650; text-decoration: none; white-space: nowrap; }
.od-text-link:hover { color: #166534; text-decoration: underline; }
.od-today-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 16px 0; padding-bottom: 15px; border-bottom: 1px solid #eef2f7; }
.od-today-summary div { display: grid; gap: 5px; }
.od-today-summary span { color: #64748b; font-size: 11px; }
.od-today-summary strong { color: #0f172a; font-size: 16px; font-weight: 650; }
.od-value-warning { color: #b45309 !important; }
.od-value-success { color: #15803d !important; }
.od-loading-state, .od-chart-empty, .od-table-empty { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 110px; color: #64748b; font-size: 12px; }
.od-booking-list { display: grid; }
.od-booking-row { display: grid; grid-template-columns: 92px minmax(0, 1fr) auto 16px; align-items: center; gap: 12px; min-height: 58px; border-bottom: 1px solid #eef2f7; color: #1e293b; text-decoration: none; }
.od-booking-row:last-child { border-bottom: 0; }
.od-booking-row:hover { background: #f8fafc; }
.od-booking-row time { color: #0f172a; font-size: 12px; font-weight: 650; }
.od-booking-main { display: grid; min-width: 0; gap: 3px; }
.od-booking-main strong { overflow: hidden; color: #0f172a; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.od-booking-main small, .od-booking-meta small { color: #64748b; font-size: 11px; }
.od-booking-meta { display: grid; justify-items: end; gap: 4px; }
.od-status { display: inline-flex; align-items: center; min-height: 22px; padding: 0 7px; border: 1px solid; border-radius: 999px; font-size: 10px; font-weight: 650; white-space: nowrap; }
.od-status--success { border-color: #bbf7d0; background: #f0fdf4; color: #15803d; }
.od-status--info { border-color: #bfdbfe; background: #eff6ff; color: #1d4ed8; }
.od-status--warning { border-color: #fde68a; background: #fffbeb; color: #b45309; }
.od-status--danger { border-color: #fecaca; background: #fff7f7; color: #b91c1c; }
.od-side-column { display: grid; align-content: start; gap: 16px; }
.od-wallet-panel { background: #f8fcf9; border-color: #bbf7d0; }
.od-wallet-value { display: block; margin: 22px 0 15px; color: #15803d; font-size: 25px; font-weight: 700; }
.od-wallet-details { display: grid; gap: 7px; color: #64748b; font-size: 11px; }
.od-wallet-details span { display: flex; justify-content: space-between; gap: 10px; }
.od-wallet-details strong { color: #334155; font-weight: 650; }
.od-health-total { display: flex; align-items: baseline; gap: 6px; margin: 20px 0 13px; }
.od-health-total strong { color: #0f172a; font-size: 28px; }
.od-health-total span { color: #64748b; font-size: 12px; }
.od-health-list { display: grid; gap: 10px; }
.od-health-list div { display: flex; justify-content: space-between; color: #475569; font-size: 12px; }
.od-health-list strong { color: #0f172a; }
.od-dot { display: inline-block; width: 7px; height: 7px; margin-right: 7px; border-radius: 50%; }
.od-dot--success { background: #16a34a; }.od-dot--warning { background: #d97706; }.od-dot--muted { background: #94a3b8; }
.od-chart-grid, .od-insight-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.od-chart-panel { min-height: 265px; }
.od-bar-chart { display: flex; align-items: flex-end; gap: 7px; height: 178px; margin-top: 20px; padding: 0 2px 0 4px; border-bottom: 1px solid #cbd5e1; }
.od-bar-column { display: flex; flex: 1; align-items: center; justify-content: flex-end; min-width: 0; height: 100%; flex-direction: column; gap: 5px; }
.od-bar-value { overflow: hidden; max-width: 100%; color: #64748b; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }
.od-bar-track { display: flex; align-items: flex-end; width: 100%; max-width: 27px; height: 125px; border-radius: 4px 4px 0 0; background: #f1f5f9; }
.od-bar-fill { display: block; width: 100%; min-height: 3px; border-radius: 4px 4px 0 0; background: #34d399; transition: height .3s ease; }
.od-bar-column small { overflow: hidden; max-width: 100%; color: #64748b; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }
.od-status-chart { display: grid; gap: 18px; margin-top: 26px; }
.od-status-row { display: grid; grid-template-columns: 100px minmax(0, 1fr) 30px; align-items: center; gap: 10px; color: #475569; font-size: 11px; }
.od-status-row strong { color: #0f172a; text-align: right; }
.od-status-track, .od-ranking-bar { display: block; height: 8px; overflow: hidden; border-radius: 99px; background: #f1f5f9; }
.od-status-fill { display: block; height: 100%; border-radius: inherit; background: #94a3b8; }.od-status-fill--pending { background: #f59e0b; }.od-status-fill--confirmed { background: #60a5fa; }.od-status-fill--playing { background: #22c55e; }.od-status-fill--completed { background: #15803d; }.od-status-fill--cancelled { background: #f87171; }
.od-ranking-list { display: grid; gap: 14px; margin-top: 20px; }
.od-ranking-list > div { display: grid; grid-template-columns: 22px minmax(105px, 1fr) minmax(70px, 1.2fr) auto; align-items: center; gap: 9px; color: #475569; font-size: 11px; }
.od-rank { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 5px; background: #f1f5f9; color: #475569; font-size: 11px; font-weight: 650; }
.od-ranking-name { overflow: hidden; color: #1e293b; text-overflow: ellipsis; white-space: nowrap; }
.od-ranking-bar { height: 6px; }.od-ranking-bar i { display: block; height: 100%; border-radius: inherit; background: #34d399; }
.od-ranking-list strong { color: #0f172a; font-size: 11px; text-align: right; white-space: nowrap; }
.od-recent-panel { overflow: hidden; }
.od-recent-grid { display: grid; margin-top: 15px; }
.od-recent-row { display: grid; grid-template-columns: 100px minmax(120px, 1fr) minmax(120px, 1fr) 155px 110px 110px; align-items: center; gap: 12px; min-height: 47px; border-top: 1px solid #eef2f7; color: #475569; font-size: 11px; text-decoration: none; }
.od-recent-row:hover { background: #f8fafc; }
.od-recent-row > strong { color: #0f172a; font-size: 11px; }.od-recent-amount { text-align: right; }
@media (max-width: 1120px) { .od-attention-grid { grid-template-columns: repeat(2, 1fr); }.od-main-grid { grid-template-columns: 1fr; }.od-side-column { grid-template-columns: repeat(2, minmax(0, 1fr)); }.od-recent-row { grid-template-columns: 100px minmax(120px, 1fr) 110px 110px; }.od-recent-row > span:nth-child(3) { display: none; } }
@media (max-width: 760px) { .od-hero { align-items: flex-start; flex-direction: column; }.od-controls { justify-content: flex-start; }.od-kpi-grid { grid-template-columns: repeat(2, 1fr); }.od-chart-grid, .od-insight-grid { grid-template-columns: 1fr; }.od-recent-row { grid-template-columns: 92px minmax(0, 1fr) 100px; }.od-recent-row > span:nth-child(4), .od-recent-row > .od-status { display: none; } }
@media (max-width: 520px) { .od-section, .od-panel, .od-empty-state { padding: 14px; }.od-attention-grid, .od-kpi-grid, .od-side-column { grid-template-columns: 1fr; }.od-today-summary { grid-template-columns: repeat(2, 1fr); row-gap: 14px; }.od-booking-row { grid-template-columns: 70px minmax(0, 1fr) 16px; }.od-booking-meta { display: none; }.od-date-range { width: 100%; }.od-date-range input { flex: 1; min-width: 0; }.od-empty-state { align-items: flex-start; flex-wrap: wrap; }.od-empty-state .od-primary-button { width: 100%; }.od-ranking-list > div { grid-template-columns: 22px minmax(80px, 1fr) 80px; }.od-ranking-bar { display: none; }.od-recent-row { grid-template-columns: 86px minmax(0, 1fr); }.od-recent-row > span, .od-recent-row > .od-status, .od-recent-row > strong:last-child { display: none; } }
</style>
