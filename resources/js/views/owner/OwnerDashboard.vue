<template>
  <section class="owner-dashboard" aria-labelledby="owner-dashboard-title">
    <header class="dashboard-command">
      <div class="dashboard-command__intro">
        <p class="dashboard-eyebrow">{{ greeting }}</p>
        <h1 id="owner-dashboard-title">Tình hình sân hôm nay</h1>
        <p>
          {{ selectedCluster?.name || 'Toàn bộ cụm sân' }}
          <span aria-hidden="true">·</span>
          {{ formatLongDate(new Date()) }}
        </p>
      </div>

      <div class="dashboard-command__actions">
        <router-link class="dashboard-button dashboard-button--secondary" to="/owner/counter-booking">
          <AppIcon name="plus" :size="17" />
          Tạo booking
        </router-link>
        <button
          class="dashboard-icon-button"
          type="button"
          aria-label="Tải lại dữ liệu dashboard"
          :disabled="isLoading"
          @click="loadStats"
        >
          <AppIcon name="refresh" :size="18" />
        </button>
      </div>
    </header>

    <div class="dashboard-filter" aria-label="Lọc thời gian thống kê">
      <div class="period-options">
        <button
          v-for="option in periodOptions"
          :key="option.value"
          type="button"
          :class="{ active: periodKey === option.value }"
          @click="selectPeriod(option.value)"
        >
          {{ option.label }}
        </button>
      </div>
      <div v-if="periodKey === 'custom'" class="custom-range">
        <label>
          <span>Từ ngày</span>
          <input v-model="customDateFrom" type="date" :max="customDateTo || today" />
        </label>
        <label>
          <span>Đến ngày</span>
          <input v-model="customDateTo" type="date" :min="customDateFrom" :max="today" />
        </label>
        <button
          class="dashboard-button dashboard-button--primary"
          type="button"
          :disabled="!customRangeValid || isLoading"
          @click="loadStats"
        >
          Áp dụng
        </button>
      </div>
      <p class="period-caption">
        Số liệu thống kê:
        <strong>{{ periodCaption }}</strong>
      </p>
    </div>

    <div v-if="error" class="dashboard-alert" role="alert">
      <AppIcon name="alert" :size="19" />
      <span>{{ error }}</span>
      <button type="button" @click="loadStats">Thử lại</button>
    </div>

    <div v-if="isLoading" class="dashboard-skeleton" aria-label="Đang tải dashboard">
      <span v-for="item in 8" :key="item"></span>
    </div>

    <template v-else>
      <div class="dashboard-hero-grid">
        <section class="period-section" aria-labelledby="period-summary-title">
          <div class="section-heading">
            <div>
              <p class="section-kicker">Tổng quan kỳ</p>
              <h2 id="period-summary-title">{{ stats.period.label }}</h2>
            </div>
            <p>{{ formatDateRange(stats.period.date_from, stats.period.date_to) }}</p>
          </div>

          <div class="metric-grid">
            <article v-for="metric in periodMetrics" :key="metric.key" class="metric-card">
              <span class="metric-card__icon"><AppIcon :name="metric.icon" :size="18" /></span>
              <div>
                <p>{{ metric.label }}</p>
                <strong>{{ metric.value }}</strong>
                <small>{{ metric.note }}</small>
              </div>
            </article>
          </div>
        </section>

        <section class="attention-section" aria-labelledby="attention-title">
          <div class="section-heading">
            <div>
              <p class="section-kicker">Cần xử lý</p>
              <h2 id="attention-title">Việc đang chờ bạn</h2>
            </div>
          </div>

          <div class="attention-grid">
            <router-link
              v-for="item in attentionItems"
              :key="item.key"
              class="attention-item"
              :class="`attention-item--${item.tone}`"
              :to="item.to"
            >
              <span class="attention-item__icon">
                <AppIcon :name="item.icon" :size="19" />
              </span>
              <span>
                <strong>{{ formatNumber(item.value) }}</strong>
                <small>{{ item.label }}</small>
              </span>
              <AppIcon name="chevronRight" :size="17" />
            </router-link>
          </div>
        </section>
      </div>

      <div class="analytics-grid analytics-grid--hero">
        <section class="dashboard-panel trend-panel" aria-labelledby="revenue-trend-title">
          <div class="panel-heading">
            <div>
              <p class="section-kicker">Doanh thu & booking</p>
              <h2 id="revenue-trend-title">Diễn biến theo thời gian</h2>
            </div>
            <strong>{{ formatCurrency(stats.period_summary.revenue) }}</strong>
          </div>

          <div v-if="trendHasData" class="trend-chart" role="img" :aria-label="trendAriaLabel">
            <div
              v-for="point in trendBars"
              :key="`${point.date_from}-${point.date_to}`"
              class="trend-column"
              :title="`${point.label}: ${formatCurrency(point.revenue)} · ${point.bookings} booking`"
            >
              <span class="trend-column__value">{{ compactCurrency(point.revenue) }}</span>
              <div class="trend-column__track">
                <i :style="{ height: `${trendHeight(point.revenue)}%` }"></i>
              </div>
              <small>{{ point.label }}</small>
            </div>
          </div>
          <div v-else class="dashboard-empty">
            <AppIcon name="dashboard" :size="24" />
            <span>Chưa có doanh thu trong khoảng này.</span>
          </div>
        </section>

        <section class="dashboard-panel status-panel" aria-labelledby="booking-status-title">
          <div class="panel-heading">
            <div>
              <p class="section-kicker">Cơ cấu booking</p>
              <h2 id="booking-status-title">Theo trạng thái</h2>
            </div>
            <strong>{{ formatNumber(stats.period_summary.bookings) }} lịch</strong>
          </div>
          <div class="status-bars">
            <div v-for="status in stats.booking_statuses" :key="status.key" class="status-row">
              <div>
                <span><i :class="`status-dot status-dot--${status.key}`"></i>{{ status.label }}</span>
                <strong>{{ formatNumber(status.count) }}</strong>
              </div>
              <span class="status-row__track">
                <i :class="`status-fill--${status.key}`" :style="{ width: `${statusPercent(status.count)}%` }"></i>
              </span>
            </div>
          </div>
          <div class="source-split">
            <div><span>Online</span><strong>{{ stats.period_summary.online_bookings }}</strong></div>
            <div><span>Tại quầy</span><strong>{{ stats.period_summary.counter_bookings }}</strong></div>
          </div>
        </section>
      </div>

      <div class="today-grid">
        <section class="dashboard-panel today-panel" aria-labelledby="today-schedule-title">
          <div class="panel-heading">
            <div>
              <p class="section-kicker">Vận hành trong ngày</p>
              <h2 id="today-schedule-title">Lịch sân hôm nay</h2>
            </div>
            <router-link to="/owner/booking-list">Xem tất cả</router-link>
          </div>

          <div class="today-summary">
            <div>
              <span>Tổng lịch</span>
              <strong>{{ formatNumber(stats.today_booking_summary.total) }}</strong>
            </div>
            <div>
              <span>Đã thu</span>
              <strong>{{ formatCurrency(stats.today_booking_summary.revenue) }}</strong>
            </div>
            <div>
              <span>Chờ xử lý</span>
              <strong>{{ formatNumber(todayPendingCount) }}</strong>
            </div>
            <div>
              <span>Đã hủy</span>
              <strong>{{ formatNumber(stats.today_booking_summary.cancelled) }}</strong>
            </div>
          </div>

          <div v-if="todayBookings.length" class="today-booking-list">
            <article v-for="booking in todayBookings" :key="booking.id" class="today-booking">
              <time>{{ booking.time_label }}</time>
              <div class="today-booking__main">
                <strong>{{ booking.court_label }}</strong>
                <span>{{ booking.customer_name }} · {{ booking.source_label }}</span>
              </div>
              <div class="today-booking__state">
                <span class="status-chip" :class="statusTone(booking.status)">
                  {{ booking.status_label }}
                </span>
                <small>{{ booking.payment_state_label }}</small>
              </div>
            </article>
          </div>
          <div v-else class="dashboard-empty dashboard-empty--compact">
            <AppIcon name="calendar" :size="23" />
            <span>Hôm nay chưa có booking.</span>
          </div>
        </section>

        <aside class="dashboard-side-stack">
          <section class="wallet-panel" aria-labelledby="wallet-title">
            <div class="wallet-panel__head">
              <div>
                <p>Số dư khả dụng</p>
                <h2 id="wallet-title">{{ formatCurrency(stats.wallet.available_balance) }}</h2>
              </div>
              <AppIcon name="banknote" :size="25" />
            </div>
            <dl>
              <div>
                <dt>Đang chờ rút</dt>
                <dd>{{ formatCurrency(stats.wallet.pending_withdrawal_balance) }}</dd>
              </div>
              <div>
                <dt>Tổng đã kiếm</dt>
                <dd>{{ formatCurrency(stats.wallet.total_earned) }}</dd>
              </div>
            </dl>
            <router-link to="/owner/finance">
              Quản lý tài chính
              <AppIcon name="chevronRight" :size="16" />
            </router-link>
          </section>

          <section class="dashboard-panel court-health" aria-labelledby="court-health-title">
            <div class="panel-heading panel-heading--compact">
              <div>
                <p class="section-kicker">Tình trạng sân</p>
                <h2 id="court-health-title">{{ stats.court_statuses.total }} sân con</h2>
              </div>
              <router-link to="/owner/venue-courts">Quản lý</router-link>
            </div>
            <div class="court-health__rows">
              <div><span><i class="dot dot--active"></i>Đang hoạt động</span><strong>{{ stats.court_statuses.active }}</strong></div>
              <div><span><i class="dot dot--maintenance"></i>Bảo trì</span><strong>{{ stats.court_statuses.maintenance }}</strong></div>
              <div><span><i class="dot dot--inactive"></i>Tạm ngưng</span><strong>{{ stats.court_statuses.inactive }}</strong></div>
            </div>
          </section>
        </aside>
      </div>

      <section class="dashboard-panel recent-panel" aria-labelledby="recent-bookings-title">
        <div class="panel-heading">
          <div>
            <p class="section-kicker">Chi tiết kỳ thống kê</p>
            <h2 id="recent-bookings-title">Booking gần nhất</h2>
          </div>
          <router-link to="/owner/booking-list">Danh sách booking</router-link>
        </div>
        <div v-if="stats.recent_bookings.length" class="recent-booking-list">
          <router-link
            v-for="booking in stats.recent_bookings"
            :key="booking.id"
            class="recent-booking"
            :to="`/owner/booking-list?keyword=${encodeURIComponent(booking.booking_code)}`"
          >
            <div class="recent-booking__identity">
              <strong>{{ booking.booking_code }}</strong>
              <span>{{ booking.customer_name }}</span>
            </div>
            <div>
              <strong>{{ booking.court_label }}</strong>
              <span>{{ formatDate(booking.booking_date) }} · {{ booking.time_label }}</span>
            </div>
            <div class="recent-booking__amount">
              <strong>{{ formatCurrency(booking.total_price) }}</strong>
              <span>{{ booking.payment_state_label }}</span>
            </div>
            <span class="status-chip" :class="statusTone(booking.status)">{{ booking.status_label }}</span>
            <AppIcon name="chevronRight" :size="17" />
          </router-link>
        </div>
        <div v-else class="dashboard-empty">
          <AppIcon name="calendar" :size="24" />
          <span>Không có booking trong khoảng đã chọn.</span>
        </div>
      </section>

      <div class="finance-grid">
        <section class="dashboard-panel queue-panel" aria-labelledby="refund-queue-title">
          <div class="panel-heading">
            <div>
              <p class="section-kicker">Hoàn / hủy</p>
              <h2 id="refund-queue-title">Yêu cầu gần đây</h2>
            </div>
            <router-link to="/owner/refunds">Xử lý yêu cầu</router-link>
          </div>
          <div v-if="stats.operations.latest_refunds.length" class="queue-list">
            <router-link
              v-for="refund in stats.operations.latest_refunds"
              :key="refund.id"
              to="/owner/refunds"
              class="queue-row"
            >
              <span><strong>{{ refund.booking_code }}</strong><small>{{ formatDateTime(refund.created_at) }}</small></span>
              <span><strong>{{ formatCurrency(refund.amount) }}</strong><small>{{ refund.status_label }}</small></span>
            </router-link>
          </div>
          <div v-else class="dashboard-empty dashboard-empty--compact">Chưa có yêu cầu hoàn tiền.</div>
        </section>

        <section class="dashboard-panel queue-panel" aria-labelledby="withdrawal-queue-title">
          <div class="panel-heading">
            <div>
              <p class="section-kicker">Rút tiền</p>
              <h2 id="withdrawal-queue-title">Giao dịch gần đây</h2>
            </div>
            <router-link to="/owner/finance">Mở tài chính</router-link>
          </div>
          <div v-if="stats.operations.latest_withdrawals.length" class="queue-list">
            <router-link
              v-for="withdrawal in stats.operations.latest_withdrawals"
              :key="withdrawal.id"
              to="/owner/finance"
              class="queue-row"
            >
              <span><strong>{{ withdrawal.request_code }}</strong><small>{{ formatDateTime(withdrawal.requested_at) }}</small></span>
              <span><strong>{{ formatCurrency(withdrawal.amount) }}</strong><small>{{ withdrawal.status_label }}</small></span>
            </router-link>
          </div>
          <div v-else class="dashboard-empty dashboard-empty--compact">Chưa có yêu cầu rút tiền.</div>
        </section>
      </div>

      <div class="performance-grid">
        <section class="dashboard-panel performance-panel" aria-labelledby="court-revenue-title">
          <div class="panel-heading">
            <div>
              <p class="section-kicker">Hiệu quả sân</p>
              <h2 id="court-revenue-title">Doanh thu theo sân con</h2>
            </div>
          </div>
          <div v-if="stats.court_revenues.length" class="ranking-list">
            <div v-for="(court, index) in stats.court_revenues.slice(0, 6)" :key="court.court_name">
              <span class="ranking-index">{{ index + 1 }}</span>
              <strong>{{ court.court_name }}</strong>
              <span>{{ formatCurrency(court.revenue) }}</span>
            </div>
          </div>
          <div v-else class="dashboard-empty dashboard-empty--compact">Chưa có doanh thu theo sân.</div>
        </section>

        <section class="dashboard-panel performance-panel" aria-labelledby="golden-hour-title">
          <div class="panel-heading">
            <div>
              <p class="section-kicker">Nhu cầu đặt sân</p>
              <h2 id="golden-hour-title">Khung giờ phổ biến</h2>
            </div>
          </div>
          <div v-if="stats.golden_hours.length" class="ranking-list">
            <div v-for="(slot, index) in stats.golden_hours" :key="slot.time_slot">
              <span class="ranking-index">{{ index + 1 }}</span>
              <strong>{{ slot.time_slot }}</strong>
              <span>{{ slot.count }} lượt</span>
            </div>
          </div>
          <div v-else class="dashboard-empty dashboard-empty--compact">Chưa có dữ liệu khung giờ.</div>
        </section>
      </div>
    </template>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { api } from '../../services/api.js';
