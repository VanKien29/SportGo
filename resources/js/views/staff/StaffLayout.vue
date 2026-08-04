<template>
  <OwnerShell
    :sections="availableNavigationSections"
    :active-route-name="$route.name"
    :title="currentTitle"
    :section-label="currentSectionLabel"
    :clusters="clusters"
    :selected-cluster-id="selectedClusterId"
    :selected-cluster="selectedCluster"
    :cluster-loading="clusterLoading"
    workspace-label="Nhân viên sân"
    role-label="Nhân viên sân"
    home-url="/staff/dashboard"
    profile-url="/staff/profile"
    :show-utility-navigation="false"
    @cluster-change="changeCluster"
  >
    <router-view />
  </OwnerShell>
</template>

<script>
import OwnerShell from '../../components/owner/OwnerShell.vue';
import { staffNavigationSections, staffRouteSections, staffRouteTitles } from '../../config/staffNavigation.js';
import { applyOwnerTheme, clearOwnerTheme, enableOwnerThemeScope } from '../../utils/ownerTheme.js';
import { ownerUiSettingsService } from '../../services/ownerUiSettings.js';
import { venueClusterService } from '../../services/venueClusters.js';
import { getAuth } from '../../stores/auth.js';
import {
  canAccessStaffMenu,
  canAccessStaffRoute,
  firstAccessibleStaffRoute,
} from '../../config/permissionAccess.js';

const SELECTED_CLUSTER_KEY = 'selected_cluster';

export default {
  name: 'StaffLayout',
  components: { OwnerShell },
  data() {
    return {
      staffNavigationSections,
      clusters: [],
      selectedClusterId: '',
      clusterLoading: false,
    };
  },
  computed: {
    availableNavigationSections() {
      const auth = getAuth();

      return staffNavigationSections
        .map((section) => ({
          ...section,
          items: section.items.filter((item) =>
            canAccessStaffMenu(auth, this.selectedClusterId, item.menuKey),
          ),
        }))
        .filter((section) => section.items.length > 0);
    },
    selectedCluster() {
      return this.clusters.find((cluster) => String(cluster.id) === String(this.selectedClusterId)) || null;
    },
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
    window.addEventListener('owner-cluster-changed', this.syncExternalCluster);
    window.addEventListener('owner-theme-updated', this.syncOwnerTheme);
    await this.loadOwnerTheme();
    await this.loadClusters();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.syncExternalCluster);
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
    async loadClusters() {
      this.clusterLoading = true;
      try {
        const response = await venueClusterService.getClusters({ compact: 1 });
        this.clusters = response.data || [];
        const savedId = localStorage.getItem(SELECTED_CLUSTER_KEY);
        const fallback = this.clusters[0]?.id || '';
        const hasSavedCluster = this.clusters.some((cluster) => String(cluster.id) === String(savedId));
        this.selectedClusterId = hasSavedCluster ? savedId : fallback;
        this.persistCluster({ notify: !hasSavedCluster });
        this.ensureCurrentRouteAllowed();
      } finally {
        this.clusterLoading = false;
      }
    },
    changeCluster(clusterId) {
      this.selectedClusterId = clusterId;
      this.persistCluster();
      this.ensureCurrentRouteAllowed();
    },
    persistCluster({ notify = true } = {}) {
      if (!this.selectedClusterId) return;
      localStorage.setItem(SELECTED_CLUSTER_KEY, this.selectedClusterId);
      if (!notify) return;
      window.dispatchEvent(new CustomEvent('owner-cluster-changed', {
        detail: this.selectedCluster,
      }));
    },
    syncExternalCluster(event) {
      const clusterId = event.detail?.id;
      if (!clusterId || String(clusterId) === String(this.selectedClusterId)) return;
      if (!this.clusters.some((cluster) => String(cluster.id) === String(clusterId))) return;
      this.selectedClusterId = clusterId;
      localStorage.setItem(SELECTED_CLUSTER_KEY, clusterId);
      this.ensureCurrentRouteAllowed();
    },
    ensureCurrentRouteAllowed() {
      const auth = getAuth();
      if (canAccessStaffRoute(this.$route.name, auth, this.selectedClusterId)) return;

      const destination = firstAccessibleStaffRoute(
        auth,
        this.selectedClusterId,
        staffNavigationSections,
      );
      if (destination !== this.$route.path) {
        this.$router.replace(destination);
      }
    },
  },
};
</script>
