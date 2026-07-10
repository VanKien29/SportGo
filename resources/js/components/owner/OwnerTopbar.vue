<template>
  <header class="topbar">
    <div class="topbar-left">
      <!-- Mobile hamburger -->
      <button class="hamburger" type="button" title="Mở menu" @click="$emit('toggle-sidebar')">
        <AppIcon name="menu" size="21" />
      </button>

      <!-- Desktop collapse toggle -->
      <button
        class="collapse-toggle"
        type="button"
        :title="sidebarCollapsed ? 'Mở rộng sidebar' : 'Thu nhỏ sidebar'"
        @click="$emit('toggle-collapse')"
      >
        <AppIcon :name="sidebarCollapsed ? 'panelLeftOpen' : 'panelLeftClose'" size="18" />
      </button>

      <div class="admin-crumbs" aria-label="Breadcrumb">
        <span>Owner</span>
        <AppIcon name="chevronRight" size="13" />
        
        <!-- Cluster Selector Dropdown in Breadcrumbs -->
        <div v-if="clusters.length > 0" class="topbar-cluster-selector">
          <div v-if="clusters.length <= 1" class="single-cluster">
            {{ selectedCluster?.name || 'Chưa có cụm sân' }}
          </div>
          <div v-else class="custom-dropdown">
            <button
              class="dropdown-trigger"
              :class="{ active: dropdownOpen, 'is-locked': selectedCluster?.status === 'locked' }"
              @click.stop="toggleDropdown"
              type="button"
            >
              <AppIcon v-if="selectedCluster?.status === 'locked'" name="lock" size="13" class="lock-icon" />
              <span class="selected-text">{{ selectedClusterName }}</span>
              <AppIcon name="chevronDown" size="12" class="arrow" :class="{ open: dropdownOpen }" />
            </button>
            <transition name="slide-fade">
              <div v-if="dropdownOpen" class="dropdown-menu">
                <button
                  v-for="cluster in clusters"
                  :key="cluster.id"
                  class="dropdown-item"
                  :class="{ selected: String(selectedClusterId) === String(cluster.id) }"
                  @click="selectCluster(cluster.id)"
                  type="button"
                >
                  <AppIcon
                    name="check"
                    size="14"
                    class="check-icon"
                    :style="{ opacity: String(selectedClusterId) === String(cluster.id) ? 1 : 0 }"
                  />
                  <span>{{ cluster.name }}</span>
                </button>
              </div>
            </transition>
          </div>
        </div>

        <AppIcon v-if="clusters.length > 0" name="chevronRight" size="13" />
        <span>{{ sectionLabel || 'Tổng quan' }}</span>
        <AppIcon name="chevronRight" size="13" />
        <strong>{{ title }}</strong>
      </div>
    </div>

    <div class="topbar-actions">
      <ThemeToggle />

      <UserProfileDropdown
        :user="user"
        profile-url="/owner/profile"
        billing-url="/owner/billing"
        settings-url="/owner/settings"
        @logout="handleLogout"
      />
    </div>
  </header>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import ThemeToggle from '../ui/ThemeToggle.vue';
import UserProfileDropdown from '../ui/UserProfileDropdown.vue';
import { logout, getAuth } from '../../stores/auth.js';

export default {
  name: 'OwnerTopbar',
  components: { AppIcon, ThemeToggle, UserProfileDropdown },
  props: {
    title: { type: String, required: true },
    sectionLabel: { type: String, default: '' },
    sidebarCollapsed: { type: Boolean, default: false },
    clusters: { type: Array, default: () => [] },
    selectedClusterId: { type: [String, Number], default: '' },
    selectedCluster: { type: Object, default: null },
    clusterLoading: { type: Boolean, default: false },
  },
  emits: ['toggle-sidebar', 'toggle-collapse', 'cluster-change'],
  data() {
    return {
      dropdownOpen: false,
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
    async handleLogout() {
      await logout();
      this.$router.push('/login');
    },
    toggleDropdown() {
      if (this.clusterLoading) return;
      this.dropdownOpen = !this.dropdownOpen;
    },
    selectCluster(clusterId) {
      this.$emit('cluster-change', clusterId);
      this.dropdownOpen = false;
    },
    handleOutsideClick(e) {
      if (this.dropdownOpen && !this.$el.querySelector('.custom-dropdown')?.contains(e.target)) {
        this.dropdownOpen = false;
      }
    },
  },
};
</script>

<style scoped>
.topbar-cluster-selector {
  display: inline-flex;
  align-items: center;
}

.single-cluster {
  font-weight: 500;
  color: var(--admin-text);
  font-size: 13px;
}

.custom-dropdown {
  position: relative;
}

.dropdown-trigger {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--admin-bg-soft, rgba(0, 0, 0, 0.04));
  border: 1px solid var(--admin-border, #e2e8f0);
  border-radius: 6px;
  padding: 4px 10px;
  color: var(--admin-text, #1e293b);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  outline: none;
  user-select: none;
  min-height: 28px;
}

.dropdown-trigger.never-hover-class-placeholder {
  background: var(--admin-hover, rgba(0, 0, 0, 0.08));
  border-color: var(--admin-primary, #3b82f6);
}

.dropdown-trigger.active {
  background: var(--admin-surface, #fff);
  border-color: var(--admin-primary, #3b82f6);
  box-shadow: 0 0 0 3px var(--admin-primary-ring, rgba(59, 130, 246, 0.15));
}

.dropdown-trigger.is-locked {
  border-color: var(--admin-danger, #ef4444);
  color: var(--admin-danger, #ef4444);
  background: var(--admin-danger-soft, rgba(239, 68, 68, 0.08));
}

.dropdown-trigger.is-locked .lock-icon {
  margin-right: 2px;
  color: var(--admin-danger, #ef4444);
}

.dropdown-trigger .arrow {
  color: var(--admin-muted, #64748b);
  transition: transform 0.2s ease;
}

.dropdown-trigger .arrow.open {
  transform: rotate(180deg);
}

.dropdown-menu {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  min-width: 220px;
  background: var(--admin-surface, #fff);
  border: 1px solid var(--admin-border, #e2e8f0);
  border-radius: 8px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  padding: 6px 4px;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  gap: 2px;
  transform-origin: top left;
}

[data-theme="dark"] .dropdown-trigger {
  background: rgba(255, 255, 255, 0.05);
}
[data-theme="dark"] .dropdown-trigger.never-hover-class-placeholder {
  background: rgba(255, 255, 255, 0.08);
}

.dropdown-item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  border: none;
  background: transparent;
  padding: 8px 10px;
  border-radius: 6px;
  color: var(--admin-text, #1e293b);
  font-size: 13px;
  font-weight: 400;
  text-align: left;
  cursor: pointer;
  transition: all 0.15s ease;
  outline: none;
}

.dropdown-item.never-hover-class-placeholder {
  background: var(--admin-bg-soft, rgba(0, 0, 0, 0.04));
}

.dropdown-item.selected {
  background: var(--admin-primary-soft, rgba(59, 130, 246, 0.08));
  color: var(--admin-primary, #3b82f6);
  font-weight: 500;
}

.check-icon {
  color: var(--admin-primary, #3b82f6);
  flex-shrink: 0;
  transition: opacity 0.15s ease;
}

/* Transition Animations */
.slide-fade-enter-active {
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-fade-leave-active {
  transition: all 0.15s cubic-bezier(0.5, 0, 0.75, 0);
}
.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateY(-4px) scale(0.96);
  opacity: 0;
}
</style>
