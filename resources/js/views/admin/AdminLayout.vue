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

import { autoApproveStore } from '../../stores/autoApprove.js';
import { adminUiSettingsService } from '../../services/adminUiSettings.js';
import { applyCustomThemeStyles } from '../../utils/theme.js';

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
  async mounted() {
    document.body?.classList.add('sg-admin-theme-scope');
    autoApproveStore.init();
    await this.syncUiSettings();
  },
  unmounted() {
    document.body?.classList.remove('sg-admin-theme-scope');
    autoApproveStore.stop();
  },
  methods: {
    async syncUiSettings() {
      try {
        const data = await adminUiSettingsService.getSettings();
        if (data) {
          const activeThemeId = data.active_theme_id || 'zinc';
          const presets = data.presets || [];
          const customThemes = data.custom_themes || [];
          const allPresets = [...presets, ...customThemes];
          
          let activePreset = allPresets.find(p => p.id === activeThemeId);
          if (!activePreset && presets.length > 0) {
            activePreset = presets[0];
          }

          if (activePreset) {
            const themePayload = {
              light: activePreset.light,
              dark: activePreset.dark,
              radius: data.radius || '8px'
            };
            localStorage.setItem('admin-custom-theme', JSON.stringify(themePayload));
          }
          
          if (data.sidebar_style) {
            localStorage.setItem('admin-sidebar-style', data.sidebar_style);
          }
          
          if (data.custom_themes) {
            localStorage.setItem('admin-user-presets', JSON.stringify(data.custom_themes));
          }

          applyCustomThemeStyles();
          window.dispatchEvent(new Event('sidebar-style-changed'));
        }
      } catch (e) {
        console.error('Failed to sync UI settings from DB', e);
      }
    }
  }
};
</script>
