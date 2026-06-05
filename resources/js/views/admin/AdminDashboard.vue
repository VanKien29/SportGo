<template>
  <div class="dashboard">
    <div class="welcome-card">
      <div class="welcome-content">
        <h1 class="welcome-title">Xin chào, {{ user?.fullName || user?.full_name || user?.username }}</h1>
        <p class="welcome-desc">Tổng quan hệ thống SportGo.</p>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : stats.users.toLocaleString() }}</div>
          <div class="stat-label">Người dùng</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : stats.venues.toLocaleString() }}</div>
          <div class="stat-label">Cụm sân</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : stats.bookings.toLocaleString() }}</div>
          <div class="stat-label">Lượt đặt sân</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : formatCurrency(stats.revenue) }}</div>
          <div class="stat-label">Doanh thu</div>
        </div>
      </div>
    </div>

    <div v-if="error" style="color: red; margin-bottom: 20px;">{{ error }}</div>

    <div class="stats-grid platform-fee-grid">
      <router-link class="stat-card fee-link" to="/admin/platform-fee-ledgers?status=pending">
        <div class="stat-content">
          <div class="stat-number">{{ feeMetrics.pending }}</div>
          <div class="stat-label">Kỳ phí chờ thanh toán</div>
        </div>
      </router-link>
      <router-link class="stat-card fee-link" to="/admin/platform-fee-ledgers?status=overdue">
        <div class="stat-content">
          <div class="stat-number">{{ feeMetrics.overdue }}</div>
          <div class="stat-label">Kỳ phí quá hạn</div>
        </div>
      </router-link>
      <router-link class="stat-card fee-link" to="/admin/platform-fee-ledgers?status=paid&range=this_month">
        <div class="stat-content">
          <div class="stat-number">{{ formatCurrency(feeMetrics.paid_this_month) }}</div>
          <div class="stat-label">Phí đã thu tháng này</div>
        </div>
      </router-link>
      <router-link class="stat-card fee-link" to="/admin/platform-fee-ledgers?email_status=failed">
        <div class="stat-content">
          <div class="stat-number">{{ feeMetrics.email_failed }}</div>
          <div class="stat-label">Email nhắc phí gửi lỗi</div>
        </div>
      </router-link>
    </div>
  </div>
</template>

<script>
import { getAuth } from '../../stores/auth.js';
import { api } from '../../services/api.js';
import { getPlatformFeeDashboardMetrics } from '../../services/platformFeeLedger.service.js';

export default {
  name: 'AdminDashboard',
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
    };
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
