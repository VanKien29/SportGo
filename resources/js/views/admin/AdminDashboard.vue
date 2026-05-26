<template>
  <div class="dashboard">
    <!-- Welcome Card -->
    <div class="welcome-card">
      <div class="welcome-content">
        <h1 class="welcome-title">Xin chào, {{ user?.fullName }} 👋</h1>
        <p class="welcome-desc">Chào mừng bạn đến với trang quản trị SportGo. Tổng quan hệ thống hiển thị dữ liệu thật từ Database.</p>
      </div>
      <div class="welcome-icon">
        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
          <rect x="3" y="3" width="7" height="7" rx="1"/>
          <rect x="14" y="3" width="7" height="7" rx="1"/>
          <rect x="3" y="14" width="7" height="7" rx="1"/>
          <rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon users">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : stats.users.toLocaleString() }}</div>
          <div class="stat-label">Người dùng</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon venues">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
            <circle cx="12" cy="10" r="3"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : stats.venues.toLocaleString() }}</div>
          <div class="stat-label">Cụm sân</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon bookings">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ isLoading ? '...' : stats.bookings.toLocaleString() }}</div>
          <div class="stat-label">Lượt đặt sân</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon revenue">
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
    </div>

    <!-- Error state -->
    <div v-if="error" style="color: red; margin-bottom: 20px;">{{ error }}</div>

    <!-- Placeholder content -->
    <div class="placeholder-section">
      <div class="placeholder-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M12 20h9"/>
          <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
        </svg>
      </div>
      <h3>Trang Dashboard Admin</h3>
      <p>Biểu đồ thống kê chi tiết và tính năng quản trị sẽ được phát triển ở các bước tiếp theo.</p>
    </div>
  </div>
</template>

<script>
import AdminDashboardLogic from '../../controllers/admin/AdminDashboard.js';
export default AdminDashboardLogic;
</script>
<style src="../../../css/admin/dashboard.css" scoped></style>
