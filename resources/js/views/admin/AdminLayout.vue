<template>
  <AdminShell
    :sections="navSectionsWithBadges"
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
import {
  pendingCounts,
  startPendingCountsPoll,
  stopPendingCountsPoll,
  badgeLabel,
} from '../../services/adminPendingCounts.js';

import { autoApproveStore } from '../../stores/autoApprove.js';
import { adminUiSettingsService } from '../../services/adminUiSettings.js';
import { applyAuthThemeStyles, applyCustomThemeStyles } from '../../utils/theme.js';

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
    /**
     * Inject badge counts vào từng nav item tương ứng.
     * Dùng deep clone để không mutate config gốc.
     */
    navSectionsWithBadges() {
      return adminNavigationSections.map((section) => ({
        ...section,
        items: section.items.map((item) => {
          let badge = null;

          // Hồ sơ đối tác
          if (item.activeNames?.includes('admin-partner-applications')) {
            badge = badgeLabel(pendingCounts.partner_applications);
          }
          // Cụm sân (approval + location + info changes)
          if (
            item.activeNames?.includes('admin-venue-clusters') ||
            item.activeNames?.includes('admin-venue-cluster-detail')
          ) {
            badge = badgeLabel(pendingCounts.venue_clusters);
          }
          // Hoàn tiền & rút tiền
          if (item.activeNames?.includes('admin-finance-operations')) {
            badge = badgeLabel(pendingCounts.finance);
          }
          // Báo cáo & Khiếu nại
          if (item.activeNames?.includes('admin-reports-complaints')) {
            badge = badgeLabel(pendingCounts.moderation_support);
          }
          // Kiểm duyệt bài viết
          if (item.activeNames?.includes('admin-moderation')) {
            badge = badgeLabel(pendingCounts.detail.moderation_posts);
          }

          return badge ? { ...item, badge } : item;
        }),
      }));
    },
  },
  created() {
    startPendingCountsPoll(60_000);
  },
  beforeUnmount() {
    stopPendingCountsPoll();
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
              radius: data.radius || '8px',
              font_size: data.font_size || '14px',
              font_family: data.font_family || "'Outfit', sans-serif",
            };
            localStorage.setItem('admin-custom-theme', JSON.stringify(themePayload));
          }
          
          if (data.sidebar_style) {
            localStorage.setItem('admin-sidebar-style', data.sidebar_style);
          }
          
          if (data.custom_themes) {
            localStorage.setItem('admin-user-presets', JSON.stringify(data.custom_themes));
          }

          applyAuthThemeStyles();
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
