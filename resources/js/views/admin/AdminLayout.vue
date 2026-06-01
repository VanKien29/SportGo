<template>
  <SidebarLayout brand-sub="Quản trị hệ thống" dashboard-route="/admin/dashboard">
    <template #nav-items>
      <router-link to="/admin/dashboard" class="nav-item" active-class="nav-active">
        <span>Dashboard</span>
      </router-link>
      <router-link to="/admin/users" class="nav-item" active-class="nav-active">
        <span>Quản lý tài khoản</span>
      </router-link>
      <router-link to="/admin/court-types" class="nav-item" active-class="nav-active">
        <span>Quản lý loại sân</span>
      </router-link>
      <router-link to="/admin/venue-clusters" class="nav-item" active-class="nav-active">
        <span>Quản lý cụm sân</span>
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

export default {
  name: 'AdminLayout',
  components: { SidebarLayout },
  computed: {
    currentTitle() {
      const map = {
        'admin-dashboard': 'Dashboard',
        'admin-profile': 'Thông tin cá nhân',
        'admin-users': 'Quản lý tài khoản',
        'admin-court-types': 'Quản lý loại sân',
        'admin-venue-clusters': 'Quản lý cụm sân',
        'admin-venue-cluster-detail': 'Chi tiết cụm sân',
      };

      return map[this.$route.name] || 'Admin';
    },
  },
};
</script>

<style src="../../../css/admin/layout.css" scoped></style>
