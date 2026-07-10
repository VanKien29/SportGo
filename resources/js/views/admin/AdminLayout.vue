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
};
</script>
