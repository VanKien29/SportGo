<template>
  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar" :class="{ open: sidebarOpen }">
      <!-- Brand -->
      <div class="sidebar-brand">
        <div class="brand-icon">
          <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
            <circle cx="16" cy="16" r="15" stroke="#22c55e" stroke-width="2"/>
            <path d="M16 4C20 8 22 12 22 16C22 20 20 24 16 28" stroke="#22c55e" stroke-width="1.5" fill="none"/>
            <path d="M16 4C12 8 10 12 10 16C10 20 12 24 16 28" stroke="#22c55e" stroke-width="1.5" fill="none"/>
            <line x1="4" y1="12" x2="28" y2="12" stroke="#22c55e" stroke-width="1.5"/>
            <line x1="4" y1="20" x2="28" y2="20" stroke="#22c55e" stroke-width="1.5"/>
          </svg>
        </div>
        <div class="brand-info">
          <span class="brand-name">Sport<span class="brand-accent">Go</span></span>
          <span class="brand-sub">{{ brandSub }}</span>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="sidebar-nav">
        <slot name="nav-items">
          <router-link :to="dashboardRoute" class="nav-item" active-class="nav-active">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7" rx="1"/>
              <rect x="14" y="3" width="7" height="7" rx="1"/>
              <rect x="3" y="14" width="7" height="7" rx="1"/>
              <rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            <span>Dashboard</span>
          </router-link>
        </slot>
      </nav>

      <!-- Cluster info (owner only) — with quick-switch dropdown -->
      <div v-if="clusterName" class="sidebar-cluster">
        <div class="cluster-header">
          <div class="cluster-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
              <circle cx="12" cy="10" r="3"/>
            </svg>
            <span class="cluster-name">{{ clusterName }}</span>
          </div>
          <button class="cluster-switch-btn" @click="toggleClusterPicker" title="Đổi cụm sân">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="17 1 21 5 17 9"/>
              <path d="M3 11V9a4 4 0 0 1 4-4h14"/>
              <polyline points="7 23 3 19 7 15"/>
              <path d="M21 13v2a4 4 0 0 1-4 4H3"/>
            </svg>
          </button>
        </div>

        <!-- Cluster quick-switch panel -->
        <transition name="cluster-picker">
          <div v-if="showClusterPicker" class="cluster-picker">
            <div class="cluster-picker-title">Chọn cụm sân</div>
            <button
              v-for="c in clusters"
              :key="c.id"
              class="cluster-option"
              :class="{ active: c.name === clusterName }"
              @click="switchCluster(c)"
            >
              <div class="cluster-option-dot" :class="{ active: c.name === clusterName }"></div>
              <div class="cluster-option-info">
                <div class="cluster-option-name">{{ c.name }}</div>
                <div class="cluster-option-sub">{{ c.courtCount }} sân</div>
              </div>
              <svg v-if="c.name === clusterName" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
            </button>
          </div>
        </transition>

        <!-- Link to full select cluster page -->
        <router-link to="/owner/select-cluster" class="cluster-switch-link" @click="showClusterPicker = false">
          Xem tất cả cụm sân
        </router-link>
      </div>

      <!-- View as user button (owner only) -->
      <div v-if="isOwner" class="sidebar-view-user">
        <button class="view-user-btn" @click="viewAsUser">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
          Xem trang người dùng
        </button>
      </div>

      <!-- User Section -->
      <div class="sidebar-user" @mouseenter="showDropdown = true" @mouseleave="scheduleHide">
        <button class="user-trigger" @click="toggleDropdown">
          <div class="user-avatar">{{ userInitial }}</div>
          <div class="user-info">
            <div class="user-name">{{ user?.fullName }}</div>
            <div class="user-role">{{ roleLabel }}</div>
          </div>
          <svg class="chevron" :class="{ rotated: showDropdown }" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9"/>
          </svg>
        </button>

        <transition name="dd">
          <div v-if="showDropdown" class="user-dropdown" @mouseenter="cancelHide" @mouseleave="scheduleHide">
            <router-link :to="profileRoute" class="dd-item" @click="showDropdown = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
              Thông tin cá nhân
            </router-link>
            <button class="dd-item dd-logout" @click="handleLogout">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
              </svg>
              Đăng xuất
            </button>
          </div>
        </transition>
      </div>
    </aside>

    <!-- Mobile overlay -->
    <div v-if="sidebarOpen" class="overlay" @click="sidebarOpen = false"></div>

    <!-- Main -->
    <main class="main-content">
      <header class="topbar">
        <button class="hamburger" @click="sidebarOpen = !sidebarOpen">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="6" x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
        </button>
        <div class="topbar-title">
          <slot name="topbar-title"></slot>
        </div>
        <!-- Cluster quick info in topbar (owner only) -->
        <div v-if="clusterName" class="topbar-cluster">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
            <circle cx="12" cy="10" r="3"/>
          </svg>
          <span>{{ clusterName }}</span>
        </div>
      </header>
      <div class="content-area">
        <slot></slot>
      </div>
    </main>
  </div>
