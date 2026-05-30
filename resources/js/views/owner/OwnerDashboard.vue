<template>
  <div class="dashboard">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : stats.bookings.toLocaleString() }}</div>
          <div class="stat-label">Booking hôm nay</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : formatCurrency(stats.revenue) }}</div>
          <div class="stat-label">Doanh thu</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : stats.rating }}</div>
          <div class="stat-label">Đánh giá TB</div>
        </div>
      </div>
    </div>

    <div v-if="error" style="color: red; margin-bottom: 20px;">{{ error }}</div>
  </div>
</template>

<script>
import { api } from '../../services/api.js';

export default {
  name: 'OwnerDashboard',
  data() {
    return {
      stats: {
        bookings: 0,
        revenue: 0,
        rating: 0,
      },
      isLoading: true,
      error: null,
    };
  },
  async mounted() {
    try {
      this.stats = await api('/api/owner/dashboard');
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

<style src="../../../css/owner/dashboard.css" scoped></style>
