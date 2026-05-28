<template>
  <div class="layout">
    <aside class="sidebar" :class="{ open: sidebarOpen }">
      <div class="sidebar-brand">
        <div class="brand-icon">SG</div>
        <div class="brand-info">
          <span class="brand-name">Sport<span>Go</span></span>
          <span class="brand-sub">{{ brandSub }}</span>
        </div>
      </div>

      <nav class="sidebar-nav">
        <slot name="nav-items">
          <router-link :to="dashboardRoute" class="nav-item" active-class="nav-active">
            <span>Dashboard</span>
          </router-link>
        </slot>
      </nav>

      <div v-if="isOwner" class="sidebar-view-user">
        <button class="view-user-btn" @click="viewAsUser">Xem trang người dùng</button>
      </div>

      <div class="sidebar-user" @mouseenter="showDropdown = true" @mouseleave="scheduleHide">
        <button class="user-trigger" @click="toggleDropdown">
          <div class="user-avatar">{{ userInitial }}</div>
          <div class="user-info">
            <div class="user-name">{{ user?.fullName || user?.full_name || user?.username }}</div>
            <div class="user-role">{{ roleLabel }}</div>
          </div>
          <span class="chevron" :class="{ rotated: showDropdown }">⌄</span>
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
  background: var(--sg-dark);
  display: flex;
  flex-direction: column;
  position: fixed;
  inset: 0 auto 0 0;
  z-index: 200;
  transition: transform .25s ease;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 20px;
  border-bottom: 1px solid rgba(255, 255, 255, .08);
}

.brand-icon {
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #16a34a;
  color: #fff;
  font-weight: 900;
  font-size: 12px;
}

.brand-info {
  display: flex;
  flex-direction: column;
}

.brand-name {
  font-size: 20px;
  font-weight: 900;
  color: #fff;
}

.brand-name span {
  color: var(--sg-green);
}

.brand-sub {
  margin-top: 2px;
  color: rgba(255, 255, 255, .48);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
}

.sidebar-nav {
  flex: 1;
  padding: 16px 12px;
  overflow-y: auto;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
  padding: 10px 14px;
  border-radius: 8px;
  color: rgba(255, 255, 255, .68);
  font-size: 14px;
  font-weight: 700;
}

.nav-item:hover,
.nav-active {
  background: rgba(34, 197, 94, .16);
  color: #bbf7d0;
}

.sidebar-view-user {
  padding: 0 12px 8px;
}

.view-user-btn {
  width: 100%;
  padding: 9px 12px;
  border-radius: 8px;
  background: rgba(59, 130, 246, .12);
  color: #bfdbfe;
  font-size: 13px;
  font-weight: 700;
  text-align: left;
}

.sidebar-user {
  position: relative;
  padding: 12px;
  border-top: 1px solid rgba(255, 255, 255, .08);
}

.user-trigger {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 8px;
  text-align: left;
}

.user-trigger:hover {
  background: rgba(255, 255, 255, .06);
}

.user-avatar {
  width: 34px;
  height: 34px;
  min-width: 34px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--sg-green), var(--sg-green-dark));
  color: #fff;
  font-size: 13px;
  font-weight: 800;
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: rgba(255, 255, 255, .92);
  font-size: 13px;
  font-weight: 700;
}

.user-role {
  margin-top: 1px;
  color: rgba(255, 255, 255, .45);
  font-size: 11px;
}

.chevron {
  color: rgba(255, 255, 255, .45);
  transition: transform .2s ease;
}

.chevron.rotated {
  transform: rotate(180deg);
}

.user-dropdown {
  position: absolute;
  left: 12px;
  right: 12px;
  bottom: calc(100% + 4px);
  overflow: hidden;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 18px 50px rgba(15, 23, 42, .24);
  z-index: 250;
}

.dd-item {
  width: 100%;
  display: flex;
  padding: 12px 16px;
  color: var(--sg-text);
  font-size: 13px;
  font-weight: 700;
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
