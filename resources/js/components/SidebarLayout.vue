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
    <div v-if="sidebarOpen && isMobile" class="overlay" @click="sidebarOpen = false"></div>

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
      </header>
      <div class="content-area">
        <slot></slot>
      </div>
    </main>
  </div>
</template>

<script>
import { getAuth, logout } from '../stores/auth.js';

export default {
  name: 'SidebarLayout',
  props: {
    brandSub: { type: String, default: '' },
    dashboardRoute: { type: String, default: '/' },
  },
  data() {
    return {
      user: getAuth(),
      showDropdown: false,
      sidebarOpen: false,
      isMobile: false,
      hideTimer: null,
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
    updateViewport() {
      this.isMobile = window.innerWidth <= 768;
      if (!this.isMobile) {
        this.sidebarOpen = false;
      }
    },
    toggleDropdown() {
      this.showDropdown = !this.showDropdown;
    },
    scheduleHide() {
      this.hideTimer = setTimeout(() => { this.showDropdown = false; }, 200);
    },
    cancelHide() {
      if (this.hideTimer) clearTimeout(this.hideTimer);
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
  mounted() {
    this.updateViewport();
    window.addEventListener('resize', this.updateViewport);
  },
  beforeUnmount() {
    window.removeEventListener('resize', this.updateViewport);
  },
  watch: {
    $route() {
      this.sidebarOpen = false;
    },
  },
};
</script>

<style scoped>
.layout {
  display: flex;
  min-height: 100vh;
}

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
  pointer-events: none;
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
    pointer-events: auto;
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
}
</style>

