<template>
  <section class="dashboard">
    <section class="dashboard-hero">
      <div>
        <span class="hero-kicker">SportGo Admin</span>
        <h1>Xin chào, {{ userName }}</h1>
        <p>Điều phối người dùng, sân, thanh toán và phí nền tảng trong một không gian quản trị sáng, gọn, dễ quét.</p>
      </div>
      <RouterLink class="hero-action" to="/admin/payments">
        <AppIcon name="creditCard" size="18" />
        <span>Đối soát thanh toán</span>
      </RouterLink>
    </section>

    <div v-if="error" class="alert error">{{ error }}</div>

    <section class="metric-grid">
      <article v-for="item in overviewCards" :key="item.label" class="metric-card" :class="item.tone">
        <div class="metric-icon">
          <AppIcon :name="item.icon" size="20" />
        </div>
        <span>{{ item.label }}</span>
        <strong>{{ item.value }}</strong>
        <small>{{ item.caption }}</small>
      </article>
    </section>

    <section class="dashboard-grid">
      <article class="dashboard-panel quick-panel">
        <div class="panel-head">
          <div>
            <span class="eyebrow">Luồng công việc</span>
            <h2>Truy cập nhanh</h2>
          </div>
        </div>
        <div class="quick-links">
          <RouterLink v-for="item in quickLinks" :key="item.to" class="quick-link" :to="item.to">
            <span class="quick-icon"><AppIcon :name="item.icon" size="18" /></span>
            <span>
              <strong>{{ item.label }}</strong>
              <small>{{ item.description }}</small>
            </span>
          </RouterLink>
        </div>
      </article>

      <article class="dashboard-panel fee-panel">
        <div class="panel-head">
          <div>
            <span class="eyebrow">Platform fee</span>
            <h2>Phí duy trì</h2>
          </div>
          <RouterLink class="panel-link" to="/admin/platform-fee-ledgers">Xem ledger</RouterLink>
        </div>
        <div class="fee-list">
          <RouterLink v-for="item in feeCards" :key="item.label" class="fee-row" :to="item.to">
            <span>{{ item.label }}</span>
            <strong>{{ item.value }}</strong>
          </RouterLink>
        </div>
      </article>
    </section>
  </section>
</template>

<script>
import { getAuth } from '../../stores/auth.js';
import { api } from '../../services/api.js';
import { getPlatformFeeDashboardMetrics } from '../../services/platformFeeLedger.service.js';
import AppIcon from '../../components/AppIcon.vue';

export default {
  name: 'AdminDashboard',
  components: { AppIcon },
  data() {
    return {
      user: getAuth(),
      stats: {
        users: 0,
        venues: 0,
        bookings: 0,
        revenue: 0,
      },
      feeMetrics: getPlatformFeeDashboardMetrics(),
      isLoading: true,
      error: null,
      quickLinks: [
        {
          label: 'Tài khoản',
          description: 'Vai trò, khóa mở và cảnh báo người dùng',
          icon: 'users',
          to: '/admin/users',
        },
        {
          label: 'Cụm sân',
          description: 'Duyệt cụm sân, loại sân và phí duy trì',
          icon: 'building',
          to: '/admin/venue-clusters',
        },
        {
          label: 'Hồ sơ đối tác',
          description: 'Hồ sơ đăng ký và tài liệu xác minh',
          icon: 'fileText',
          to: '/admin/partner-applications',
        },
        {
          label: 'Kiểm duyệt',
          description: 'Báo cáo cộng đồng và khiếu nại dịch vụ',
          icon: 'messageWarning',
          to: '/admin/reports',
        },
      ],
    };
  },
  computed: {
    userName() {
      return this.user?.fullName || this.user?.full_name || this.user?.username || 'Admin';
    },
    overviewCards() {
      return [
        {
          label: 'Người dùng',
          value: this.isLoading ? '...' : this.stats.users.toLocaleString(),
          caption: 'Tài khoản trên hệ thống',
          icon: 'users',
          tone: 'green',
        },
        {
          label: 'Cụm sân',
          value: this.isLoading ? '...' : this.stats.venues.toLocaleString(),
          caption: 'Địa điểm đang quản lý',
          icon: 'building',
          tone: 'blue',
        },
        {
          label: 'Lượt đặt sân',
          value: this.isLoading ? '...' : this.stats.bookings.toLocaleString(),
          caption: 'Booking đã ghi nhận',
          icon: 'calendar',
          tone: 'purple',
        },
        {
          label: 'Doanh thu',
          value: this.isLoading ? '...' : this.formatCurrency(this.stats.revenue),
          caption: 'Tổng giá trị giao dịch',
          icon: 'banknote',
          tone: 'amber',
        },
      ];
    },
    feeCards() {
      return [
        {
          label: 'Chờ thanh toán',
          value: this.feeMetrics.pending,
          to: '/admin/platform-fee-ledgers?status=pending',
        },
        {
          label: 'Quá hạn',
          value: this.feeMetrics.overdue,
          to: '/admin/platform-fee-ledgers?status=overdue',
        },
        {
          label: 'Đã thu tháng này',
          value: this.formatCurrency(this.feeMetrics.paid_this_month),
          to: '/admin/platform-fee-ledgers?status=paid&range=this_month',
        },
        {
          label: 'Email gửi lỗi',
          value: this.feeMetrics.email_failed,
          to: '/admin/platform-fee-ledgers?email_status=failed',
        },
      ];
    },
  },
  async mounted() {
    try {
      this.stats = await api('/api/admin/dashboard');
    } catch (error) {
      this.error = error.message || 'Không thể tải dữ liệu thống kê.';
    } finally {
      this.isLoading = false;
    }
  },
  methods: {
    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0);
    },
  },
};
</script>

<style src="../../../css/admin/dashboard.css" scoped></style>
