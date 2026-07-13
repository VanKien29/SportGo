<template>
  <aside class="sidebar" aria-label="Owner navigation">
    <RouterLink class="admin-brand" to="/owner/dashboard" @click="$emit('navigate')">
      <span class="admin-brand-mark">SG</span>
      <span class="admin-brand-copy">
        <strong>SportGo</strong>
        <small>Owner Console</small>
      </span>
    </RouterLink>

    <div class="owner-cluster-card" :class="`cluster-mode-${restrictionMode}`">
      <span>Cụm sân đang quản lý</span>
      <strong v-if="clusters.length <= 1">{{ selectedCluster?.name || 'Chưa có cụm sân' }}</strong>
      <div v-else class="custom-select-wrapper">
        <div
          class="custom-select-trigger"
          :class="{ active: isOpen }"
          @click.stop="toggleDropdown"
        >
          <span class="selected-text">{{ selectedClusterName }}</span>
          <span class="arrow" :class="{ open: isOpen }">▼</span>
        </div>
        <div v-if="isOpen" class="custom-options-container">
          <div
            v-for="cluster in clusters"
            :key="cluster.id"
            class="custom-option"
            :class="{
              selected: String(selectedClusterId) === String(cluster.id),
              archived: clusterRestrictionMode(cluster) === 'archived',
            }"
            @click="selectCluster(cluster.id)"
          >
            <span>{{ cluster.name }}</span>
            <small>{{ clusterStatusLabel(cluster) }}</small>
          </div>
        </div>
      </div>
      <small v-if="selectedCluster" class="cluster-state-label">{{ clusterStatusLabel(selectedCluster) }}</small>
      <p v-if="restrictionHint">{{ restrictionHint }}</p>
    </div>

    <nav class="sidebar-nav">
      <section v-for="section in sections" :key="section.label" class="admin-nav-section">
        <p class="nav-group">{{ section.label }}</p>
        <template v-for="item in section.items" :key="item.to">
          <button
            v-if="isItemDisabled(item)"
            class="nav-item nav-disabled"
            type="button"
            disabled
            :title="restrictionHint"
          >
            <AppIcon :name="item.icon" size="17" />
            <span>{{ item.label }}</span>
            <AppIcon name="lock" size="13" />
          </button>
          <RouterLink
            v-else
            class="nav-item"
            :class="{ 'nav-active': isActive(item) }"
            :to="item.to"
            @click="$emit('navigate')"
          >
            <AppIcon :name="item.icon" size="17" />
            <span>{{ item.label }}</span>
          </RouterLink>
        </template>
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
import { venueDisplayStatus, venuePartnerState } from '../../utils/venuePartnerState.js';

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
  data() {
    return {
      isOpen: false,
    };
  },
  mounted() {
    document.addEventListener('click', this.handleOutsideClick);
  },
  beforeUnmount() {
    document.removeEventListener('click', this.handleOutsideClick);
  },
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
    selectedClusterName() {
      const cluster = this.clusters.find(c => String(c.id) === String(this.selectedClusterId));
      return cluster ? cluster.name : 'Chọn cụm sân';
    },
    restrictionMode() {
      return venuePartnerState(this.selectedCluster);
    },
    restrictionHint() {
      if (this.restrictionMode === 'archived') {
        return 'Hợp đồng đã chấm dứt. Cụm sân được giữ để tra cứu, mọi thao tác vận hành đã khóa.';
      }
      if (this.restrictionMode === 'terminating') {
        return 'Cụm sân đang chấm dứt hợp tác. Chỉ còn xử lý hồ sơ, hoàn/hủy và số dư.';
      }
      if (this.selectedCluster?.status === 'locked') {
        return 'Cụm sân đang bị khóa. Mở trang cụm sân để xem lý do hoặc gửi giải trình.';
      }
      return '';
    },
  },
  methods: {
    isActive(item) {
      return item.activeNames?.includes(this.activeRouteName);
    },
    toggleDropdown() {
      if (this.clusterLoading) return;
      this.isOpen = !this.isOpen;
    },
    selectCluster(clusterId) {
      this.$emit('cluster-change', clusterId);
      this.isOpen = false;
    },
    isItemDisabled(item) {
      if (this.restrictionMode === 'normal') return false;

      const viewOnly = ['/owner/dashboard', '/owner/partner-profile', '/owner/venue-clusters'];
      if (this.restrictionMode === 'archived') return !viewOnly.includes(item.to);

      return ![...viewOnly, '/owner/refunds', '/owner/finance'].includes(item.to);
    },
    clusterRestrictionMode(cluster) {
      return venuePartnerState(cluster);
    },
    clusterStatusLabel(cluster) {
      const status = venueDisplayStatus(cluster);
      return {
        pending: 'Chờ duyệt',
        active: 'Đang hoạt động',
        locked: 'Đang khóa',
        termination_locked: 'Đang chấm dứt',
        termination_processing: 'Đang chấm dứt',
        partner_terminated: 'Đã chấm dứt',
      }[status] || 'Chưa xác định';
    },
    handleOutsideClick(e) {
      if (this.isOpen && !this.$el.querySelector('.custom-select-wrapper')?.contains(e.target)) {
        this.isOpen = false;
      }
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
  border-radius: 8px;
  background: #fff;
  box-shadow: var(--admin-shadow-sm);
}

.owner-cluster-card.cluster-mode-terminating {
  background: #fffbeb;
  border-color: #fcd34d;
}

.owner-cluster-card.cluster-mode-archived {
  background: #f1f5f9;
  border-color: #cbd5e1;
  filter: saturate(.35);
}

.owner-cluster-card > span {
  color: var(--admin-faint);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}

.selected-text,
.custom-option > span {
  color: var(--admin-text);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: none;
}

.owner-cluster-card strong {
  color: var(--admin-text);
  font-size: 13px;
  font-weight: 800;
}

.custom-select-wrapper {
  position: relative;
  width: 100%;
}

.custom-select-trigger {
  min-height: 38px;
  width: 100%;
  border: 1px solid #b9cbbb;
  border-radius: var(--admin-radius);
  background: #fff;
  color: var(--admin-text);
  font-size: 13px;
  font-weight: 700;
  padding: 8px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  user-select: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.custom-select-trigger:hover {
  border-color: var(--admin-primary, #10b981);
}

.custom-select-trigger.active {
  border-color: var(--admin-primary, #10b981);
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.15);
}

.custom-select-trigger .arrow {
  font-size: 8px;
  color: var(--admin-faint);
  transition: transform 0.2s;
}

.custom-select-trigger .arrow.open {
  transform: rotate(180deg);
}

.custom-options-container {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  box-shadow: var(--admin-shadow-lg, 0 10px 15px -3px rgba(0, 0, 0, 0.1));
  z-index: 100;
  max-height: 200px;
  overflow-y: auto;
  padding: 4px 0;
}

.custom-option {
  padding: 8px 12px;
  font-size: 13px;
  font-weight: 600;
  color: var(--admin-text);
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
  text-align: left;
}

.custom-option {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
}

.custom-option > span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.custom-option small,
.cluster-state-label {
  color: var(--admin-faint, #64748b);
  font-size: 10px;
  font-weight: 800;
}

.custom-option.archived {
  background: #f8fafc;
  color: #64748b;
}

.custom-option:hover {
  background: var(--admin-surface, #f1f5f9);
}

.custom-option.selected {
  background: var(--admin-primary-faint, rgba(16, 185, 129, 0.08));
  color: var(--admin-primary, #10b981);
  font-weight: 700;
}

.owner-cluster-card p {
  margin: 0;
  color: #9a3412;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.45;
}

.nav-disabled {
  width: 100%;
  border: 0;
  opacity: .45;
  cursor: not-allowed;
  text-align: left;
}

.nav-disabled span {
  flex: 1;
}
</style>
