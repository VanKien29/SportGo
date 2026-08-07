<template>
  <AdminShell
    :sections="filteredNavigationSections"
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
import { adminUiSettingsService } from '../../services/adminUiSettings.js';
import { getAuth } from '../../stores/auth.js';
import { hasAllAdminPermissions } from '../../config/permissionAccess.js';

export default {
  name: 'AdminLayout',
  components: { AdminShell },
  data() {
    return {
      adminNavigationSections,
    };
  },
  computed: {
    filteredNavigationSections() {
      const auth = getAuth();

      return adminNavigationSections
        .map((section) => ({
          ...section,
          items: section.items.filter((item) =>
            hasAllAdminPermissions(auth, item.permissionCodes || []),
          ),
        }))
        .filter((section) => section.items.length > 0);
    },
    currentTitle() {
      return adminRouteTitles[this.$route.name] || 'Admin';
    },
    currentSectionLabel() {
      return findAdminNavigationSection(this.$route.name)?.label || 'Tổng quan';
    },
  },
  mounted() {
    document.body?.classList.add('sg-admin-theme-scope');
    if (typeof applyCustomThemeStyles === 'function') {
      applyCustomThemeStyles();
    }
    if (hasAllAdminPermissions(getAuth(), ['ui_settings.view'])) {
      window.requestAnimationFrame(() => this.syncUiSettings());
    }
  },
  unmounted() {
    document.body?.classList.remove('sg-admin-theme-scope');
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
              radius: ['0px', '4px', '6px'].includes(data.radius) ? data.radius : '4px'
            };
            localStorage.setItem('admin-custom-theme', JSON.stringify(themePayload));
          }
          
          if (data.sidebar_style) {
            localStorage.setItem('admin-sidebar-style', data.sidebar_style);
          }
          
          if (data.custom_themes) {
            localStorage.setItem('admin-user-presets', JSON.stringify(data.custom_themes));
          }

          window.dispatchEvent(new Event('sidebar-style-changed'));
        }
      } catch (e) {
        console.error('Failed to sync UI settings from DB', e);
      }
    }
  }
};
</script>