</template>

<script>
import { getAuth, logout, getSelectedCluster, selectCluster, getClusters } from '../stores/auth.js';

export default {
  name: 'SidebarLayout',
  props: {
    brandSub: { type: String, default: '' },
    dashboardRoute: { type: String, default: '/' },
    clusterName: { type: String, default: '' },
    showClusterSwitch: { type: Boolean, default: false },
  },
  data() {
    return {
      user: getAuth(),
      showDropdown: false,
      sidebarOpen: false,
      hideTimer: null,
      showClusterPicker: false,
      clusters: getClusters(),
    };
  },
  computed: {
    userInitial() {
      return this.user?.fullName?.charAt(0)?.toUpperCase() || '?';
    },
    roleLabel() {
      const map = { admin: 'Quản trị viên', owner: 'Chủ sân', user: 'Người dùng' };
      return map[this.user?.role] || '';
    },
    isOwner() {
      return this.user?.role === 'owner';
    },
    profileRoute() {
      const role = this.user?.role;
      if (role === 'admin') return '/admin/profile';
      if (role === 'owner') return '/owner/profile';
      return '/profile';
    },
  },
  methods: {
    toggleDropdown() {
      this.showDropdown = !this.showDropdown;
    },
    scheduleHide() {
      this.hideTimer = setTimeout(() => { this.showDropdown = false; }, 200);
    },
    cancelHide() {
      if (this.hideTimer) clearTimeout(this.hideTimer);
    },
    toggleClusterPicker() {
      this.showClusterPicker = !this.showClusterPicker;
    },
    switchCluster(cluster) {
      selectCluster(cluster);
      this.showClusterPicker = false;
      // Reload current page to reflect new cluster
      this.$router.push('/owner/dashboard');
    },
    viewAsUser() {
      this.sidebarOpen = false;
      this.$router.push('/');
    },
    async handleLogout() {
      await logout();
      this.$router.push('/login');
    },
  },
};
</script>

<style scoped>
.layout {
  display: flex;
  min-height: 100vh;
}

/* ── Sidebar ── */
.sidebar {
  width: 260px;
  min-width: 260px;
  background: var(--sg-dark);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  z-index: 200;
  transition: transform .25s ease;
}
.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 20px 20px 16px;
  border-bottom: 1px solid rgba(255,255,255,.08);
}
.brand-info {
  display: flex;
  flex-direction: column;
}
.brand-name {
  font-size: 20px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -.4px;
}
.brand-accent {
  color: var(--sg-green);
}
.brand-sub {
  font-size: 11px;
  color: rgba(255,255,255,.45);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: .5px;
  margin-top: 2px;
}

/* ── Nav ── */
.sidebar-nav {
  flex: 1;
  padding: 16px 12px;
  overflow-y: auto;
}
.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-radius: var(--sg-radius-sm);
  font-size: 14px;
  font-weight: 500;
  color: rgba(255,255,255,.6);
  transition: var(--sg-transition);
  margin-bottom: 4px;
}
.nav-item:hover {
  background: rgba(255,255,255,.06);
  color: rgba(255,255,255,.9);
}
.nav-active {
  background: rgba(34,197,94,.15) !important;
  color: var(--sg-green-light) !important;
}

/* ── Cluster Section ── */
.sidebar-cluster {
  padding: 12px 16px;
  border-top: 1px solid rgba(255,255,255,.08);
}
.cluster-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}
.cluster-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  padding: 8px 10px;
  background: rgba(34,197,94,.1);
  border-radius: var(--sg-radius-sm);
  border: 1px solid rgba(34,197,94,.2);
  min-width: 0;
}
.cluster-badge svg {
  color: var(--sg-green);
  min-width: 16px;
}
.cluster-name {
  font-size: 12px;
  font-weight: 600;
  color: var(--sg-green-light);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cluster-switch-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  min-width: 30px;
  border-radius: var(--sg-radius-sm);
  background: rgba(255,255,255,.07);
  color: rgba(255,255,255,.5);
  transition: var(--sg-transition);
}
.cluster-switch-btn:hover {
  background: rgba(34,197,94,.2);
  color: var(--sg-green-light);
}
.cluster-switch-link {
  display: block;
  text-align: center;
  margin-top: 6px;
  font-size: 11px;
  color: rgba(255,255,255,.3);
  transition: var(--sg-transition);
}
.cluster-switch-link:hover {
  color: var(--sg-green-light);
}

