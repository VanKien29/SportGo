<template>
  <div class="w-full space-y-6">
    <div v-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 text-xs rounded-none">
      {{ error }}
    </div>

    <!-- Top KPI Grid: 4 Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Số dư khả dụng -->
      <div class="bg-white border border-slate-200 p-4 flex flex-col justify-between shadow-xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-normal text-slate-500">Số dư khả dụng</span>
          <span class="p-1.5 bg-emerald-50 text-emerald-600 rounded-none">
            <AppIcon name="wallet" size="16" />
          </span>
        </div>
        <div class="mt-3 text-xl font-semibold text-emerald-600">
          {{ isLoading ? '...' : formatCurrency(stats.wallet.available_balance) }}
        </div>
      </div>

      <!-- Doanh thu online -->
      <div class="bg-white border border-slate-200 p-4 flex flex-col justify-between shadow-xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-normal text-slate-500">Doanh thu online</span>
          <span class="p-1.5 bg-slate-100 text-slate-600 rounded-none">
            <AppIcon name="trending-up" size="16" />
          </span>
        </div>
        <div class="mt-3 text-xl font-semibold text-slate-900">
          {{ isLoading ? '...' : formatCurrency(stats.revenue) }}
        </div>
      </div>

      <!-- Tổng lượt đặt -->
      <div class="bg-white border border-slate-200 p-4 flex flex-col justify-between shadow-xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-normal text-slate-500">Tổng lượt đặt</span>
          <span class="p-1.5 bg-slate-100 text-slate-600 rounded-none">
            <AppIcon name="calendar" size="16" />
          </span>
        </div>
        <div class="mt-3 text-xl font-semibold text-slate-900">
          {{ isLoading ? '...' : stats.bookings.toLocaleString() }}
        </div>
      </div>

      <!-- Đánh giá trung bình -->
      <div class="bg-white border border-slate-200 p-4 flex flex-col justify-between shadow-xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-normal text-slate-500">Đánh giá trung bình</span>
          <span class="p-1.5 bg-amber-50 text-amber-500 rounded-none">
            <AppIcon name="star" size="16" />
          </span>
        </div>
        <div class="mt-3 text-xl font-semibold text-slate-900">
          {{ isLoading ? '...' : stats.rating }} <span class="text-xs text-slate-400 font-normal">/ 5.0</span>
        </div>
      </div>
    </div>

    <!-- Section: Today Summary Cards -->
    <div class="bg-white border border-slate-200 p-5 space-y-4 shadow-xs">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <h3 class="text-xs font-medium uppercase tracking-wider text-slate-800">Thống kê lịch hôm nay</h3>
        <router-link to="/owner/booking-list" class="text-xs text-emerald-600 hover:text-emerald-700 font-normal">
          Xem tất cả lịch &rarr;
        </router-link>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-slate-50/70 border border-slate-200/80 p-3">
          <div class="text-[11px] text-slate-500">Tổng lịch</div>
          <div class="text-lg font-medium text-slate-900 mt-1">{{ isLoading ? '...' : stats.today_booking_summary.total }}</div>
        </div>
        <div class="bg-slate-50/70 border border-slate-200/80 p-3">
          <div class="text-[11px] text-amber-600">Chờ xác nhận</div>
          <div class="text-lg font-medium text-slate-900 mt-1">{{ isLoading ? '...' : stats.today_booking_summary.pending_approval }}</div>
        </div>
        <div class="bg-slate-50/70 border border-slate-200/80 p-3">
          <div class="text-[11px] text-amber-600">Chờ thanh toán</div>
          <div class="text-lg font-medium text-slate-900 mt-1">{{ isLoading ? '...' : stats.today_booking_summary.pending_payment }}</div>
        </div>
        <div class="bg-slate-50/70 border border-slate-200/80 p-3">
          <div class="text-[11px] text-emerald-600">Đã thanh toán</div>
          <div class="text-lg font-medium text-slate-900 mt-1">{{ isLoading ? '...' : stats.today_booking_summary.paid }}</div>
        </div>
        <div class="bg-slate-50/70 border border-slate-200/80 p-3">
          <div class="text-[11px] text-rose-600">Đã hủy</div>
          <div class="text-lg font-medium text-slate-900 mt-1">{{ isLoading ? '...' : stats.today_booking_summary.cancelled }}</div>
        </div>
        <div class="bg-emerald-50/60 border border-emerald-200/80 p-3">
          <div class="text-[11px] text-emerald-700">Doanh thu hôm nay</div>
          <div class="text-base font-semibold text-emerald-700 mt-1">{{ isLoading ? '...' : formatCurrency(stats.today_booking_summary.revenue) }}</div>
        </div>
      </div>
    </div>

    <!-- Section: 2-Column Grid for Bookings & Schedule -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Booking cần chú ý -->
      <div class="bg-white border border-slate-200 p-5 space-y-4 shadow-xs">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="text-xs font-medium uppercase tracking-wider text-slate-800">Booking cần chú ý</h3>
          <span class="text-xs text-slate-400 font-normal">{{ pendingBookings.length }} lịch</span>
        </div>

        <div v-if="isLoading" class="text-center py-6 text-xs text-slate-400">Đang tải lịch chờ xử lý...</div>
        <div v-else-if="pendingBookings.length === 0" class="text-center py-6 text-xs text-slate-400 border border-dashed border-slate-200">
          Không có booking nào đang chờ xử lý.
        </div>
        <div v-else class="divide-y divide-slate-100 border border-slate-200">
          <div v-for="booking in pendingBookings" :key="booking.id" class="p-3 flex items-center justify-between hover:bg-slate-50 transition-colors">
            <div>
              <div class="text-xs font-medium text-slate-900">#{{ booking.booking_code }}</div>
              <div class="text-[11px] text-slate-500 mt-0.5">{{ booking.customer_name }} · {{ booking.court_label }}</div>
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
            <div class="text-right">
              <div class="text-xs font-medium text-emerald-600">{{ formatCurrency(booking.outstanding_amount || booking.total_price) }}</div>
              <div class="text-[10px] text-slate-400 mt-0.5">{{ formatDate(booking.booking_date) }} {{ booking.time_label }}</div>
            </div>
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
      <!-- Lịch trong ngày -->
      <div class="bg-white border border-slate-200 p-5 space-y-4 shadow-xs">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="text-xs font-medium uppercase tracking-wider text-slate-800">Lịch trong ngày</h3>
          <span class="text-xs text-slate-400 font-normal">{{ todayBookings.length }} lịch gần nhất</span>
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

        <div v-if="isLoading" class="text-center py-6 text-xs text-slate-400">Đang tải lịch hôm nay...</div>
        <div v-else-if="todayBookings.length === 0" class="text-center py-6 text-xs text-slate-400 border border-dashed border-slate-200">
          Hôm nay chưa có booking.
        </div>
        <div v-else class="divide-y divide-slate-100 border border-slate-200">
          <div v-for="booking in todayBookings" :key="booking.id" class="p-3 flex items-center justify-between hover:bg-slate-50 transition-colors">
            <div>
              <div class="text-xs font-medium text-slate-900">{{ booking.time_label }}</div>
              <div class="text-[11px] text-slate-500 mt-0.5">{{ booking.court_label }} · {{ booking.customer_name }}</div>
            </div>
            <div class="text-right">
              <span class="text-[11px] px-2 py-0.5 bg-slate-100 text-slate-600 font-normal">{{ booking.status_label }}</span>
            </div>
            <span class="status-chip" :class="statusTone(booking.status)">{{ booking.status_label }}</span>
            <AppIcon name="chevronRight" :size="17" />
          </router-link>
          </div>
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
      </div>
    </div>

    <!-- Section: 2-Column Grid for Revenue by Court & Golden Hours -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Doanh thu theo sân con -->
      <div class="bg-white border border-slate-200 p-5 space-y-4 shadow-xs">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="text-xs font-medium uppercase tracking-wider text-slate-800">Doanh thu theo sân con</h3>
        </div>

        <div v-if="isLoading" class="text-center py-6 text-xs text-slate-400">Đang tải dữ liệu...</div>
        <div v-else-if="!stats.court_revenues || stats.court_revenues.length === 0" class="text-center py-6 text-xs text-slate-400 border border-dashed border-slate-200">
          Không có dữ liệu doanh thu.
        </div>
        <div v-else class="overflow-x-auto border border-slate-200">
          <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-medium">
              <tr>
                <th class="py-2.5 px-3">Tên sân con</th>
                <th class="py-2.5 px-3 text-right">Doanh thu</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-800">
              <tr v-for="(court, idx) in stats.court_revenues" :key="idx" class="hover:bg-slate-50">
                <td class="py-2.5 px-3 font-normal">{{ court.court_name }}</td>
                <td class="py-2.5 px-3 text-right font-medium text-slate-900">{{ formatCurrency(court.revenue) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
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
      <!-- Khung giờ vàng phổ biến -->
      <div class="bg-white border border-slate-200 p-5 space-y-4 shadow-xs">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="text-xs font-medium uppercase tracking-wider text-slate-800">Khung giờ vàng phổ biến</h3>
        </div>

        <div v-if="isLoading" class="text-center py-6 text-xs text-slate-400">Đang tải dữ liệu...</div>
        <div v-else-if="!stats.golden_hours || stats.golden_hours.length === 0" class="text-center py-6 text-xs text-slate-400 border border-dashed border-slate-200">
          Không có dữ liệu khung giờ chơi.
        </div>
        <div v-else class="overflow-x-auto border border-slate-200">
          <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-medium">
              <tr>
                <th class="py-2.5 px-3 w-16">Thứ tự</th>
                <th class="py-2.5 px-3">Khung giờ</th>
                <th class="py-2.5 px-3 text-right">Lượt đặt</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-800">
              <tr v-for="(slot, idx) in stats.golden_hours" :key="idx" class="hover:bg-slate-50">
                <td class="py-2.5 px-3 text-slate-400 font-medium">#{{ idx + 1 }}</td>
                <td class="py-2.5 px-3 font-normal">{{ slot.time_slot }}</td>
                <td class="py-2.5 px-3 text-right font-medium text-slate-900">{{ slot.count }} lượt</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { api } from '../../services/api.js';
import { venueClusterService } from '../../services/venueClusters.js';
import AppIcon from '../../components/AppIcon.vue';

export default {
  name: 'OwnerDashboard',
  components: { AppIcon },
  data() {
    return {
      user: getAuth(),
      selectedCluster: null,
      stats: {
        bookings: 0,
        revenue: 0,
        rating: 0,
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
        published_posts: [],
      },
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
        const response = await venueClusterService.getClusters({ compact: 1 });
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

<style scoped>
.cluster-profile-surface.standalone {
  width: 100%;
}

.profile-section-card.dashboard-main-content {
  background: var(--admin-surface, #ffffff);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 0;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* 1. Overview Bar */
.dash-overview-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 0;
  background: var(--admin-bg-soft, #f7fbf5);
}

@media (max-width: 768px) {
  .dash-overview-bar {
    grid-template-columns: 1fr 1fr;
  }
}

.dash-metric-item {
  padding: 14px 18px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  border-right: 1px solid var(--admin-border-soft, #e2e8f0);
}

.dash-metric-item:last-child {
  border-right: none;
}

.dash-metric-label {
  font-size: 12px;
  color: var(--admin-muted, #64748b);
  font-weight: 400;
}

.dash-metric-val {
  font-size: 18px;
  font-weight: 500;
  color: var(--admin-text, #101c15);
}

.dash-metric-val.primary {
  color: var(--admin-primary, #22a653);
}

/* 2. Section Styles */
.dash-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.dash-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
  padding-bottom: 8px;
}

.dash-section-title {
  font-size: 13.5px;
  font-weight: 500;
  color: var(--admin-text, #101c15);
  margin: 0;
}

.dash-subtitle {
  font-size: 11.5px;
  color: var(--admin-muted, #64748b);
}

.dash-link {
  font-size: 12px;
  color: var(--admin-primary, #22a653);
  text-decoration: none;
  font-weight: 400;
}

.dash-summary-row {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 8px;
}

@media (max-width: 1024px) {
  .dash-summary-row {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 640px) {
  .dash-summary-row {
    grid-template-columns: repeat(2, 1fr);
  }
}

.dash-summary-item {
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 0;
  padding: 10px 12px;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dash-summary-item span {
  font-size: 11px;
  color: var(--admin-muted, #64748b);
}

.dash-summary-item strong {
  font-size: 15px;
  font-weight: 500;
  color: var(--admin-text, #101c15);
}

/* 3. Grid 2 Column */
.dash-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

@media (max-width: 768px) {
  .dash-grid-2 {
    grid-template-columns: 1fr;
  }
}

.dash-col {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.dash-table-wrap {
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 0;
  overflow: hidden;
}

.dash-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12.5px;
}

.dash-table th {
  padding: 8px 12px;
  background: var(--admin-bg-soft, #f7fbf5);
  border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
  font-weight: 500;
  color: var(--admin-muted, #64748b);
  font-size: 11.5px;
}

.dash-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
  color: var(--admin-text, #101c15);
}

.dash-table tr:last-child td {
  border-bottom: none;
}

.table-state-card {
  padding: 12px;
  border: 1px dashed var(--admin-border-soft, #cbd5e1);
  color: var(--admin-muted, #64748b);
  font-size: 12px;
  text-align: center;
}

.alert-error-custom {
  background: #fef2f2;
  color: #dc2626;
  border-color: #fca5a5;
  border-style: solid;
}
</style>
