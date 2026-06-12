<template>
  <div class="dashboard">
    <section class="page-head">
      <div>
        <h2>Bảng điều hành chủ sân</h2>
        <p>Theo dõi nhanh booking, doanh thu online và đánh giá của cụm sân đang chọn.</p>
      </div>
    </section>

    <div v-if="error" class="alert error">{{ error }}</div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : stats.bookings.toLocaleString() }}</div>
          <div class="stat-label">Booking</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : formatCurrency(stats.revenue) }}</div>
          <div class="stat-label">Doanh thu online</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : stats.rating }}</div>
          <div class="stat-label">Đánh giá trung bình</div>
        </div>
      </div>
    </div>
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
    window.addEventListener('owner-cluster-changed', this.loadStats);
    await this.loadStats();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.loadStats);
  },
  methods: {
    async loadStats() {
      this.isLoading = true;
      this.error = null;
      const clusterId = localStorage.getItem('selected_cluster');
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
  },
};
</script>

<style src="../../../css/owner/dashboard.css" scoped></style>
<style scoped>
.alert {
  margin-bottom: 16px;
  border-radius: 10px;
  padding: 12px 14px;
  font-weight: 700;
}

.alert.error {
  color: #b91c1c;
  background: #fee2e2;
}
</style>
