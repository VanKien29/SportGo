<template>
  <SidebarLayout brand-sub="Quản lý sân" dashboard-route="/owner/dashboard">
    <template #nav-items>
      <div class="owner-nav">
        <router-link to="/owner/dashboard" class="nav-item" active-class="nav-active">
          <AppIcon name="dashboard" size="18" />
          <span>Tổng quan</span>
        </router-link>
        <router-link to="/owner/venue-clusters" class="nav-item" active-class="nav-active">
          <AppIcon name="building" size="18" />
          <span>Quản lý cụm sân</span>
        </router-link>
        <button class="nav-item nav-muted" type="button">
          <AppIcon name="calendar" size="18" />
          <span>Lịch sân</span>
        </button>
        <button class="nav-item nav-muted" type="button">
          <AppIcon name="calendar" size="18" />
          <span>Booking Online</span>
        </button>
        <button class="nav-item nav-muted" type="button">
          <AppIcon name="refresh" size="18" />
          <span>Đặt sân cố định</span>
        </button>
        <button class="nav-item nav-muted" type="button">
          <AppIcon name="creditCard" size="18" />
          <span>Booking tại quầy</span>
        </button>
        <button class="nav-item nav-muted" type="button">
          <AppIcon name="circleCheck" size="18" />
          <span>Trạng thái sân</span>
        </button>
        <button class="nav-item nav-muted" type="button">
          <AppIcon name="plus" size="18" />
          <span>Yêu cầu thêm sân</span>
        </button>
        <button class="nav-item nav-muted" type="button">
          <AppIcon name="messageWarning" size="18" />
          <span>Tin nhắn</span>
        </button>
        <button class="nav-item nav-muted" type="button">
          <AppIcon name="fileText" size="18" />
          <span>Bài đăng sân</span>
        </button>
        <router-link to="/owner/pricing" class="nav-item" active-class="nav-active">
          <AppIcon name="settings" size="18" />
          <span>Cấu hình giá</span>
        </router-link>
        <button class="nav-item nav-muted" type="button">
          <AppIcon name="users" size="18" />
          <span>Giao lưu tại sân</span>
        </button>
        <button class="nav-item nav-muted" type="button">
          <AppIcon name="banknote" size="18" />
          <span>Phí nền tảng</span>
        </button>
        <button class="nav-item nav-muted" type="button">
          <AppIcon name="shieldCheck" size="18" />
          <span>Đánh giá</span>
        </button>
        <button class="nav-item nav-muted" type="button">
          <AppIcon name="refresh" size="18" />
          <span>Yêu cầu hủy/hoàn tiền</span>
        </button>
        <button class="nav-item nav-muted" type="button">
          <AppIcon name="alert" size="18" />
          <span>Khiếu nại</span>
        </button>
        <button class="nav-item nav-muted" type="button">
          <AppIcon name="settings" size="18" />
          <span>Cấu hình & thanh toán</span>
        </button>
      </div>
    </template>
    <template #topbar-title>
      <span>{{ currentTitle }}</span>
    </template>
    <router-view />
  </SidebarLayout>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import SidebarLayout from '../../components/SidebarLayout.vue';

export default {
  name: 'OwnerLayout',
  components: { AppIcon, SidebarLayout },
  computed: {
    currentTitle() {
      const map = {
        'owner-dashboard': 'Dashboard',
        'owner-profile': 'Thông tin cá nhân',
        'owner-venue-clusters': 'Quản lý cụm sân',
        'owner-venue-courts': 'Quản lý sân con',
        'owner-pricing': 'Cấu hình giá',
      };

      return map[this.$route.name] || 'Chủ sân';
    },
  },
};
</script>

<style scoped>
.owner-nav {
  display: grid;
  gap: 4px;
}

.owner-nav .nav-item {
  width: 100%;
  min-height: 38px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 8px;
  color: rgba(255, 255, 255, 0.74);
  font-size: 13px;
  font-weight: 750;
  line-height: 1.25;
  text-align: left;
}

.owner-nav .nav-item svg {
  color: currentColor;
}

.owner-nav .nav-item:hover,
.owner-nav .nav-active {
  background: #059669;
  color: #fff;
}

.nav-muted {
  opacity: 0.78;
}
</style>
