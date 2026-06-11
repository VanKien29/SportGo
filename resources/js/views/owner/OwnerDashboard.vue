<template>
  <div class="dashboard-simple">
    <div v-if="error" class="error-msg">{{ error }}</div>

    <!-- Ví của tôi (Wallet) -->
    <div class="section-box">
      <h3 class="section-title">VÍ CỦA TÔI</h3>
      <div class="wallet-simple-grid">
        <div class="wallet-item">
          <span class="label">Số dư khả dụng:</span>
          <span class="value bold">{{ isLoading ? '...' : formatCurrency(stats.wallet.available_balance) }}</span>
        </div>
        <div class="wallet-item">
          <span class="label">Chờ rút tiền:</span>
          <span class="value">{{ isLoading ? '...' : formatCurrency(stats.wallet.pending_withdrawal_balance) }}</span>
        </div>
        <div class="wallet-item">
          <span class="label">Tổng thu nhập:</span>
          <span class="value">{{ isLoading ? '...' : formatCurrency(stats.wallet.total_earned) }}</span>
        </div>
      </div>
    </div>

    <!-- Số liệu vận hành (Overview Stats) -->
    <div class="section-box">
      <h3 class="section-title">SỐ LIỆU VẬN HÀNH</h3>
      <div class="stats-simple-grid">
        <div class="stat-item">
          <span class="label">Tổng lượt đặt:</span>
          <span class="value bold">{{ isLoading ? '...' : stats.bookings.toLocaleString() }}</span>
        </div>
        <div class="stat-item">
          <span class="label">Doanh thu online:</span>
          <span class="value bold">{{ isLoading ? '...' : formatCurrency(stats.revenue) }}</span>
        </div>
        <div class="stat-item">
          <span class="label">Đánh giá trung bình:</span>
          <span class="value bold">{{ isLoading ? '...' : stats.rating }} / 5</span>
        </div>
      </div>
    </div>

    <!-- Chi tiết (Details Layout) -->
    <div class="details-simple-grid">
      <!-- Doanh thu theo sân con (Court Revenues) -->
      <div class="section-box">
        <h3 class="section-title">DOANH THU THEO SÂN CON</h3>
        <div v-if="isLoading" class="loading-text">Đang tải...</div>
        <div v-else-if="!stats.court_revenues || stats.court_revenues.length === 0" class="empty-text">
          Không có dữ liệu doanh thu.
        </div>
        <table v-else class="simple-table">
          <thead>
            <tr>
              <th align="left">Tên sân</th>
              <th align="right">Doanh thu</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(court, idx) in stats.court_revenues" :key="idx">
              <td>{{ court.court_name }}</td>
              <td align="right">{{ formatCurrency(court.revenue) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Khung giờ vàng (Golden Hours) -->
      <div class="section-box">
        <h3 class="section-title">KHUNG GIỜ VÀNG PHỔ BIẾN</h3>
        <div v-if="isLoading" class="loading-text">Đang tải...</div>
        <div v-else-if="!stats.golden_hours || stats.golden_hours.length === 0" class="empty-text">
          Không có dữ liệu khung giờ chơi.
        </div>
        <table v-else class="simple-table">
          <thead>
            <tr>
              <th align="left">Xếp hạng</th>
              <th align="left">Khung giờ</th>
              <th align="right">Lượt đặt</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(slot, idx) in stats.golden_hours" :key="idx">
              <td>#{{ idx + 1 }}</td>
              <td>{{ slot.time_slot }}</td>
              <td align="right">{{ slot.count }}</td>
            </tr>
          </tbody>
        </table>
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
        wallet: {
          available_balance: 0,
          pending_withdrawal_balance: 0,
          total_earned: 0,
          total_withdrawn: 0,
        },
        golden_hours: [],
        court_revenues: [],
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

<style scoped>
.dashboard-simple {
  max-width: 1000px;
  font-family: inherit;
  color: #333333;
}

.error-msg {
  padding: 10px;
  background-color: #f2f2f2;
  border: 1px solid #cccccc;
  margin-bottom: 20px;
  font-size: 14px;
}

.section-box {
  border: 1px solid #dddddd;
  padding: 20px;
  margin-bottom: 20px;
  background-color: #ffffff;
}

.section-title {
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.5px;
  margin-top: 0;
  margin-bottom: 15px;
  border-bottom: 1px solid #dddddd;
  padding-bottom: 8px;
  color: #000000;
}

.wallet-simple-grid, .stats-simple-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

@media (max-width: 600px) {
  .wallet-simple-grid, .stats-simple-grid {
    grid-template-columns: 1fr;
  }
}

.wallet-item, .stat-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.label {
  font-size: 12px;
  color: #666666;
}

.value {
  font-size: 16px;
}

.bold {
  font-weight: 700;
  color: #000000;
}

.details-simple-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

@media (max-width: 768px) {
  .details-simple-grid {
    grid-template-columns: 1fr;
  }
}

.simple-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.simple-table th, .simple-table td {
  padding: 8px 10px;
  border-bottom: 1px solid #eeeeee;
}

.simple-table th {
  font-weight: 700;
  color: #666666;
  border-bottom: 2px solid #dddddd;
}

.loading-text, .empty-text {
  padding: 20px;
  text-align: center;
  color: #666666;
  font-size: 14px;
}
</style>
