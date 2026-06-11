<template>
  <SidebarLayout brand-sub="Quản lý sân" dashboard-route="/owner/dashboard">
    <template #nav-items>
      <div class="cluster-box">
        <span>Cụm sân đang quản lý</span>
        <strong v-if="clusters.length <= 1">{{ selectedCluster?.name || 'Chưa có cụm sân' }}</strong>
        <select v-else v-model="selectedClusterId" :disabled="clusterLoading" @change="changeCluster">
          <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">
            {{ cluster.name }}
          </option>
        </select>
        <p v-if="selectedCluster?.status === 'locked'" class="cluster-warning">
          Cụm sân đang bị khóa. Một số thao tác có thể bị chặn.
        </p>
      </div>

      <div class="owner-nav">
        <router-link to="/owner/dashboard" class="nav-item" active-class="nav-active">
          <AppIcon name="dashboard" size="18" />
          <span>Tổng quan</span>
        </router-link>
        <router-link to="/owner/venue-clusters" class="nav-item" active-class="nav-active">
          <AppIcon name="building" size="18" />
          <span>Quản lý cụm sân</span>
        </router-link>
        <router-link to="/owner/venue-courts" class="nav-item" active-class="nav-active">
          <AppIcon name="circleCheck" size="18" />
          <span>Quản lý sân con</span>
        </router-link>
        <router-link to="/owner/pricing" class="nav-item" active-class="nav-active">
          <AppIcon name="settings" size="18" />
          <span>Cấu hình giá</span>
        </router-link>
        <router-link to="/owner/schedule-locks" class="nav-item" active-class="nav-active">
          <AppIcon name="calendar" size="18" />
          <span>Khóa lịch</span>
        </router-link>
        <router-link to="/owner/platform-fees" class="nav-item" active-class="nav-active">
          <AppIcon name="creditCard" size="18" />
          <span>Phí nền tảng</span>
        </router-link>
        <router-link to="/owner/staff" class="nav-item" active-class="nav-active">
          <AppIcon name="users" size="18" />
          <span>Nhân viên sân</span>
        </router-link>
        <router-link to="/owner/vouchers" class="nav-item" active-class="nav-active">
          <AppIcon name="creditCard" size="18" />
          <span>Voucher của sân</span>
        </router-link>
        <router-link to="/owner/policies" class="nav-item" active-class="nav-active">
          <AppIcon name="fileText" size="18" />
          <span>Chính sách sân</span>
        </router-link>
      </div>
    </template>

    <template #topbar-title>
      <span>{{ currentTitle }}</span>
    </template>

    <router-view />
  </SidebarLayout>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import SidebarLayout from '../../components/SidebarLayout.vue';
import { venueClusterService } from '../../services/venueClusters.js';

const SELECTED_CLUSTER_KEY = 'selected_cluster';

export default {
  name: 'OwnerLayout',
  components: { AppIcon, SidebarLayout },
  data() {
    return {
      clusters: [],
      selectedClusterId: '',
      clusterLoading: false,
    };
  },
  computed: {
    selectedCluster() {
      return this.clusters.find((cluster) => cluster.id === this.selectedClusterId) || null;
    },
    currentTitle() {
      const map = {
        'owner-dashboard': 'Tổng quan',
        'owner-profile': 'Thông tin cá nhân',
        'owner-venue-clusters': 'Quản lý cụm sân',
        'owner-venue-courts': 'Quản lý sân con',
        'owner-pricing': 'Cấu hình giá',
        'owner-schedule-locks': 'Khóa lịch theo khung giờ',
        'owner-platform-fees': 'Phí nền tảng',
        'owner-staff': 'Quản lý nhân viên sân',
        'owner-vouchers': 'Voucher của sân',
        'owner-policies': 'Chính sách sân',
      };

      return map[this.$route.name] || 'Chủ sân';
    },
  },
  async mounted() {
    await this.loadClusters();
  },
  methods: {
    async loadClusters() {
      this.clusterLoading = true;
      try {
        const response = await venueClusterService.getClusters();
        this.clusters = response.data || [];
        const savedId = localStorage.getItem(SELECTED_CLUSTER_KEY);
        const fallback = this.clusters[0]?.id || '';
        this.selectedClusterId = this.clusters.some((cluster) => cluster.id === savedId) ? savedId : fallback;
        this.persistCluster();
      } finally {
        this.clusterLoading = false;
      }
    },
    changeCluster() {
      this.persistCluster();
    },
    persistCluster() {
      if (!this.selectedClusterId) return;
      localStorage.setItem(SELECTED_CLUSTER_KEY, this.selectedClusterId);
      window.dispatchEvent(new CustomEvent('owner-cluster-changed', {
        detail: this.selectedCluster,
      }));
    },
  },
};
</script>

<style scoped>
.cluster-box {
  display: grid;
  gap: 8px;
  margin: 4px 4px 14px;
  padding: 12px;
  border: 1px solid rgba(148, 163, 184, .22);
  border-radius: 10px;
  background: rgba(15, 23, 42, .28);
  color: #fff;
}

.cluster-box span {
  color: #94a3b8;
  font-size: 12px;
  font-weight: 800;
}

.cluster-box strong {
  font-size: 14px;
}

.cluster-box select {
  width: 100%;
  border: 1px solid rgba(148, 163, 184, .45);
  border-radius: 8px;
  padding: 9px 10px;
  color: #0f172a;
  background: #fff;
  font-weight: 700;
}

.cluster-warning {
  margin: 0;
  color: #fde68a;
  font-size: 12px;
  line-height: 1.4;
}

.owner-nav {
  display: grid;
  gap: 4px;
}

.owner-nav .nav-item {
  width: 100%;
  min-height: 38px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 8px;
  color: rgba(255, 255, 255, 0.74);
  font-size: 13px;
  font-weight: 750;
  line-height: 1.25;
  text-align: left;
}

.owner-nav .nav-item:hover,
.owner-nav .nav-active {
  background: #059669;
  color: #fff;
}
</style>
