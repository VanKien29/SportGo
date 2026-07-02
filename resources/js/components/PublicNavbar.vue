<template>
  <nav class="navbar">
    <div class="navbar-inner">
      <div class="navbar-left">
        <router-link to="/" class="brand">
          <div class="brand-icon" aria-hidden="true">
            <svg viewBox="0 0 32 32" fill="none">
              <circle cx="16" cy="16" r="15" stroke="currentColor" stroke-width="2"/>
              <path d="m9 12 7-5 7 5-3 8h-8z" stroke="currentColor" stroke-width="1.7" fill="none"/>
              <path d="M9 12 4 15M23 12l5 3M12 20l-3 7M20 20l3 7" stroke="currentColor" stroke-width="1.5"/>
            </svg>
          </div>
          <span class="brand-text">Sport<span>Go</span></span>
        </router-link>

        <div class="nav-links">
          <router-link to="/" class="nav-link" exact-active-class="active-link">Trang chủ</router-link>
          <router-link to="/venues" class="nav-link" active-class="active-link">Tìm sân</router-link>
          <a href="/#sports" class="nav-link">Môn thể thao</a>
          <a href="/#news" class="nav-link">Tin tức</a>
          <router-link to="/become-partner" class="nav-link" active-class="active-link">Chủ sân</router-link>
          <a href="/#support" class="nav-link">Hỗ trợ</a>
          <router-link
            v-if="user && user.role === 'user'"
            to="/bookings"
            class="nav-link"
            active-class="active-link"
          >
            Lịch đặt sân
          </router-link>
        </div>
      </div>

      <div class="navbar-right">
        <router-link to="/become-partner" class="hotline owner-entry">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 10h.01M15 10h.01"/>
          </svg>
          <span>
            <strong>Chủ sân</strong>
            <small>Quản lý & nhận booking</small>
          </span>
        </router-link>

        <template v-if="!user">
          <router-link to="/login" class="login-btn">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
              <polyline points="10 17 15 12 10 7"/>
              <line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
            Đăng nhập
          </router-link>
          <router-link to="/register" class="register-btn">Đăng ký</router-link>
        </template>

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

              <router-link :to="profileRoute" class="dd-item" @click="showDropdown = false">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
                Thông tin cá nhân
              </router-link>

              <button v-if="user.role === 'owner'" class="dd-item dd-manage" @click="goToManage">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                  <circle cx="12" cy="10" r="3"/>
                </svg>
                Quay lại quản lý sân
              </button>

              <button v-if="user.role === 'admin'" class="dd-item dd-manage" @click="goToManage">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <rect x="3" y="3" width="7" height="7" rx="1"/>
                  <rect x="14" y="3" width="7" height="7" rx="1"/>
                  <rect x="3" y="14" width="7" height="7" rx="1"/>
                  <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Quay lại quản trị
              </button>

              <button class="dd-item dd-logout" @click="handleLogout">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
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
import { getAuth, logout } from "../stores/auth.js";