import { venueClusterService } from '../../services/venueClusters.js';

const emptyStats = () => ({
  bookings: 0,
  revenue: 0,
  rating: 0,
  period: { key: 'today', label: 'Hôm nay', date_from: '', date_to: '' },
  period_summary: {
    bookings: 0,
    revenue: 0,
    average_booking_value: 0,
    completed: 0,
    cancelled: 0,
    online_bookings: 0,
    counter_bookings: 0,
  },
  booking_statuses: [],
  revenue_trend: [],
  operations: {
    pending_bookings: 0,
    pending_refunds: 0,
    pending_refund_amount: 0,
    pending_withdrawals: 0,
    pending_withdrawal_amount: 0,
    open_complaints: 0,
    latest_refunds: [],
    latest_withdrawals: [],
  },
  recent_bookings: [],
  court_statuses: { total: 0, active: 0, maintenance: 0, inactive: 0 },
  wallet: {
    available_balance: 0,
    pending_withdrawal_balance: 0,
    total_earned: 0,
    total_withdrawn: 0,
  },
  today_booking_summary: {
    date: null,
    total: 0,
    pending_approval: 0,
    pending_payment: 0,
    paid: 0,
    cancelled: 0,
    revenue: 0,
  },
  today_bookings: [],
  pending_bookings: [],
  cancelled_today: [],
  golden_hours: [],
  court_revenues: [],
});

