<template>
  <DoubleSidebar
    :sections="sections"
    :active-route-name="activeRouteName"
    :user="user"
    role-label="Chủ sân"
    show-view-user-btn
    view-user-route="/"
    view-user-label="Xem trang khách"
    @navigate="$emit('navigate')"
  >
    <template #extra>
      <div class="owner-cluster-card">
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
              :class="{ selected: String(selectedClusterId) === String(cluster.id) }"
              @click="selectCluster(cluster.id)"
            >
              {{ cluster.name }}
            </div>
          </div>
        </div>
        <p v-if="selectedCluster?.status === 'locked'" class="locked-warning">
          Cụm sân đang bị khóa. Một số thao tác có thể bị chặn.
        </p>
      </div>
    </template>
  </DoubleSidebar>
</template>

<script>
import DoubleSidebar from '../ui/DoubleSidebar.vue';
import { getAuth } from '../../stores/auth.js';

export default {
  name: 'OwnerSidebar',
  components: { DoubleSidebar },
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
    selectedClusterName() {
      const cluster = this.clusters.find(c => String(c.id) === String(this.selectedClusterId));
      return cluster ? cluster.name : 'Chọn cụm sân';
    },
  },
  methods: {
    toggleDropdown() {
      if (this.clusterLoading) return;
      this.isOpen = !this.isOpen;
    },
    selectCluster(clusterId) {
      this.$emit('cluster-change', clusterId);
      this.isOpen = false;
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
  padding: 12px;
  border: 1px solid #27272a;
  border-radius: 8px;
  background: #141416;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
}

.owner-cluster-card > span {
  color: #a1a1aa;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.owner-cluster-card strong {
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
}

.custom-select-wrapper {
  position: relative;
  width: 100%;
}

.custom-select-trigger {
  min-height: 34px;
  width: 100%;
  border: 1px solid #27272a;
  border-radius: 6px;
  background: #1f1f22;
  color: #ffffff;
  font-size: 12px;
  font-weight: 600;
  padding: 6px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  user-select: none;
  transition: border-color 0.2s, background-color 0.2s;
}

.custom-select-trigger:hover {
  border-color: #52525b;
  background-color: #27272a;
}

.custom-select-trigger.active {
  border-color: #ffffff;
}

.custom-select-trigger .arrow {
  font-size: 8px;
  color: #a1a1aa;
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
  background: #141416;
  border: 1px solid #27272a;
  border-radius: 6px;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
  z-index: 150;
  max-height: 180px;
  overflow-y: auto;
  padding: 4px 0;
}

.custom-option {
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 500;
  color: #a1a1aa;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
  text-align: left;
}

.custom-option:hover {
  background: #1f1f22;
  color: #ffffff;
}

.custom-option.selected {
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
  font-weight: 700;
}

.locked-warning {
  margin: 0;
  color: #ef4444;
  font-size: 11px;
  font-weight: 500;
  line-height: 1.4;
}
</style>

