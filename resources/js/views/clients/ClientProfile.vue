<template>
  <div class="cp-page">
    <PublicNavbar />

    <main class="cp-main">
      <!-- PAGE HEADER BAR -->
      <div class="cp-head">
        <div class="cp-head-info">
          <nav class="cp-breadcrumbs">
            <router-link to="/">Trang chủ</router-link>
            <span>/</span>
            <strong>Tài khoản</strong>
          </nav>
          <h1 class="cp-title">Tài khoản của tôi</h1>
          <p class="cp-subtitle">Quản lý thông tin cá nhân, lịch đặt và quyền lợi của bạn trên SportGo.</p>
        </div>

        <router-link class="cp-btn cp-btn--primary" to="/venues">
          <span>Tìm sân mới</span>
        </router-link>
      </div>

      <!-- ACCOUNT NAVIGATION TAB BAR -->
      <ClientAccountNav />

      <!-- NON-REDUNDANT STREAMLINED CONTENT -->
      <div class="cp-content">
        <!-- TOP IDENTITY SECTION -->
        <div class="cp-identity-sec">
          <div class="cp-user-info">
            <span class="cp-avatar" aria-hidden="true">{{ userInitial }}</span>
            <div class="cp-user-details">
              <h2>{{ user?.fullName || "Người chơi SportGo" }}</h2>
              <span class="cp-role-tag">{{ roleLabel }} · Hoạt động</span>
            </div>
          </div>

          <router-link class="cp-btn cp-btn--outline" to="/vip-membership">
            <span>Quyền lợi thành viên</span>
          </router-link>
        </div>

        <!-- STATS METRICS ROW -->
        <div class="cp-stats-row">
          <div class="cp-stat-item">
            <span class="cp-stat-label">Lịch đặt đã tạo</span>
            <strong class="cp-stat-val">{{ bookingCount }}</strong>
          </div>

          <div class="cp-stat-item">
            <span class="cp-stat-label">Số dư ví</span>
            <strong class="cp-stat-val cp-stat-val--green">{{ formatCurrency(walletBalance) }}</strong>
          </div>

          <div class="cp-stat-item">
            <span class="cp-stat-label">Hạng thành viên</span>
            <strong class="cp-stat-val">{{ membershipLabel }}</strong>
          </div>
        </div>

        <!-- PERSONAL INFO SECTION -->
        <div class="cp-info-sec">
          <h3 class="cp-section-title">Thông tin cá nhân</h3>

          <div class="cp-info-grid">
            <div class="cp-info-item">
              <span class="cp-info-label">Họ và tên</span>
              <strong class="cp-info-val">{{ user?.fullName || "Chưa cập nhật" }}</strong>
            </div>

            <div class="cp-info-item">
              <span class="cp-info-label">Tên tài khoản</span>
              <strong class="cp-info-val">{{ user?.username || user?.email || "Chưa cập nhật" }}</strong>
            </div>

            <div class="cp-info-item">
              <span class="cp-info-label">Email</span>
              <strong class="cp-info-val">{{ user?.email || "Chưa cập nhật" }}</strong>
            </div>

            <div class="cp-info-item">
              <span class="cp-info-label">Số điện thoại</span>
              <strong class="cp-info-val">{{ user?.phone || "Chưa cập nhật" }}</strong>
            </div>

            <div class="cp-info-item">
              <span class="cp-info-label">Trạng thái xác thực</span>
              <strong class="cp-info-val">{{ user?.email_verified_at ? "Email đã xác thực" : "Đang chờ xác thực" }}</strong>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script>
import ClientAccountNav from "../../components/ClientAccountNav.vue";
import PublicNavbar from "../../components/PublicNavbar.vue";
import { bookingService } from "../../services/bookingService.js";
import { getAuth } from "../../stores/auth.js";

