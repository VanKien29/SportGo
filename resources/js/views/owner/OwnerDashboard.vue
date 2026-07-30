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
            <div class="text-right">
              <div class="text-xs font-medium text-emerald-600">{{ formatCurrency(booking.outstanding_amount || booking.total_price) }}</div>
              <div class="text-[10px] text-slate-400 mt-0.5">{{ formatDate(booking.booking_date) }} {{ booking.time_label }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Lịch trong ngày -->
      <div class="bg-white border border-slate-200 p-5 space-y-4 shadow-xs">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="text-xs font-medium uppercase tracking-wider text-slate-800">Lịch trong ngày</h3>
          <span class="text-xs text-slate-400 font-normal">{{ todayBookings.length }} lịch gần nhất</span>
        </div>

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
          </div>
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
    </div>
  </div>
</template>

<script>
import { api } from '../../services/api.js';
import { getAuth } from '../../stores/auth.js';
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
      error: null,
    };
  },
  computed: {
    userName() {
      return this.user?.fullName || this.user?.full_name || this.user?.username || 'Chủ sân';
    },
    todayBookings() {
      return this.stats.today_bookings || [];
    },
    pendingBookings() {
      return this.stats.pending_bookings || [];
    },
  },
  async mounted() {
    window.addEventListener('owner-cluster-changed', this.loadStats);
    await this.loadStats();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.loadStats);
  },
  methods: {
    async loadStats(event) {
      this.isLoading = true;
      this.error = null;

      let clusterId = localStorage.getItem('selected_cluster');
      if (event && event.detail) {
        this.selectedCluster = event.detail;
        if (event.detail.id) clusterId = event.detail.id;
      } else if (clusterId) {
        try {
          const response = await venueClusterService.getClusters();
          const clusters = response.data || [];
          this.selectedCluster = clusters.find((c) => String(c.id) === String(clusterId)) || null;
        } catch (e) {
          console.error('Failed to load clusters list for dashboard header:', e);
        }
      }

      const query = clusterId ? `?venue_cluster_id=${encodeURIComponent(clusterId)}` : '';

      try {
        this.stats = await api(`/api/owner/dashboard${query}`);
      } catch (error) {
        this.error = error.message || 'Không thể tải dữ liệu thống kê.';
      } finally {
        this.isLoading = false;
      }
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0);
    },
    formatDate(value) {
      if (!value) return 'Vừa xuất bản';
      return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      }).format(new Date(value));
    },
    postTypeLabel(type) {
      return {
        promotion: 'Khuyến mãi',
        tournament: 'Giải đấu',
        news: 'Tin tức',
        notice: 'Thông báo',
        recruitment: 'Tuyển dụng',
      }[type] || 'Bài viết';
    },
    postPublicUrl(post) {
      return `/venues/${post.venue_cluster_id}?tab=posts`;
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
