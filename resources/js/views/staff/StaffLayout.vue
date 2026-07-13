<template>
  <OwnerShell
    :sections="staffNavigationSections"
    :active-route-name="$route.name"
    :title="currentTitle"
    :section-label="currentSectionLabel"
    workspace-label="Nhân viên sân"
    role-label="Nhân viên sân"
    home-url="/staff/dashboard"
    profile-url="/staff/profile"
    :show-utility-navigation="false"
  >
    <router-view />
  </OwnerShell>
</template>

<script>
import OwnerShell from '../../components/owner/OwnerShell.vue';
import { staffNavigationSections, staffRouteSections, staffRouteTitles } from '../../config/staffNavigation.js';
import { applyOwnerTheme, clearOwnerTheme, enableOwnerThemeScope } from '../../utils/ownerTheme.js';

export default {
  name: 'StaffLayout',
  components: { OwnerShell },
  data() {
    return { staffNavigationSections };
  },
  computed: {
    currentTitle() {
      return staffRouteTitles[this.$route.name] || 'Nhân viên sân';
    },
    currentSectionLabel() {
      return staffRouteSections[this.$route.name] || 'Công việc';
    },
  },
  mounted() {
    enableOwnerThemeScope();
    applyOwnerTheme();
  },
  beforeUnmount() {
    clearOwnerTheme();
  },
};
</script>