<template>
  <OwnerShell
    :sections="ownerNavigationSections"
    :active-route-name="$route.name"
    :title="currentTitle"
    :section-label="currentSectionLabel"
    :clusters="clusters"
    :selected-cluster-id="selectedClusterId"
    :selected-cluster="selectedCluster"
    :cluster-loading="clusterLoading"
    @cluster-change="changeCluster"
  >
    <router-view />
  </OwnerShell>
</template>

<script>
import OwnerShell from '../../components/owner/OwnerShell.vue';
import {
  getOwnerRouteSectionLabel,
  ownerNavigationSections,
  ownerRouteTitles,
} from '../../config/ownerNavigation.js';
import { venueClusterService } from '../../services/venueClusters.js';

const SELECTED_CLUSTER_KEY = 'selected_cluster';

export default {
  name: 'OwnerLayout',
  components: { OwnerShell },
  data() {
    return {
      ownerNavigationSections,
      clusters: [],
      selectedClusterId: '',
      clusterLoading: false,
    };
  },
  computed: {
    selectedCluster() {
      return this.clusters.find((cluster) => String(cluster.id) === String(this.selectedClusterId)) || null;
    },
    currentTitle() {
      return ownerRouteTitles[this.$route.name] || 'Chủ sân';
    },
    currentSectionLabel() {
      return getOwnerRouteSectionLabel(this.$route.name);
    },
  },
  async mounted() {
    window.addEventListener('owner-cluster-changed', this.syncExternalCluster);
    await this.loadClusters();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.syncExternalCluster);
  },
  methods: {
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
      } finally {
        this.clusterLoading = false;
      }
    },
    changeCluster(clusterId) {
      this.selectedClusterId = clusterId;
      this.persistCluster();
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
    },
  },
};
</script>
