<template>
  <div class="dashboard">
    <div v-if="error" class="alert error">{{ error }}</div>

    <div class="section-container">
      <!-- Ví của tôi (Wallet) -->
      <div class="section-box">
        <div class="section-header-row">
          <div class="section-title-wrapper">
            <h3 class="section-title">VÍ CỦA TÔI</h3>
          </div>
          <router-link to="/owner/wallet" class="section-action-btn">
            Quản lý ví
          </router-link>
        </div>

        <div class="wallet-grid">
          <!-- Premium Card -->
          <div class="wallet-premium-card">
            <div>
              <div class="card-chip"></div>
              <div class="card-label">Số dư khả dụng</div>
              <div class="card-value">
                {{ isLoading ? '...' : formatCurrency(stats.wallet.available_balance) }}
              </div>
            </div>
            <div class="card-footer">
              <div class="card-holder-name">{{ userName }}</div>
            </div>
          </div>

          <!-- Other Wallet Details -->
          <div class="wallet-details-subgrid">
            <div class="wallet-subcard">
              <div class="wallet-subcard-info">
                <span class="wallet-subcard-label">Chờ rút tiền</span>
                <span class="wallet-subcard-value">
                  {{ isLoading ? '...' : formatCurrency(stats.wallet.pending_withdrawal_balance) }}
                </span>
              </div>
            </div>

            <div class="wallet-subcard">
              <div class="wallet-subcard-info">
                <span class="wallet-subcard-label">Tổng thu nhập</span>
                <span class="wallet-subcard-value">
                  {{ isLoading ? '...' : formatCurrency(stats.wallet.total_earned) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Số liệu vận hành (Overview Stats) -->
      <div class="section-box">
        <div class="section-header-row">
          <div class="section-title-wrapper">
            <h3 class="section-title">SỐ LIỆU VẬN HÀNH</h3>
          </div>
        </div>

        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-info">
              <span class="stat-number">
                {{ isLoading ? '...' : stats.bookings.toLocaleString() }}
              </span>
              <span class="stat-label">Tổng lượt đặt</span>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-info">
              <span class="stat-number">
                {{ isLoading ? '...' : formatCurrency(stats.revenue) }}
              </span>
              <span class="stat-label">Doanh thu online</span>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-info">
              <span class="stat-number">
                {{ isLoading ? '...' : stats.rating }} / 5
              </span>
              <span class="stat-label">Đánh giá trung bình</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Chi tiết (Details Layout) -->
      <div class="details-simple-grid">
        <!-- Doanh thu theo sân con (Court Revenues) -->
        <div class="section-box">
          <div class="section-header-row">
            <div class="section-title-wrapper">
              <h3 class="section-title">DOANH THU THEO SÂN CON</h3>
            </div>
          </div>

          <div v-if="isLoading" class="loading-wrapper">
            <div class="spinner"></div>
            <span>Đang tải dữ liệu doanh thu...</span>
          </div>
          <div v-else-if="!stats.court_revenues || stats.court_revenues.length === 0" class="empty-wrapper">
            <span>Không có dữ liệu doanh thu.</span>
          </div>
          <div v-else class="table-responsive">
            <table class="premium-table">
              <thead>
                <tr>
                  <th align="left">Tên sân con</th>
                  <th align="right" style="text-align: right;">Doanh thu</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(court, idx) in stats.court_revenues" :key="idx">
                  <td>{{ court.court_name }}</td>
                  <td align="right" style="text-align: right; font-weight: 700; color: #111827">
                    {{ formatCurrency(court.revenue) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Khung giờ vàng (Golden Hours) -->
        <div class="section-box">
          <div class="section-header-row">
            <div class="section-title-wrapper">
              <h3 class="section-title">KHUNG GIỜ VÀNG PHỔ BIẾN</h3>
            </div>
          </div>

          <div v-if="isLoading" class="loading-wrapper">
            <div class="spinner"></div>
            <span>Đang tải dữ liệu khung giờ chơi...</span>
          </div>
          <div v-else-if="!stats.golden_hours || stats.golden_hours.length === 0" class="empty-wrapper">
            <span>Không có dữ liệu khung giờ chơi.</span>
          </div>
          <div v-else class="table-responsive">
            <table class="premium-table">
              <thead>
                <tr>
                  <th align="left" style="width: 80px;">Xếp hạng</th>
                  <th align="left">Khung giờ</th>
                  <th align="right" style="text-align: right;">Lượt đặt</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(slot, idx) in stats.golden_hours" :key="idx">
                  <td>
                    <span :class="['rank-badge', idx === 0 ? 'rank-1' : idx === 1 ? 'rank-2' : idx === 2 ? 'rank-3' : 'rank-other']">
                      {{ idx + 1 }}
                    </span>
                  </td>
                  <td style="font-weight: 600;">{{ slot.time_slot }}</td>
                  <td align="right" style="text-align: right; font-weight: 700;">
                    {{ slot.count }} lượt
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { api } from '../../services/api.js';
import { getAuth } from '../../stores/auth.js';
import { venueClusterService } from '../../services/venueClusters.js';

export default {
  name: 'OwnerDashboard',
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
        golden_hours: [],
        court_revenues: [],
      },
      isLoading: true,
      error: null,
    };
  },
  computed: {
    userName() {
      return this.user?.fullName || this.user?.full_name || this.user?.username || 'Chủ sân';
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
  },
};
</script>

<style src="../../../css/owner/dashboard.css" scoped></style>
