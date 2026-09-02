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
    workspace-label="Chủ sân"
    role-label="Chủ sân"
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
import { useToast } from 'vue-toastification';

const SELECTED_CLUSTER_KEY = 'selected_cluster';

export default {
  name: 'OwnerLayout',
  components: { OwnerShell },
  setup() {
    return { toast: useToast() };
  },
  data() {
    return {
      ownerNavigationSections,
      clusters: [],
      selectedClusterId: '',
      clusterLoading: false,
      lastRestrictionNoticeKey: '',
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
    window.addEventListener('focus', this.refreshClusterStatuses);
    document.addEventListener('visibilitychange', this.handleVisibilityChange);
    await this.loadClusters();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.syncExternalCluster);
    window.removeEventListener('focus', this.refreshClusterStatuses);
    document.removeEventListener('visibilitychange', this.handleVisibilityChange);
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
        this.notifyClusterRestriction();
      } finally {
        this.clusterLoading = false;
      }
    },
    handleVisibilityChange() {
      if (!document.hidden) this.refreshClusterStatuses();
    },
    async refreshClusterStatuses() {
      if (this.clusterLoading) return;

      try {
        const response = await venueClusterService.getClusters({ compact: 1 });
        const latestById = new Map((response.data || []).map((cluster) => [String(cluster.id), cluster]));
        this.clusters = this.clusters.map((cluster) => {
          const latest = latestById.get(String(cluster.id));
          return latest
            ? { ...cluster, status: latest.status, status_reason: latest.status_reason, access_restriction: latest.access_restriction }
            : cluster;
        });
        this.notifyClusterRestriction();
      } catch {
        // The next page request/action remains authoritative if this
        // opportunistic refresh is unavailable.
      }
    },
    changeCluster(clusterId) {
      this.selectedClusterId = clusterId;
      this.persistCluster();
      this.notifyClusterRestriction();
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
      this.notifyClusterRestriction();
    },
    notifyClusterRestriction() {
      const cluster = this.selectedCluster;
      const restriction = cluster?.access_restriction;
      const status = cluster?.status;
      const isRestricted = Boolean(restriction?.access_mode === 'blocked'
        || restriction?.access_mode === 'limited'
        || ['pending', 'locked', 'termination_locked', 'termination_processing', 'partner_terminated'].includes(status));

      if (!cluster || !isRestricted) {
        this.lastRestrictionNoticeKey = '';
        return;
      }

      const reason = String(restriction?.reason || cluster.status_reason || '').trim();
      let title = 'Cụm sân đang bị hạn chế';
      if (status === 'pending') title = 'Cụm sân chưa sẵn sàng';
      else if (restriction?.access_mode === 'limited') title = 'Cụm sân đang bị giới hạn quyền';
      else if (restriction?.restriction_type === 'platform_fee_overdue') title = 'Cụm sân đang bị khóa do phí nền tảng';
      else if (['termination_locked', 'termination_processing', 'partner_terminated'].includes(status)
        || restriction?.restriction_type === 'contract_termination') title = 'Cụm sân đang chấm dứt hợp đồng';
      else if (restriction?.restriction_type === 'admin_manual') title = 'Cụm sân bị quản trị viên khóa';

      const message = `${cluster.name || 'Cụm sân'}: ${title}.${reason ? ` Lý do: ${reason.replace(/[.\s]+$/, '')}.` : ' Lý do chưa được cập nhật.'}`;
      const noticeKey = `${cluster.id}:${status}:${restriction?.access_mode || ''}:${reason}`;
      if (noticeKey === this.lastRestrictionNoticeKey) return;
      this.lastRestrictionNoticeKey = noticeKey;

      if (restriction?.access_mode === 'limited') this.toast.warning(message, { timeout: 8000 });
      else this.toast.error(message, { timeout: 8000 });
    },
  },
};
</script>
