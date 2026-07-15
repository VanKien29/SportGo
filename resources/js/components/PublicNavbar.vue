<template>
  <nav class="navbar">
    <div class="navbar-inner">
      <div class="navbar-left">
        <router-link to="/" class="brand">
          <div class="brand-icon" aria-hidden="true">
            <img v-if="brandLogo" :src="brandLogo" :alt="brandName" />
            <svg v-else viewBox="0 0 32 32" fill="none">
              <circle cx="16" cy="16" r="15" stroke="currentColor" stroke-width="2"/>
              <path d="m9 12 7-5 7 5-3 8h-8z" stroke="currentColor" stroke-width="1.7" fill="none"/>
              <path d="M9 12 4 15M23 12l5 3M12 20l-3 7M20 20l3 7" stroke="currentColor" stroke-width="1.5"/>
            </svg>
          </div>
          <span class="brand-text">{{ brandMain }}<span v-if="brandAccent">{{ brandAccent }}</span></span>
        </router-link>

        <div class="nav-links">
          <router-link to="/" class="nav-link" exact-active-class="active-link">Trang chủ</router-link>
          <router-link to="/venues" class="nav-link" active-class="active-link">Tìm sân</router-link>
          <a href="/#sports" class="nav-link">Môn thể thao</a>
          <router-link to="/news" class="nav-link" active-class="active-link">Tin tức</router-link>
          <router-link to="/community" class="nav-link" active-class="active-link">Cộng đồng</router-link>
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

        <div v-if="user" class="notification-menu" @mouseenter="showNotifDropdown = true" @mouseleave="scheduleNotifHide">
          <button class="notif-btn" @click="toggleNotifDropdown">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" width="22" height="22">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="currentColor" stroke-width="2"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span v-if="unreadCount > 0" class="notif-badge">{{ unreadCount }}</span>
          </button>

          <transition name="dd">
            <div v-if="showNotifDropdown" class="dropdown notif-dropdown" @mouseenter="cancelNotifHide" @mouseleave="scheduleNotifHide">
              <div class="dropdown-header notif-header">
                <span class="dd-name">Thông báo</span>
                <button v-if="unreadCount > 0" @click="markAllAsRead" class="mark-read-btn">Đánh dấu đã đọc</button>
              </div>
              <div class="dd-divider"></div>
              <div class="notif-list">
                <div v-if="notifications.length === 0" class="no-notif">Không có thông báo nào.</div>
                <div v-for="notif in notifications" :key="notif.id" class="notif-item" :class="{ 'unread': !notif.is_read }" @click="markAsRead(notif)">
                  <div class="notif-content">
                    <div class="notif-title">{{ notif.title }}</div>
                    <div class="notif-body">{{ notif.body }}</div>
                    <div class="notif-time">{{ formatTime(notif.created_at) }}</div>
                  </div>
                  <div v-if="!notif.is_read" class="unread-dot"></div>
                </div>
              </div>
            </div>
          </transition>
        </div>

        <div v-if="user" class="user-menu" @mouseenter="showDropdown = true" @mouseleave="scheduleHide">
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

              <router-link to="/chat" class="dd-item" @click="showDropdown = false">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                Trò chuyện
              </router-link>

              <button
                v-if="user.role === 'user'"
                class="dd-item"
                @click="openComplaintModal"
              >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                  <line x1="12" y1="9" x2="12" y2="13"/>
                  <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                Gửi khiếu nại
              </button>

              <router-link
                v-if="user.role === 'user'"
                to="/partner-application"
                class="dd-item dd-partner"
                @click="showDropdown = false"
              >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M3 21h18"/>
                  <path d="M5 21V7l8-4v18"/>
                  <path d="M19 21V11l-6-4"/>
                  <path d="M9 9h1M9 13h1M9 17h1"/>
                </svg>
                Đăng ký đối tác
              </router-link>

              <router-link
                v-if="user.role === 'owner'"
                to="/owner/partner-profile"
                class="dd-item dd-partner"
                @click="showDropdown = false"
              >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                  <path d="M14 2v6h6"/>
                  <path d="M16 13H8M16 17H8"/>
                </svg>
                Hồ sơ đối tác
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

              <button class="dd-item" @click="toggleThemeMode">
                <svg v-if="!isDark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <circle cx="12" cy="12" r="5"/>
                  <line x1="12" y1="1" x2="12" y2="3"/>
                  <line x1="12" y1="21" x2="12" y2="23"/>
                  <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                  <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                  <line x1="1" y1="12" x2="3" y2="12"/>
                  <line x1="21" y1="12" x2="23" y2="12"/>
                  <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                  <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
                {{ isDark ? 'Chế độ sáng' : 'Chế độ tối' }}
              </button>

              <button class="dd-item dd-logout" @click="handleLogout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
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

  <ComplaintModal 
    :is-open="showComplaintModal" 
    @close="showComplaintModal = false" 
    @success="onComplaintSuccess" 
  />
