<template>
  <div class="layout">
    <aside class="sidebar" :class="{ open: sidebarOpen }">
      <nav class="sidebar-nav">
        <slot name="nav-items">
          <router-link :to="dashboardRoute" class="nav-item" active-class="nav-active">
            <span>Dashboard</span>
          </router-link>
        </slot>
      </nav>

      <div v-if="isOwner" class="sidebar-view-user">
        <button class="view-user-btn" @click="viewAsUser">
          <svg class="icon-btn" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          Xem trang người dùng
        </button>
      </div>

      <div class="sidebar-user" @mouseenter="showDropdown = true" @mouseleave="scheduleHide">
        <button class="user-trigger" @click="toggleDropdown">
          <div class="user-avatar">{{ userInitial }}</div>
          <div class="user-info">
            <div class="user-name">{{ user?.fullName || user?.full_name || user?.username }}</div>
            <div class="user-role">{{ roleLabel }}</div>
          </div>
        </button>

        <transition name="dd">
          <div v-if="showDropdown" class="user-dropdown" @mouseenter="cancelHide" @mouseleave="scheduleHide">
            <router-link :to="profileRoute" class="dd-item" @click="showDropdown = false">
              Thông tin cá nhân
            </router-link>
            <button class="dd-item dd-logout" @click="handleLogout">
              Đăng xuất
            </button>
          </div>
        </transition>
      </div>
    </aside>

    <div v-if="sidebarOpen && isMobile" class="overlay" @click="sidebarOpen = false"></div>

    <main class="main-content">
      <header class="topbar">
        <button class="hamburger" @click="sidebarOpen = !sidebarOpen">☰</button>
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
import { adminLogout, getAuth, logout } from '../stores/auth.js';

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
      const name = this.user?.fullName || this.user?.full_name || this.user?.username || '?';
      return name.charAt(0).toUpperCase();
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
      if (!this.isMobile) this.sidebarOpen = false;
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
      if (this.user?.role === 'admin') {
        await adminLogout();
        this.$router.push('/admin/login');
        return;
      }

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
  background: #ffffff;
  display: flex;
  flex-direction: column;
  position: fixed;
  inset: 0 auto 0 0;
  z-index: 200;
  transition: transform .25s cubic-bezier(0.4, 0, 0.2, 1);
  border-right: 1px solid #e5e7eb;
}

.sidebar-nav {
  flex: 1;
  padding: 24px 16px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

:deep(.nav-item) {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 10px;
  color: rgba(0, 0, 0, 0.6) !important;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.2s ease;
  text-decoration: none;
}

:deep(.nav-item:hover) {
  background: rgba(0, 0, 0, 0.03) !important;
  color: #000000 !important;
}

:deep(.nav-active) {
  background: rgba(0, 0, 0, 0.05) !important;
  color: #000000 !important;
  font-weight: 700;
  border-left: 3px solid #000000;
  padding-left: 13px;
}

.sidebar-view-user {
  padding: 0 16px 12px;
}

.view-user-btn {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  background: rgba(0, 0, 0, 0.02);
  border: 1px solid rgba(0, 0, 0, 0.08);
  color: #000000;
  font-size: 13px;
  font-weight: 600;
  text-align: left;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.view-user-btn:hover {
  background: rgba(0, 0, 0, 0.05);
  color: #000000;
  border-color: rgba(0, 0, 0, 0.15);
}

.icon-btn {
  opacity: 0.8;
}

.sidebar-user {
  position: relative;
  padding: 16px;
  border-top: 1px solid #e5e7eb;
  background: rgba(0, 0, 0, 0.02);
}

.user-trigger {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px;
  border-radius: 10px;
  text-align: left;
  border: none;
  background: transparent;
  cursor: pointer;
  transition: all 0.2s ease;
}

.user-trigger:hover {
  background: rgba(0, 0, 0, 0.03);
}

.user-avatar {
  width: 36px;
  height: 36px;
  min-width: 36px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #000000;
  color: #ffffff;
  font-size: 14px;
  font-weight: 700;
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: rgba(0, 0, 0, 0.8);
  font-size: 13px;
  font-weight: 600;
}

.user-role {
  margin-top: 2px;
  color: rgba(0, 0, 0, 0.4);
  font-size: 11px;
}

.user-dropdown {
  position: absolute;
  left: 16px;
  right: 16px;
  bottom: calc(100% + 8px);
  overflow: hidden;
  border-radius: 12px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  z-index: 250;
}

.dd-item {
  width: 100%;
  display: flex;
  padding: 12px 16px;
  color: rgba(0, 0, 0, 0.8);
  font-size: 13px;
  font-weight: 600;
  text-align: left;
  background: transparent;
  border: none;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s ease;
}

.dd-item:hover {
  background: rgba(0, 0, 0, 0.03);
  color: #000000;
}

.dd-logout {
  color: rgba(0, 0, 0, 0.6);
  border-top: 1px solid #e5e7eb;
}

.dd-logout:hover {
  background: rgba(239, 68, 68, 0.05);
  color: #ef4444;
}



.main-content {
  flex: 1;
  min-height: 100vh;
  margin-left: 260px;
  display: flex;
  flex-direction: column;
}

.topbar {
  height: 60px;
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 0 24px;
  background: #fff;
  border-bottom: 1px solid var(--sg-border);
  position: sticky;
  top: 0;
  z-index: 50;
}

.hamburger {
  display: none;
  padding: 8px;
  border-radius: 8px;
  color: var(--sg-text);
}

.topbar-title {
  flex: 1;
  color: var(--sg-text);
  font-size: 16px;
  font-weight: 800;
}

.content-area {
  flex: 1;
  padding: 24px;
}

.overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, .5);
  z-index: 190;
}

.dd-enter-active,
.dd-leave-active {
  transition: opacity .15s ease, transform .15s ease;
}

.dd-enter-from,
.dd-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

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
}
</style>
