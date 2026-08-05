<template>
  <div class="sg-client-page sg3-profile-page">
    <PublicNavbar />
    <main class="sg3-profile-main sg-container" aria-label="Tài khoản cá nhân">
      <div class="sg3-page-head">
        <div>
          <div class="sg3-breadcrumbs"><router-link to="/">Trang chủ</router-link><span>/</span><strong>Tài khoản</strong></div>
          <p class="sg3-kicker">Không gian cá nhân</p>
          <h1>Tài khoản của tôi</h1>
          <p>Quản lý thông tin cá nhân, lịch đặt và những quyền lợi đang có trên SportGo.</p>
        </div>
        <router-link class="sg3-button sg3-button--primary" to="/venues">
          <AppIcon name="search" :size="17" />
          Tìm sân mới
        </router-link>
      </div>

      <ClientAccountNav />

      <div class="sg3-profile-layout">
        <section class="sg3-profile-primary">
          <article class="sg3-card sg3-profile-identity">
            <span class="sg3-avatar" aria-hidden="true">{{ userInitial }}</span>
            <div>
              <h2>{{ user.fullName || "Người chơi SportGo" }}</h2>
              <p>{{ user.email || "Chưa cập nhật email" }}<span v-if="user.phone"> · {{ user.phone }}</span></p>
              <span class="sg3-status">Tài khoản đang hoạt động</span>
            </div>
            <router-link class="sg3-button sg3-button--secondary" to="/vip-membership">
              <AppIcon name="shieldCheck" :size="17" />
              Quyền lợi thành viên
            </router-link>
          </article>

          <div class="sg3-stats" aria-label="Tổng quan tài khoản">
            <article class="sg3-card sg3-stat"><span>Lịch đặt đã tạo</span><strong>{{ bookingCount }}</strong></article>
            <article class="sg3-card sg3-stat"><span>Số dư ví</span><strong>{{ formatCurrency(walletBalance) }}</strong></article>
            <article class="sg3-card sg3-stat"><span>Hạng thành viên</span><strong>{{ membershipLabel }}</strong></article>
          </div>

          <section class="sg3-card sg3-info-card" aria-labelledby="profile-information-heading">
            <h2 id="profile-information-heading">Thông tin cá nhân</h2>
            <div class="sg3-info-grid">
              <div class="sg3-info-item"><span>Họ và tên</span><strong>{{ user.fullName || "Chưa cập nhật" }}</strong></div>
              <div class="sg3-info-item"><span>Tên tài khoản</span><strong>{{ user.username || user.email || "Chưa cập nhật" }}</strong></div>
              <div class="sg3-info-item"><span>Email</span><strong>{{ user.email || "Chưa cập nhật" }}</strong></div>
              <div class="sg3-info-item"><span>Số điện thoại</span><strong>{{ user.phone || "Chưa cập nhật" }}</strong></div>
              <div class="sg3-info-item"><span>Vai trò</span><strong>{{ roleLabel }}</strong></div>
              <div class="sg3-info-item"><span>Trạng thái xác thực</span><strong>{{ user.email_verified_at ? "Email đã xác thực" : "Đang chờ xác thực" }}</strong></div>
            </div>
          </section>
        </section>

        <aside class="sg3-profile-aside">
          <section class="sg3-card sg3-side-card">
            <h2>Đi tới nhanh</h2>
            <p>Các việc bạn thường làm sau khi đăng nhập.</p>
            <div class="sg3-side-links">
              <router-link to="/bookings">Lịch đặt của tôi <AppIcon name="chevronRight" :size="16" /></router-link>
              <router-link to="/wallet">Ví SportGo <AppIcon name="chevronRight" :size="16" /></router-link>
              <router-link to="/refunds">Theo dõi hoàn tiền <AppIcon name="chevronRight" :size="16" /></router-link>
              <router-link to="/notifications">Thông báo <AppIcon name="chevronRight" :size="16" /></router-link>
            </div>
          </section>

          <section class="sg3-card sg3-side-card">
            <h2>Cần hỗ trợ?</h2>
            <p>Gửi yêu cầu để đội ngũ SportGo hỗ trợ theo đúng lịch sử giao dịch của bạn.</p>
            <router-link class="sg3-button sg3-button--secondary" to="/complaints/new">Gửi yêu cầu hỗ trợ</router-link>
          </section>
        </aside>
      </div>
    </main>
  </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import ClientAccountNav from "../../components/ClientAccountNav.vue";
import PublicNavbar from "../../components/PublicNavbar.vue";
import { bookingService } from "../../services/bookingService.js";
import { getAuth } from "../../stores/auth.js";

export default {
  name: "ClientProfile",
  components: { AppIcon, ClientAccountNav, PublicNavbar },
  data() {
    return { user: getAuth(), bookingCount: 0, walletBalance: 0, membershipLabel: "Cơ bản" };
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
        const [bookingsResponse, walletResponse] = await Promise.allSettled([bookingService.listBookings({ limit: 1 }), bookingService.getWallet()]);
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
.sg3-profile-page :deep(.sg3-account-nav) { margin-bottom: 20px; }
.sg3-profile-page :deep(.sg3-side-links svg) { flex: 0 0 auto; }
</style>
