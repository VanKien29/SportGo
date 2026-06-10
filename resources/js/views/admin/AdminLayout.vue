<template>
  <SidebarLayout brand-sub="Quản trị hệ thống" dashboard-route="/admin/dashboard">
    <template #nav-items>
      <router-link to="/admin/dashboard" class="nav-item" active-class="nav-active">
        <AppIcon name="dashboard" size="18" />
        <span>Dashboard</span>
      </router-link>

      <div class="nav-group">Tài khoản & phân quyền</div>
      <router-link to="/admin/users" class="nav-item" active-class="nav-active">
        <AppIcon name="users" size="18" />
        <span>Quản lý tài khoản</span>
      </router-link>
      <router-link to="/admin/roles" class="nav-item" active-class="nav-active">
        <AppIcon name="shieldCheck" size="18" />
        <span>Nhóm quyền</span>
      </router-link>

      <div class="nav-group">Tài chính & voucher</div>
      <router-link to="/admin/payments" class="nav-item" active-class="nav-active">
        <AppIcon name="creditCard" size="18" />
        <span>Thanh toán booking</span>
      </router-link>
      <router-link to="/admin/finance-operations" class="nav-item" active-class="nav-active">
        <AppIcon name="banknote" size="18" />
        <span>Hoàn tiền & rút tiền</span>
      </router-link>
      <router-link to="/admin/vouchers" class="nav-item" active-class="nav-active">
        <AppIcon name="creditCard" size="18" />
        <span>Voucher hệ thống</span>
      </router-link>

      <div class="nav-group">Cấu hình hệ thống</div>
      <router-link to="/admin/policies" class="nav-item" active-class="nav-active">
        <AppIcon name="fileText" size="18" />
        <span>Quản lý chính sách</span>
      </router-link>
      <router-link to="/admin/court-types" class="nav-item" active-class="nav-active">
        <AppIcon name="layers" size="18" />
        <span>Loại sân</span>
      </router-link>
      <router-link to="/admin/amenities" class="nav-item" active-class="nav-active">
        <AppIcon name="star" size="18" />
        <span>Tiện ích</span>
      </router-link>

      <div class="nav-group">Kiểm duyệt & hỗ trợ</div>
      <router-link to="/admin/reports" class="nav-item" active-class="nav-active">
        <AppIcon name="messageWarning" size="18" />
        <span>Xử lý báo cáo</span>
      </router-link>
      <router-link to="/admin/complaints" class="nav-item" active-class="nav-active">
        <AppIcon name="shieldCheck" size="18" />
        <span>Xử lý khiếu nại</span>
      </router-link>

      <div class="nav-group">Cụm sân & đối tác</div>
      <router-link to="/admin/venue-clusters" class="nav-item" active-class="nav-active">
        <AppIcon name="layers" size="18" />
        <span>Quản lý cụm sân</span>
      </router-link>
      <router-link to="/admin/platform-fee-tiers" class="nav-item" :class="{ 'nav-active': platformFeeSectionActive }">
        <AppIcon name="layers" size="18" />
        <span>Bậc phí nền tảng</span>
      </router-link>
      <router-link to="/admin/partner-applications" class="nav-item" active-class="nav-active">
        <AppIcon name="fileText" size="18" />
        <span>Đơn đăng ký đối tác</span>
      </router-link>
      <router-link to="/admin/banners" class="nav-item" active-class="nav-active">
        <AppIcon name="image" size="18" />
        <span>Banner</span>
      </router-link>
    </template>

    <template #topbar-title>
      <span>{{ currentTitle }}</span>
    </template>

    <router-view />
  </SidebarLayout>
</template>

<script>
import SidebarLayout from '../../components/SidebarLayout.vue';
import AppIcon from '../../components/AppIcon.vue';

export default {
  name: 'AdminLayout',
  components: { AppIcon, SidebarLayout },
  computed: {
    platformFeeSectionActive() {
      return String(this.$route.name || '').includes('platform-fee') || this.$route.query.source === 'platform_fee';
    },
    currentTitle() {
      const map = {
        'admin-dashboard': 'Dashboard',
        'admin-profile': 'Thông tin cá nhân',
        'admin-users': 'Quản lý tài khoản',
        'admin-user-detail': 'Chi tiết tài khoản',
        'admin-payments': 'Theo dõi thanh toán booking',
        'admin-finance-operations': 'Hoàn tiền và rút tiền',
        'admin-vouchers': 'Voucher hệ thống',
        'admin-partner-applications': 'Quản lý đơn đăng ký',
        'admin-banners': 'Quản lý banner',
        'admin-roles': 'Quản lý nhóm quyền',
        'admin-role-detail': 'Chi tiết nhóm quyền',
        'admin-policies': 'Quản lý chính sách',
        'admin-platform-fee-policies': 'Chính sách phí nền tảng',
        'admin-policy-detail': 'Chi tiết chính sách',
        'admin-reports': 'Xử lý báo cáo',
        'admin-complaints': 'Xử lý khiếu nại',
        'admin-court-types': 'Quản lý loại sân',
        'admin-amenities': 'Quản lý tiện ích',
        'admin-venue-clusters': 'Quản lý cụm sân',
        'admin-venue-cluster-detail': 'Chi tiết cụm sân',
        'admin-platform-fee-tiers': 'Cấu hình bậc phí nền tảng',
        'admin-platform-fee-ledgers': 'Quản lý phí duy trì hệ thống',
        'admin-platform-fee-ledger-detail': 'Chi tiết kỳ phí duy trì',
        'admin-venue-platform-fees': 'Phí duy trì cụm sân',
        'admin-platform-fee-settings': 'Cài đặt phí duy trì',
      };

      return map[this.$route.name] || 'Admin';
    },
  },
};
</script>

<style src="../../../css/admin/layout.css" scoped></style>
<style scoped>
.nav-group {
  margin: 12px 8px 4px;
  color: #94a3b8;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}

:deep(.nav-item svg) {
  color: currentColor;
}
</style>
