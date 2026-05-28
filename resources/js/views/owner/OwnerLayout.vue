<template>
  <SidebarLayout brand-sub="Quản lý sân" dashboard-route="/owner/dashboard">
    <template #nav-items>
      <router-link to="/owner/dashboard" class="nav-item" active-class="nav-active">
        <span>Dashboard</span>
      </router-link>
      <router-link to="/owner/venue-clusters" class="nav-item" active-class="nav-active">
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
  name: 'OwnerLayout',
  components: { SidebarLayout },
  computed: {
    currentTitle() {
      const map = {
        'owner-dashboard': 'Dashboard',
        'owner-profile': 'Thông tin cá nhân',
        'owner-venue-clusters': 'Quản lý cụm sân',
        'owner-venue-courts': 'Quản lý sân con',
      };

      return map[this.$route.name] || 'Chủ sân';
    },
  },
};
</script>
