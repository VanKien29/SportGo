<template>
  <SidebarLayout brand-sub="Quản lý sân" dashboard-route="/owner/dashboard">
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
      };

      return map[this.$route.name] || 'Chủ sân';
    },
  },
};
</script>
