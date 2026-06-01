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

      <div class="nav-group">Cấu hình hệ thống</div>
      <router-link to="/admin/policies" class="nav-item" active-class="nav-active">
        <AppIcon name="fileText" size="18" />
        <span>Quản lý chính sách</span>
      </router-link>
      <router-link to="/admin/court-types" class="nav-item" active-class="nav-active">
        <AppIcon name="layers" size="18" />
        <span>Quản lý loại sân</span>
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
    currentTitle() {
      const map = {
        'admin-dashboard': 'Dashboard',
        'admin-profile': 'Thông tin cá nhân',
        'admin-users': 'Quản lý tài khoản',
        'admin-roles': 'Quản lý nhóm quyền',
        'admin-role-detail': 'Chi tiết nhóm quyền',
        'admin-policies': 'Quản lý chính sách',
        'admin-policy-detail': 'Chi tiết chính sách',
        'admin-court-types': 'Quản lý loại sân',
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
  letter-spacing: .04em;
  text-transform: uppercase;
}

:deep(.nav-item svg) {
  color: currentColor;
}
</style>