export default {
  name: "ClientProfile",
  components: { ClientAccountNav, PublicNavbar },
  data() {
    return {
      user: getAuth(),
      bookingCount: 0,
      walletBalance: 0,
      membershipLabel: "Cơ bản",
    };
  },
  computed: {
    userInitial() {
      return this.user?.fullName?.trim()?.charAt(0)?.toUpperCase() || "S";
    },
    roleLabel() {
      return this.user?.role === "owner" ? "Chủ sân" : this.user?.role === "staff" ? "Nhân viên sân" : "Người chơi";
    },
  },
  created() {
    if (!this.user) {
      this.$router.replace({ name: "login", query: { redirect: this.$route.fullPath } });
      return;
    }
    this.loadOverview();
  },
  methods: {
    async loadOverview() {
      try {
        const [bookingsResponse, walletResponse] = await Promise.allSettled([
          bookingService.listBookings({ limit: 1 }),
          bookingService.getWallet(),
        ]);
        if (bookingsResponse.status === "fulfilled") {
          const payload = bookingsResponse.value?.data;
          this.bookingCount = Number(payload?.meta?.total ?? payload?.total ?? (Array.isArray(payload) ? payload.length : 0));
        }
        if (walletResponse.status === "fulfilled") {
          const payload = walletResponse.value?.data || walletResponse.value || {};
          this.walletBalance = Number(payload.balance ?? payload.wallet?.balance ?? 0);
        }
      } catch (error) {
        console.warn("Không thể tải tổng quan tài khoản", error);
      }
    },
    formatCurrency(value) {
      return `${new Intl.NumberFormat("vi-VN").format(Number(value || 0))} đ`;
    },
  },
};
</script>

<style scoped>
.cp-page {
  min-height: 100vh;
  background: #ffffff;
}

.cp-main {
  max-width: 1100px;
  margin: 0 auto;
  padding: 24px 16px 60px;
}

/* HEADER BAR */
.cp-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.cp-breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #1e293b;
  margin-bottom: 6px;
}

.cp-breadcrumbs a {
  color: #1e293b;
  text-decoration: none;
}

.cp-breadcrumbs a:hover {
  color: #0f172a;
}

.cp-breadcrumbs strong {
  color: #0f172a;
  font-weight: 500;
}

.cp-title {
  font-size: 22px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 4px;
}

.cp-subtitle {
  font-size: 13.5px;
  color: #1e293b;
  margin: 0;
}

/* BUTTON UTILITIES */
.cp-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 500;
  border-radius: 4px;
  cursor: pointer;
  text-decoration: none;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #0f172a;
}

.cp-btn--primary {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.cp-btn--outline {
  background: #ffffff;
  color: #0f172a;
  border-color: #cbd5e1;
}

/* CONTENT CONTAINER */
.cp-content {
  display: flex;
  flex-direction: column;
  gap: 28px;
}

/* TOP IDENTITY SECTION */
.cp-identity-sec {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 8px;
  flex-wrap: wrap;
}

.cp-user-info {
  display: flex;
  align-items: center;
  gap: 16px;
}

.cp-avatar {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: #15803d;
  color: #ffffff;
  font-size: 20px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.cp-user-details h2 {
  font-size: 18px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 2px;
}

.cp-role-tag {
  font-size: 13px;
  color: #15803d;
  font-weight: 500;
}

/* STATS ROW */
.cp-stats-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  padding-bottom: 8px;
}

@media (max-width: 600px) {
  .cp-stats-row {
    grid-template-columns: 1fr;
    gap: 16px;
  }
}

.cp-stat-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.cp-stat-label {
  font-size: 12.5px;
  color: #1e293b;
}

.cp-stat-val {
  font-size: 22px;
  font-weight: 500;
  color: #0f172a;
}

.cp-stat-val--green {
  color: #15803d;
}

/* INFO SECTION */
.cp-info-sec {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cp-section-title {
  font-size: 15px;
  font-weight: 500;
  color: #0f172a;
  margin: 0;
}

.cp-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 20px 24px;
}

.cp-info-item {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.cp-info-label {
  font-size: 12.5px;
  color: #1e293b;
}

.cp-info-val {
  font-size: 13.5px;
  color: #0f172a;
  font-weight: 500;
}
</style>