export default {
  name: 'OwnerDashboard',
  components: { AppIcon },
  data() {
    return {
      selectedCluster: null,
      stats: emptyStats(),
      periodKey: 'today',
      customDateFrom: '',
      customDateTo: '',
      isLoading: true,
      error: '',
      periodOptions: [
        { value: 'today', label: 'Hôm nay' },
        { value: '7_days', label: '7 ngày' },
        { value: '30_days', label: '30 ngày' },
        { value: 'this_month', label: 'Tháng này' },
        { value: 'custom', label: 'Tùy chọn' },
      ],
    };
  },
  computed: {
    today() {
      return this.localDateString();
    },
    greeting() {
      const hour = new Date().getHours();
      if (hour < 11) return 'Chào buổi sáng';
      if (hour < 18) return 'Chào buổi chiều';
      return 'Chào buổi tối';
    },
    todayBookings() {
      return this.stats.today_bookings || [];
    },
    todayPendingCount() {
      return Number(this.stats.today_booking_summary.pending_approval || 0)
        + Number(this.stats.today_booking_summary.pending_payment || 0);
    },
    customRangeValid() {
      return Boolean(
        this.customDateFrom
        && this.customDateTo
        && this.customDateFrom <= this.customDateTo
        && this.customDateTo <= this.today,
      );
    },
    periodCaption() {
      if (!this.stats.period?.date_from) return 'Đang cập nhật';
      return `${this.stats.period.label} · ${this.formatDateRange(
        this.stats.period.date_from,
        this.stats.period.date_to,
      )}`;
    },
    attentionItems() {
      const operations = this.stats.operations;
      return [
        {
          key: 'bookings',
          label: 'Booking cần xử lý',
          value: operations.pending_bookings,
          icon: 'calendar',
          tone: operations.pending_bookings ? 'warning' : 'neutral',
          to: '/owner/booking-list?status=pending',
        },
        {
          key: 'refunds',
          label: 'Yêu cầu hoàn tiền',
          value: operations.pending_refunds,
          icon: 'refresh',
          tone: operations.pending_refunds ? 'danger' : 'neutral',
          to: '/owner/refunds?status=pending_owner_confirmation',
        },
        {
          key: 'withdrawals',
          label: 'Lệnh rút đang xử lý',
          value: operations.pending_withdrawals,
          icon: 'banknote',
          tone: operations.pending_withdrawals ? 'info' : 'neutral',
          to: '/owner/finance',
        },
        {
          key: 'complaints',
          label: 'Khiếu nại đang mở',
          value: operations.open_complaints,
          icon: 'alert',
          tone: operations.open_complaints ? 'danger' : 'neutral',
          to: '/owner/complaints?status=open',
        },
      ];
    },
    periodMetrics() {
      const summary = this.stats.period_summary;
      const completionRate = summary.bookings
        ? Math.round((summary.completed / summary.bookings) * 100)
        : 0;
      return [
        {
          key: 'bookings',
          label: 'Tổng booking',
          value: this.formatNumber(summary.bookings),
          note: `${summary.online_bookings} online · ${summary.counter_bookings} tại quầy`,
          icon: 'calendar',
        },
        {
          key: 'revenue',
          label: 'Doanh thu đã thu',
          value: this.formatCurrency(summary.revenue),
          note: 'Từ các giao dịch đã thanh toán',
          icon: 'banknote',
        },
        {
          key: 'average',
          label: 'Giá trị trung bình',
          value: this.formatCurrency(summary.average_booking_value),
          note: 'Trên mỗi booking trong kỳ',
          icon: 'dashboard',
        },
        {
          key: 'completion',
          label: 'Tỷ lệ hoàn thành',
          value: `${completionRate}%`,
          note: `${summary.completed} hoàn thành · ${summary.cancelled} hủy`,
          icon: 'court',
        },
      ];
    },
    trendBars() {
      const rows = this.stats.revenue_trend || [];
      if (rows.length <= 18) {
        return rows.map((row) => ({
          ...row,
          date_from: row.date,
          date_to: row.date,
        }));
      }

      const chunkSize = Math.ceil(rows.length / 18);
      const grouped = [];
      for (let index = 0; index < rows.length; index += chunkSize) {
        const chunk = rows.slice(index, index + chunkSize);
        grouped.push({
          date_from: chunk[0].date,
          date_to: chunk.at(-1).date,
          label: chunk.length === 1 ? chunk[0].label : `${chunk[0].label}–${chunk.at(-1).label}`,
          bookings: chunk.reduce((sum, row) => sum + Number(row.bookings || 0), 0),
          revenue: chunk.reduce((sum, row) => sum + Number(row.revenue || 0), 0),
        });
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
      return `Biểu đồ doanh thu ${this.stats.period.label}, tổng ${this.formatCurrency(
        this.stats.period_summary.revenue,
      )}`;
    },
  },
  async mounted() {
    window.addEventListener('owner-cluster-changed', this.handleClusterChange);
    await this.loadStats();
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
        const response = await venueClusterService.getClusters();
        const clusters = response.data || [];
        this.selectedCluster = clusters.find((cluster) => String(cluster.id) === String(clusterId)) || null;
      } catch {
        this.selectedCluster = null;
      }
    },
    async loadStats() {
      if (this.periodKey === 'custom' && !this.customRangeValid) return;
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
        this.stats = {
          ...emptyStats(),
          ...response,
          period_summary: { ...emptyStats().period_summary, ...(response.period_summary || {}) },
          operations: { ...emptyStats().operations, ...(response.operations || {}) },
          court_statuses: { ...emptyStats().court_statuses, ...(response.court_statuses || {}) },
          wallet: { ...emptyStats().wallet, ...(response.wallet || {}) },
          today_booking_summary: {
            ...emptyStats().today_booking_summary,
            ...(response.today_booking_summary || {}),
          },
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
    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
      }).format(Number(amount || 0));
    },
    compactCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', {
        notation: 'compact',
        maximumFractionDigits: 1,
      }).format(Number(amount || 0));
    },
    formatNumber(value) {
      return Number(value || 0).toLocaleString('vi-VN');
    },
    formatDate(value) {
      if (!value) return 'Chưa rõ ngày';
      return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      }).format(new Date(`${String(value).slice(0, 10)}T00:00:00`));
    },
    formatDateTime(value) {
      if (!value) return 'Chưa rõ thời gian';
      return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      }).format(new Date(value));
    },
    formatLongDate(value) {
      return new Intl.DateTimeFormat('vi-VN', {
        weekday: 'long',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      }).format(value);
    },
    formatDateRange(from, to) {
      if (!from || !to) return 'Chưa chọn khoảng';
      if (from === to) return this.formatDate(from);
      return `${this.formatDate(from)} – ${this.formatDate(to)}`;
    },
    trendHeight(revenue) {
      if (!this.maxTrendRevenue || !revenue) return 0;
      return Math.max(8, Math.round((Number(revenue) / this.maxTrendRevenue) * 100));
    },
    statusPercent(count) {
      const total = Number(this.stats.period_summary.bookings || 0);
      if (!total || !count) return 0;
      return Math.max(3, Math.round((Number(count) / total) * 100));
    },
    statusTone(status) {
      if (['confirmed', 'completed'].includes(status)) return 'status-chip--success';
      if (status === 'checked_in') return 'status-chip--info';
      if (['cancelled', 'rejected', 'expired'].includes(status)) return 'status-chip--danger';
      return 'status-chip--warning';
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

