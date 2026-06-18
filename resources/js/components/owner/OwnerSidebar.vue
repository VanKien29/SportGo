<template>
  <aside class="sidebar" aria-label="Owner navigation">
    <RouterLink class="admin-brand" to="/owner/dashboard" @click="$emit('navigate')">
      <span class="admin-brand-mark">SG</span>
      <span class="admin-brand-copy">
        <strong>SportGo</strong>
        <small>Owner Console</small>
      </span>
    </RouterLink>

    <div class="owner-cluster-card">
      <span>Cụm sân đang quản lý</span>
      <strong v-if="clusters.length <= 1">{{ selectedCluster?.name || 'Chưa có cụm sân' }}</strong>
      <select
        v-else
        :value="selectedClusterId"
        :disabled="clusterLoading"
        @change="$emit('cluster-change', $event.target.value)"
      >
        <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">
          {{ cluster.name }}
        </option>
      </select>
      <p v-if="selectedCluster?.status === 'locked'">
        Cụm sân đang bị khóa. Một số thao tác có thể bị chặn.
      </p>
    </div>

    <nav class="sidebar-nav">
      <section v-for="section in sections" :key="section.label" class="admin-nav-section">
        <p class="nav-group">{{ section.label }}</p>
        <RouterLink
          v-for="item in section.items"
          :key="item.to"
          class="nav-item"
          :class="{ 'nav-active': isActive(item) }"
          :to="item.to"
          @click="$emit('navigate')"
        >
          <AppIcon :name="item.icon" size="17" />
          <span>{{ item.label }}</span>
        </RouterLink>
      </section>
    </nav>

    <div class="sidebar-view-user">
      <RouterLink class="view-user-btn" to="/" @click="$emit('navigate')">
        <AppIcon name="eye" size="16" />
        <span>Xem trang khách</span>
      </RouterLink>
    </div>

    <div class="sidebar-user">
      <div class="user-avatar">{{ userInitial }}</div>
      <div class="user-info">
        <div class="user-name">{{ userName }}</div>
        <div class="user-role">{{ roleLabel }}</div>
      </div>
    </div>
  </aside>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import { getAuth } from '../../stores/auth.js';

export default {
  name: 'OwnerSidebar',
  components: { AppIcon },
  props: {
    sections: { type: Array, required: true },
    activeRouteName: { type: String, default: '' },
    clusters: { type: Array, default: () => [] },
    selectedClusterId: { type: [String, Number], default: '' },
    selectedCluster: { type: Object, default: null },
    clusterLoading: { type: Boolean, default: false },
  },
  emits: ['cluster-change', 'navigate'],
  computed: {
    user() {
      return getAuth() || {};
    },
    userName() {
      return this.user.fullName || this.user.full_name || this.user.username || 'Chủ sân';
    },
    userInitial() {
      return this.userName.charAt(0).toUpperCase();
    },
    roleLabel() {
      return 'Chủ sân';
    },
  },
  methods: {
    isActive(item) {
      return item.activeNames?.includes(this.activeRouteName);
    },
  },
};
</script>

<style scoped>
.owner-cluster-card {
  display: grid;
  gap: 8px;
  margin: 16px 14px 2px;
  padding: 12px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(226, 246, 232, 0.72));
  box-shadow: var(--admin-shadow-sm);
}

.owner-cluster-card span {
  color: var(--admin-faint);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.owner-cluster-card strong {
  color: var(--admin-text);
  font-size: 13px;
  font-weight: 800;
}

.owner-cluster-card select {
  min-height: 38px;
  width: 100%;
  border: 1px solid #b9cbbb;
  border-radius: var(--admin-radius);
  background: #fff;
  color: var(--admin-text);
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  padding: 8px 10px;
}

.owner-cluster-card p {
  margin: 0;
  color: #9a3412;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.45;
}
</style>