</template>

<script>
import { getAuth, logout } from "../stores/auth.js";
import { resolveSystemAsset, systemProfileState, systemName } from "../stores/systemProfile.js";
import { notificationService } from "../services/notification.service.js";
import ComplaintModal from "./ComplaintModal.vue";

import { useToast } from "vue-toastification";

export default {
  name: "PublicNavbar",
  components: {
    ComplaintModal
  },
  setup() {
    const toast = useToast();
    return { toast };
  },
  data() {
    return {
      user: getAuth(),
      showDropdown: false,
      hideTimer: null,
      showNotifDropdown: false,
      notifHideTimer: null,
      notifications: [],
      unreadCount: 0,
      notifPollTimer: null,
      showComplaintModal: false,
      isDark: document.documentElement.classList.contains('dark') || document.documentElement.getAttribute('data-theme') === 'dark',
    };
  },
  mounted() {
    if (this.user) {
      this.fetchNotifications();
      this.notifPollTimer = setInterval(this.fetchNotifications, 30000); // Tự động load thông báo mỗi 30s
    }
  },
  unmounted() {
    if (this.notifPollTimer) clearInterval(this.notifPollTimer);
  },
  computed: {
    appliedTheme() {
      if (this.theme === 'dark') return 'dark';
      if (this.theme === 'light') return 'light';
      return this.isDark ? 'dark' : 'light';
    },
    brandName() {
      return systemName();
    },
    brandLogo() {
      return resolveSystemAsset(systemProfileState.profile.logo_url);
    },
    brandMain() {
      const name = this.brandName || "SportGo";
      const match = name.match(/^(.*?)(go)$/i);
      return match ? match[1] : name;
    },
    brandAccent() {
      const match = (this.brandName || "SportGo").match(/^(.*?)(go)$/i);
      return match ? match[2] : "";
    },
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
    toggleThemeMode() {
      const isCurrentlyDark = document.documentElement.classList.contains('dark') || document.documentElement.getAttribute('data-theme') === 'dark';
      if (isCurrentlyDark) {
        document.documentElement.classList.remove('dark');
        document.documentElement.classList.add('light');
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('theme', 'light');
        this.isDark = false;
      } else {
        document.documentElement.classList.add('dark');
        document.documentElement.classList.remove('light');
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');
        this.isDark = true;
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
    goToManage() {
      this.showDropdown = false;
      const role = this.user?.role;
      if (role === "admin") {
        this.$router.push("/admin/dashboard");
      } else if (role === "owner") {
        this.$router.push("/owner/dashboard");
      }
    },
    openComplaintModal() {
      this.showDropdown = false;
      this.showComplaintModal = true;
    },
    onComplaintSuccess() {
      this.showComplaintModal = false;
      this.toast.success("Cảm ơn bạn đã gửi khiếu nại. Chúng tôi sẽ xem xét trong thời gian sớm nhất.");
    },
    async handleLogout() {
      await logout();
      this.user = null;
      this.showDropdown = false;
      this.$router.push("/login");
    },
    toggleNotifDropdown() {
      this.showNotifDropdown = !this.showNotifDropdown;
    },
    scheduleNotifHide() {
      this.notifHideTimer = setTimeout(() => { this.showNotifDropdown = false; }, 200);
    },
    cancelNotifHide() {
      if (this.notifHideTimer) clearTimeout(this.notifHideTimer);
    },
    async fetchNotifications() {
      try {
        const res = await notificationService.getNotifications();
        this.notifications = res.data;
        this.unreadCount = res.unread_count;
      } catch (e) {
        console.error('Failed to fetch notifications', e);
      }
    },
    async markAsRead(notif) {
      if (!notif.is_read) {
        notif.is_read = true;
        this.unreadCount = Math.max(0, this.unreadCount - 1);
        try {
          await notificationService.markAsRead(notif.id);
        } catch (e) {
          console.error(e);
        }
      }
      
      // Navigation handling
      if (notif.type === 'matchmaking_join_request') {
        this.$router.push(`/matchmaking-posts/${notif.reference_id}/manage`);
        this.showNotifDropdown = false;
      } else if (notif.type === 'matchmaking_join_approved' || notif.type === 'matchmaking_join_rejected') {
        this.$router.push('/community');
        this.showNotifDropdown = false;
      } else if (notif.type === 'post_approved') {
        if (notif.reference_type === 'venue_posts') {
          if (notif.data && notif.data.slug) {
            this.$router.push(`/community/${notif.data.slug}`);
          } else {
            this.$router.push('/owner/venue-posts');
          }
        } else if (notif.reference_type === 'community_posts') {
          this.$router.push('/community');
        } else if (notif.reference_type === 'system_posts') {
          if (notif.data && notif.data.slug) {
            this.$router.push(`/news/${notif.data.slug}`);
          } else {
            this.$router.push('/news');
          }
        }
        this.showNotifDropdown = false;
      } else if (notif.type === 'report_processed') {
        if (notif.data && notif.data.target_type && (notif.data.target_type.includes('comment') || notif.data.target_type.includes('post'))) {
          if (notif.data.post_slug) {
            let url = `/community/${notif.data.post_slug}`;
            if (notif.data.target_type.includes('comment') && notif.data.target_id) {
              url += `?open_comment=${notif.data.target_id}`;
            }
            this.$router.push(url);
          }
        }
        this.showNotifDropdown = false;
      }
    },
    async markAllAsRead() {
      try {
        await notificationService.markAllAsRead();
        this.notifications.forEach(n => n.is_read = true);
        this.unreadCount = 0;
      } catch (e) {
        console.error(e);
      }
    },
    formatTime(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleString('vi-VN', { 
        hour: '2-digit', minute: '2-digit', 
        day: '2-digit', month: '2-digit', year: 'numeric' 
      });
    }
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

.brand-icon img {
  width: 100%;
  height: 100%;
  border-radius: inherit;
  object-fit: contain;
  padding: 4px;
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

.nav-link.never-hover-class-placeholder,
.active-link {
  color: #04733f;
}

.nav-link.never-hover-class-placeholder::after,
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

.user-btn.never-hover-class-placeholder {
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

.notification-menu {
  position: relative;
  display: flex;
  align-items: center;
  margin-right: 12px;
}

.notif-btn {
  position: relative;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #475569;
  background: transparent;
  transition: all 0.2s;
}

.notif-btn.never-hover-class-placeholder {
  background: #f1f5f9;
  color: #0f172a;
}

.notif-badge {
  position: absolute;
  top: 4px;
  right: 4px;
  background: #ef4444;
  color: white;
  font-size: 10px;
  font-weight: bold;
  height: 16px;
  min-width: 16px;
  padding: 0 4px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid white;
}

.notif-dropdown {
  width: 320px;
  padding: 0;
}

.notif-header {
  justify-content: space-between;
  padding: 12px 16px;
}

.mark-read-btn {
  font-size: 12px;
  color: #0ea5e9;
  font-weight: 600;
  background: transparent;
}

.mark-read-btn.never-hover-class-placeholder {
  text-decoration: underline;
}

.notif-list {
  max-height: 380px;
  overflow-y: auto;
}

.no-notif {
  padding: 20px;
  text-align: center;
  color: #64748b;
  font-size: 13px;
}

.notif-item {
  padding: 12px 16px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  cursor: pointer;
  transition: background 0.2s;
}

.notif-item.never-hover-class-placeholder {
  background: #f8fafc;
}

.notif-item.unread {
  background: #f0f9ff;
}

.notif-content {
  flex: 1;
  min-width: 0;
}

.notif-title {
  font-weight: 700;
  font-size: 13px;
  color: #0f172a;
  margin-bottom: 4px;
}

.notif-body {
  font-size: 13px;
  color: #475569;
  line-height: 1.4;
  margin-bottom: 6px;
}

.notif-time {
  font-size: 11px;
  color: #94a3b8;
}

.unread-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #0ea5e9;
  flex-shrink: 0;
  margin-top: 4px;
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

.dd-item.never-hover-class-placeholder {
  background: #f6faf8;
  color: #04733f;
}

.dd-manage {
  color: #2563eb;
}

.dd-partner {
  color: #0b7a46;
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