/* Cluster Picker Dropdown */
.cluster-picker {
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: var(--sg-radius-sm);
  overflow: hidden;
  margin-top: 4px;
  margin-bottom: 4px;
}
.cluster-picker-title {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  color: rgba(255,255,255,.3);
  padding: 8px 12px 4px;
}
.cluster-option {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 9px 12px;
  text-align: left;
  transition: var(--sg-transition);
  border-top: 1px solid rgba(255,255,255,.04);
}
.cluster-option:hover {
  background: rgba(255,255,255,.07);
}
.cluster-option.active {
  background: rgba(34,197,94,.12);
}
.cluster-option-dot {
  width: 8px;
  height: 8px;
  min-width: 8px;
  border-radius: 50%;
  background: rgba(255,255,255,.2);
}
.cluster-option-dot.active {
  background: var(--sg-green);
}
.cluster-option-info {
  flex: 1;
  min-width: 0;
}
.cluster-option-name {
  font-size: 12px;
  font-weight: 600;
  color: rgba(255,255,255,.85);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cluster-option-sub {
  font-size: 10px;
  color: rgba(255,255,255,.35);
  margin-top: 1px;
}
.cluster-option svg {
  color: var(--sg-green);
  min-width: 14px;
}

/* Cluster picker animation */
.cluster-picker-enter-active, .cluster-picker-leave-active {
  transition: opacity .15s ease, max-height .2s ease;
  max-height: 200px;
  overflow: hidden;
}
.cluster-picker-enter-from, .cluster-picker-leave-to {
  opacity: 0;
  max-height: 0;
}

/* ── View As User Button ── */
.sidebar-view-user {
  padding: 0 12px 8px;
}
.view-user-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 9px 12px;
  border-radius: var(--sg-radius-sm);
  background: rgba(59,130,246,.1);
  border: 1px solid rgba(59,130,246,.2);
  color: #93c5fd;
  font-size: 13px;
  font-weight: 500;
  transition: var(--sg-transition);
  text-align: left;
}
.view-user-btn:hover {
  background: rgba(59,130,246,.2);
  color: #bfdbfe;
}

/* ── User Section ── */
.sidebar-user {
  position: relative;
  padding: 12px;
  border-top: 1px solid rgba(255,255,255,.08);
}
.user-trigger {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px 10px;
  border-radius: var(--sg-radius-sm);
  transition: var(--sg-transition);
  text-align: left;
}
.user-trigger:hover {
  background: rgba(255,255,255,.06);
}
.user-avatar {
  width: 34px;
  height: 34px;
  min-width: 34px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--sg-green), var(--sg-green-dark));
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 13px;
}
.user-info {
  flex: 1;
  min-width: 0;
}
.user-name {
  font-size: 13px;
  font-weight: 600;
  color: rgba(255,255,255,.9);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.user-role {
  font-size: 11px;
  color: rgba(255,255,255,.4);
  margin-top: 1px;
}
.chevron {
  color: rgba(255,255,255,.3);
  transition: transform .2s ease;
}
.chevron.rotated {
  transform: rotate(180deg);
}

/* ── User Dropdown ── */
.user-dropdown {
  position: absolute;
  bottom: calc(100% + 4px);
  left: 12px;
  right: 12px;
  background: var(--sg-white);
  border-radius: var(--sg-radius);
  box-shadow: var(--sg-shadow-xl);
  overflow: hidden;
  z-index: 250;
}
.dd-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 12px 16px;
  font-size: 13px;
  color: var(--sg-text);
  transition: var(--sg-transition);
  text-align: left;
}
.dd-item:hover {
  background: var(--sg-surface);
  color: var(--sg-green-dark);
}
.dd-logout {
  color: var(--sg-danger);
  border-top: 1px solid var(--sg-border);
}
.dd-logout:hover {
  background: #fef2f2;
  color: var(--sg-danger);
}

/* Dropdown transition */
.dd-enter-active, .dd-leave-active {
  transition: opacity .15s ease, transform .15s ease;
}
.dd-enter-from, .dd-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

/* ── Main ── */
.main-content {
  flex: 1;
  margin-left: 260px;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
.topbar {
  height: 60px;
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 0 24px;
  background: var(--sg-white);
  border-bottom: 1px solid var(--sg-border);
  position: sticky;
  top: 0;
  z-index: 50;
}
.hamburger {
  display: none;
  padding: 8px;
  border-radius: var(--sg-radius-sm);
  color: var(--sg-text);
  transition: var(--sg-transition);
}
.hamburger:hover {
  background: var(--sg-surface);
}
.topbar-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--sg-text);
  flex: 1;
}
.topbar-cluster {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  background: rgba(34,197,94,.08);
  border: 1px solid rgba(34,197,94,.2);
  border-radius: var(--sg-radius-full);
  font-size: 12px;
  font-weight: 600;
  color: var(--sg-green-dark);
}
.content-area {
  flex: 1;
  padding: 24px;
}

/* ── Overlay (mobile) ── */
.overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.5);
  z-index: 190;
}

/* ── Responsive ── */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
  }
  .sidebar.open {
    transform: translateX(0);
  }
  .overlay {
    display: block;
  }
  .main-content {
    margin-left: 0;
  }
  .hamburger {
    display: flex;
  }
  .content-area {
    padding: 16px;
  }
  .topbar-cluster {
    display: none;
  }
}
</style>
