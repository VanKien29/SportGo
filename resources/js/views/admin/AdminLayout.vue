<template>
  <AdminShell
    :sections="adminNavigationSections"
    :title="currentTitle"
    :section-label="currentSectionLabel"
    :active-route-name="String($route.name || '')"
  >
    <router-view :key="$route.fullPath" />
  </AdminShell>
</template>

<script>
import AdminShell from '../../components/admin/AdminShell.vue';
import {
  adminNavigationSections,
  adminRouteTitles,
  findAdminNavigationSection,
} from '../../config/adminNavigation.js';

export default {
  name: 'AdminLayout',
  components: { AdminShell },
  data() {
    return {
      adminNavigationSections,
    };
  },
  computed: {
    currentTitle() {
      return adminRouteTitles[this.$route.name] || 'Admin';
    },
    currentSectionLabel() {
      return findAdminNavigationSection(this.$route.name)?.label || 'Tổng quan';
    },
  },
};
</script>
