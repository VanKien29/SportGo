<template>
  <nav class="navbar">
    <div class="navbar-inner">
      <!-- Brand + Nav links -->
      <div class="navbar-left">
        <router-link to="/" class="brand">
          <div class="brand-icon">
            <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
              <circle cx="16" cy="16" r="15" stroke="#22c55e" stroke-width="2"/>
              <path d="M16 4 C20 8 22 12 22 16 C22 20 20 24 16 28" stroke="#22c55e" stroke-width="1.5" fill="none"/>
              <path d="M16 4 C12 8 10 12 10 16 C10 20 12 24 16 28" stroke="#22c55e" stroke-width="1.5" fill="none"/>
              <line x1="4" y1="12" x2="28" y2="12" stroke="#22c55e" stroke-width="1.5"/>
              <line x1="4" y1="20" x2="28" y2="20" stroke="#22c55e" stroke-width="1.5"/>
            </svg>
          </div>
          <span class="brand-text">Sport<span class="brand-accent">Go</span></span>
        </router-link>
        <div class="nav-links">
          <router-link to="/" class="nav-link" exact-active-class="active-link">Trang chủ</router-link>
          <router-link to="/booking" class="nav-link" active-class="active-link" v-if="user && user.role === 'user'">Lịch & Đặt sân</router-link>
        </div>
      </div>

      <!-- Right: Login or User Menu -->
      <div class="navbar-right">
        <router-link v-if="!user" to="/login" class="login-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
            <polyline points="10 17 15 12 10 7"/>
            <line x1="15" y1="12" x2="3" y2="12"/>
          </svg>
          Đăng nhập
        </router-link>

        <div v-else class="user-menu" @mouseenter="showDropdown = true" @mouseleave="scheduleHide">
          <button class="user-btn" @click="toggleDropdown">
            <div class="user-avatar">{{ userInitial }}</div>
          </button>

          <transition name="dd">
            <div v-if="showDropdown" class="dropdown" @mouseenter="cancelHide" @mouseleave="scheduleHide">
              <div class="dropdown-header">
                <div class="dd-avatar">{{ userInitial }}</div>
                <div class="dd-info">
                  <div class="dd-name">{{ user.fullName }}</div>
                  <div class="dd-role">{{ roleLabel }}</div>
                </div>
              </div>
              <div class="dd-divider"></div>

              <!-- Profile link - đến đúng profile theo role -->
              <router-link :to="profileRoute" class="dd-item" @click="showDropdown = false">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
                Thông tin cá nhân
              </router-link>

              <!-- Owner: Quay lại quản lý sân -->
              <button v-if="user.role === 'owner'" class="dd-item dd-manage" @click="goToManage">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                  <circle cx="12" cy="10" r="3"/>
                </svg>
                Quay lại quản lý sân
              </button>

              <!-- Admin: Quay lại quản trị -->
              <button v-if="user.role === 'admin'" class="dd-item dd-manage" @click="goToManage">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="3" width="7" height="7" rx="1"/>
                  <rect x="14" y="3" width="7" height="7" rx="1"/>
                  <rect x="3" y="14" width="7" height="7" rx="1"/>
                  <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Quay lại quản trị
              </button>

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
      </div>
    </div>
  </nav>
</template>

<script>
import { getAuth, logout } from '../stores/auth.js';

export default {
  name: 'PublicNavbar',
  data() {
    return {
      user: getAuth(),
      showDropdown: false,
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
    profileRoute() {
      if (!this.user) return '/login';
      // Dùng chung /profile vì Profile.vue sẽ render đúng layout theo role
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
    goToManage() {
      this.showDropdown = false;
      const role = this.user?.role;
      if (role === 'admin') {
        this.$router.push('/admin/dashboard');
      } else if (role === 'owner') {
        this.$router.push('/owner/dashboard');
      }
    },
    async handleLogout() {
      await logout();
      this.user = null;
      this.showDropdown = false;
      this.$router.push('/login');
    },
  },
};
</script>

<style scoped>
.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 64px;
  background: rgba(255,255,255,.92);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--sg-border);
  z-index: 100;
}
.navbar-inner {
  max-width: 1280px;
  margin: 0 auto;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
}
.navbar-left {
  display: flex;
  align-items: center;
  gap: 32px;
}
.brand {
  display: flex;
  align-items: center;
  gap: 10px;
}
.brand-text {
  font-size: 22px;
  font-weight: 800;
  color: var(--sg-dark);
  letter-spacing: -.5px;
}
.brand-accent {
  color: var(--sg-green);
}
.nav-links {
  display: flex;
  gap: 8px;
}
.nav-link {
  padding: 8px 16px;
  border-radius: var(--sg-radius-sm);
  font-size: 14px;
  font-weight: 500;
  color: var(--sg-text-muted);
  transition: var(--sg-transition);
}
.nav-link:hover,
.active-link {
  color: var(--sg-green-dark);
  background: var(--sg-green-pale);
}
.navbar-right {
  display: flex;
  align-items: center;
}
.login-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 20px;
  background: var(--sg-green);
  color: #fff;
  border-radius: var(--sg-radius-full);
  font-size: 14px;
  font-weight: 600;
  transition: var(--sg-transition);
}
.login-btn:hover {
  background: var(--sg-green-dark);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(34,197,94,.4);
}
.user-menu {
  position: relative;
}
.user-btn {
  padding: 4px;
  border-radius: 50%;
  transition: var(--sg-transition);
}
.user-btn:hover {
  background: var(--sg-green-pale);
}
.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--sg-green), var(--sg-green-dark));
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
}
.dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 260px;
  background: var(--sg-white);
  border-radius: var(--sg-radius);
  border: 1px solid var(--sg-border);
  box-shadow: var(--sg-shadow-xl);
  overflow: hidden;
}
.dropdown-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
}
.dd-avatar {
  width: 40px;
  height: 40px;
  min-width: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--sg-green), var(--sg-green-dark));
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 16px;
}
.dd-name {
  font-weight: 600;
  font-size: 14px;
  color: var(--sg-text);
}
.dd-role {
  font-size: 12px;
  color: var(--sg-text-muted);
  margin-top: 2px;
}
.dd-divider {
  height: 1px;
  background: var(--sg-border);
  margin: 0 16px;
}
.dd-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 12px 16px;
  font-size: 14px;
  color: var(--sg-text);
  transition: var(--sg-transition);
  text-align: left;
}
.dd-item:hover {
  background: var(--sg-surface);
  color: var(--sg-green-dark);
}
.dd-manage {
  color: #2563eb;
  font-weight: 500;
}
.dd-manage:hover {
  background: #eff6ff;
  color: #1d4ed8;
}
.dd-logout {
  color: var(--sg-danger);
}
.dd-logout:hover {
  background: #fef2f2;
  color: var(--sg-danger);
}

/* Transition */
.dd-enter-active, .dd-leave-active {
  transition: opacity .15s ease, transform .15s ease;
}
.dd-enter-from, .dd-leave-to {
  opacity: 0;
  transform: translateY(-8px) scale(.95);
}

@media (max-width: 640px) {
  .navbar-inner { padding: 0 16px; }
  .nav-links { display: none; }
  .brand-text { font-size: 18px; }
}
</style>