export default {
  name: "PublicNavbar",
  data() {
    return {
      user: getAuth(),
      showDropdown: false,
      hideTimer: null,
    };
  },
  computed: {
    userInitial() {
      return this.user?.fullName?.charAt(0)?.toUpperCase() || "?";
    },
    roleLabel() {
      const map = { admin: "Quản trị viên", owner: "Chủ sân", user: "Người dùng" };
      return map[this.user?.role] || "";
    },
    profileRoute() {
      if (!this.user) return "/login";
      if (this.user.role === "owner") return "/owner/profile";
      return "/profile";
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
      if (role === "admin") {
        this.$router.push("/admin/dashboard");
      } else if (role === "owner") {
        this.$router.push("/owner/dashboard");
      }
    },
    async handleLogout() {
      await logout();
      this.user = null;
      this.showDropdown = false;
      this.$router.push("/login");
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
  z-index: 100;
  height: 64px;
  border-bottom: 1px solid rgba(226, 232, 240, .9);
  background: rgba(255, 255, 255, .94);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
}

.navbar-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  max-width: 1440px;
  height: 100%;
  margin: 0 auto;
  padding: 0 34px;
}

.navbar-left,
.navbar-right,
.brand,
.nav-links,
.hotline,
.login-btn,
.register-btn {
  display: flex;
  align-items: center;
}

.navbar-left {
  gap: 54px;
  min-width: 0;
}

.brand {
  gap: 10px;
  color: #0b7a46;
  text-decoration: none;
}

.brand-icon {
  display: grid;
  width: 38px;
  height: 38px;
  place-items: center;
  border-radius: 50%;
  background: #e7f8ef;
}

.brand-icon svg {
  width: 28px;
  height: 28px;
}

.brand-text {
  color: #102015;
  font-size: 24px;
  font-weight: 950;
  letter-spacing: 0;
}

.brand-text span {
  color: #0b8f50;
}

.nav-links {
  gap: 20px;
}

.nav-link {
  position: relative;
  padding: 22px 4px 20px;
  color: #1f2937;
  font-size: 14px;
  font-weight: 850;
  text-decoration: none;
  transition: color .18s ease;
  white-space: nowrap;
}

.nav-link::after {
  content: "";
  position: absolute;
  left: 4px;
  right: 4px;
  bottom: 12px;
  height: 2px;
  border-radius: 999px;
  background: transparent;
}

.nav-link:hover,
.active-link {
  color: #04733f;
}

.nav-link:hover::after,
.active-link::after {
  background: #14a461;
}

.navbar-right {
  gap: 12px;
}

.hotline {
  gap: 10px;
  color: #0d7d48;
  text-decoration: none;
}

.hotline svg,
.login-btn svg,
.dd-item svg {
  width: 18px;
  height: 18px;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 2;
}

.hotline span {
  display: grid;
  gap: 1px;
}

.hotline strong {
  color: #111827;
  font-size: 14px;
  font-weight: 950;
}

.hotline small {
  color: #718078;
  font-size: 11px;
  font-weight: 750;
}

.login-btn,
.register-btn {
  justify-content: center;
  min-height: 40px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 900;
  text-decoration: none;
}

.login-btn {
  gap: 8px;
  padding: 0 18px;
  border: 1px solid #d8e3dc;
  background: #fff;
  color: #111827;
}

.register-btn {
  padding: 0 20px;
  background: #0d8c51;
  color: #fff;
  box-shadow: 0 10px 22px rgba(13, 140, 81, .18);
}

.user-menu {
  position: relative;
}

.user-btn {
  padding: 4px;
  border-radius: 50%;
  transition: background .18s ease;
}

.user-btn:hover {
  background: #e7f8ef;
}

.user-avatar,
.dd-avatar {
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: linear-gradient(135deg, #16a765, #04733f);
  color: #fff;
  font-weight: 900;
}

.user-avatar {
  width: 38px;
  height: 38px;
  font-size: 14px;
}

.dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 270px;
  overflow: hidden;
  border: 1px solid #e1e8e4;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 24px 56px rgba(15, 23, 42, .14);
}

.dropdown-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
}

.dd-avatar {
  width: 42px;
  min-width: 42px;
  height: 42px;
  font-size: 16px;
}

.dd-name {
  color: #111827;
  font-size: 14px;
  font-weight: 900;
}

.dd-role {
  margin-top: 3px;
  color: #66756d;
  font-size: 12px;
  font-weight: 750;
}

.dd-divider {
  height: 1px;
  margin: 0 16px;
  background: #edf2ef;
}

.dd-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 12px 16px;
  color: #26332b;
  font-size: 14px;
  font-weight: 800;
  text-align: left;
  text-decoration: none;
  transition: background .18s ease, color .18s ease;
}

.dd-item:hover {
  background: #f6faf8;
  color: #04733f;
}

.dd-manage {
  color: #2563eb;
}

.dd-logout {
  color: #dc2626;
}

.dd-enter-active,
.dd-leave-active {
  transition: opacity .15s ease, transform .15s ease;
}

.dd-enter-from,
.dd-leave-to {
  opacity: 0;
  transform: translateY(-8px) scale(.96);
}

@media (max-width: 980px) {
  .navbar-inner {
    padding: 0 20px;
  }

  .nav-links,
  .hotline {
    display: none;
  }
}

@media (max-width: 560px) {
  .navbar {
    height: 58px;
  }

  .brand-icon {
    width: 34px;
    height: 34px;
  }

  .brand-text {
    font-size: 20px;
  }

  .login-btn {
    padding: 0 12px;
  }

  .register-btn {
    display: none;
  }
}
</style>
