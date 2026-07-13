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
import { ownerUiSettingsService } from '../../services/ownerUiSettings.js';

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
  async mounted() {
    enableOwnerThemeScope();
    applyOwnerTheme();
    window.addEventListener('owner-theme-updated', this.syncOwnerTheme);
    await this.loadOwnerTheme();
  },
  beforeUnmount() {
    window.removeEventListener('owner-theme-updated', this.syncOwnerTheme);
    clearOwnerTheme();
  },
  methods: {
    async loadOwnerTheme() {
      try {
        const settings = await ownerUiSettingsService.getSettings();
        applyOwnerTheme(settings);
      } catch {
        applyOwnerTheme();
      }
    },
    syncOwnerTheme(event) {
      applyOwnerTheme(event.detail || {});
    },
  },
};
</script>