<template>
  <div class="dashboard">
    <!-- Welcome Card -->
    <div class="welcome-card">
      <div class="welcome-content">
        <h1 class="welcome-title">{{ cluster?.name || 'Dashboard Chủ sân' }}</h1>
        <p class="welcome-desc">
          Chào mừng bạn đến với trang quản lý cụm sân. Dữ liệu đang được lấy từ Database.
          <span v-if="cluster"><br>Địa chỉ: {{ cluster.address }}</span>
        </p>
        <div class="welcome-badges" v-if="cluster">
          <span class="badge badge-green">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7" rx="1"/>
              <rect x="14" y="3" width="7" height="7" rx="1"/>
              <rect x="3" y="14" width="7" height="7" rx="1"/>
              <rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            {{ cluster.courtCount }} sân con
          </span>
          <span class="badge badge-blue">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
            Đang hoạt động
          </span>
        </div>
      </div>
      <div class="welcome-icon">
        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
          <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
          <circle cx="12" cy="10" r="3"/>
        </svg>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon bookings-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : stats.bookings.toLocaleString() }}</div>
          <div class="stat-label">Booking hôm nay</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon revenue-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="1" x2="12" y2="23"/>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : formatCurrency(stats.revenue) }}</div>
          <div class="stat-label">Doanh thu</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon rating-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : stats.rating }}</div>
          <div class="stat-label">Đánh giá TB</div>
        </div>
      </div>
    </div>

    <!-- Error state -->
    <div v-if="error" style="color: red; margin-bottom: 20px;">{{ error }}</div>

    <!-- Placeholder -->
    <div class="placeholder-section">
      <div class="placeholder-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M12 20h9"/>
          <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
        </svg>
      </div>
      <h3>Dashboard Chủ sân</h3>
      <p>Biểu đồ thống kê, lịch booking và tính năng quản lý sân sẽ được phát triển ở các bước tiếp theo.</p>
    </div>
  </div>
</template>

<script>
import OwnerDashboardLogic from '../../controllers/owner/OwnerDashboard.js';
export default OwnerDashboardLogic;
</script>
<style src="../../../css/owner/dashboard.css" scoped></style>
