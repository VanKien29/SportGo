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
  </div>
</template>

<script>
import { getAuth } from '../../stores/auth.js';
import { api } from '../../services/api.js';

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
