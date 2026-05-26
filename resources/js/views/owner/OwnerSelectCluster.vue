<template>
  <div class="select-cluster">
    <div class="sc-header">
      <div class="sc-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
          <circle cx="12" cy="10" r="3"/>
        </svg>
      </div>
      <h1 class="sc-title">Chọn cụm sân</h1>
      <p class="sc-desc">Chọn cụm sân bạn muốn quản lý. Bạn có thể chuyển đổi cụm sân bất cứ lúc nào từ sidebar.</p>
    </div>

    <div class="cluster-grid">
      <button
        v-for="cluster in clusters"
        :key="cluster.id"
        class="cluster-card"
        @click="handleSelect(cluster)"
      >
        <div class="cluster-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
            <circle cx="12" cy="10" r="3"/>
          </svg>
        </div>
        <div class="cluster-info">
          <div class="cluster-name">{{ cluster.name }}</div>
          <div class="cluster-address">{{ cluster.address }}</div>
          <div class="cluster-meta">
            <span class="cluster-courts">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/>
              </svg>
              {{ cluster.courtCount }} sân
            </span>
          </div>
        </div>
        <div class="cluster-arrow">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="9 18 15 12 9 6"/>
          </svg>
        </div>
      </button>
    </div>
  </div>
</template>

<script>
import { getClusters, selectCluster } from '../../stores/auth.js';

export default {
  name: 'OwnerSelectCluster',
  data() {
    return {
      clusters: getClusters(),
    };
  },
  emits: ['cluster-changed'],
  methods: {
    handleSelect(cluster) {
      selectCluster(cluster);
      this.$emit('cluster-changed');
      this.$router.push('/owner/dashboard');
    },
  },
};
</script>

<style scoped>
.select-cluster {
  max-width: 680px;
  margin: 0 auto;
  padding: 20px 0;
}

.sc-header {
  text-align: center;
  margin-bottom: 36px;
}
.sc-icon {
  width: 80px;
  height: 80px;
  border-radius: 20px;
  background: var(--sg-green-pale);
  color: var(--sg-green-dark);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
}
.sc-title {
  font-size: 28px;
  font-weight: 800;
  color: var(--sg-text);
  margin-bottom: 10px;
}
.sc-desc {
  font-size: 15px;
  color: var(--sg-text-muted);
  line-height: 1.6;
  max-width: 460px;
  margin: 0 auto;
}

/* Cluster cards */
.cluster-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.cluster-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px 24px;
  background: var(--sg-white);
  border: 2px solid var(--sg-border);
  border-radius: var(--sg-radius);
  text-align: left;
  transition: var(--sg-transition);
  width: 100%;
}
.cluster-card:hover {
  border-color: var(--sg-green);
  background: rgba(34,197,94,.02);
  box-shadow: 0 4px 20px rgba(34,197,94,.12);
  transform: translateY(-2px);
}
.cluster-icon {
  width: 48px;
  height: 48px;
  min-width: 48px;
  border-radius: 12px;
  background: var(--sg-green-pale);
  color: var(--sg-green-dark);
  display: flex;
  align-items: center;
  justify-content: center;
}
.cluster-card:hover .cluster-icon {
  background: var(--sg-green);
  color: #fff;
}
.cluster-info {
  flex: 1;
  min-width: 0;
}
.cluster-name {
  font-size: 16px;
  font-weight: 700;
  color: var(--sg-text);
  margin-bottom: 4px;
}
.cluster-address {
  font-size: 13px;
  color: var(--sg-text-muted);
  margin-bottom: 8px;
}
.cluster-meta {
  display: flex;
  gap: 16px;
}
.cluster-courts {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  color: var(--sg-green-dark);
  background: var(--sg-green-pale);
  padding: 3px 10px;
  border-radius: var(--sg-radius-full);
}
.cluster-arrow {
  color: var(--sg-border);
  transition: var(--sg-transition);
}
.cluster-card:hover .cluster-arrow {
  color: var(--sg-green);
  transform: translateX(4px);
}
</style>
