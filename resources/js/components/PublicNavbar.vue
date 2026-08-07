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

        <button
          ref="mobileNavToggle"
          class="mobile-nav-toggle"
          type="button"
          :aria-label="showMobileNav ? 'Đóng menu điều hướng' : 'Mở menu điều hướng'"
          aria-controls="public-mobile-navigation"
          :aria-expanded="showMobileNav"
          @click.stop="toggleMobileNav"
        >
          <svg v-if="!showMobileNav" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h16" />
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="m6 6 12 12M18 6 6 18" />
          </svg>
        </button>

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
          <router-link to="/login" class="sg3-header__button">Đăng nhập</router-link>
        </template>

        <div v-if="user" class="sg3-menu-wrap sg3-notifications">
          <button type="button" class="sg3-icon-button" aria-label="Mở thông báo" :aria-expanded="showNotifDropdown" @click.stop="toggleNotifDropdown">
            <Bell :size="19" aria-hidden="true" />
            <span v-if="unreadCount > 0" class="sg3-notification-badge">{{ unreadCount > 99 ? "99+" : unreadCount }}</span>
          </button>
          <transition name="spg-pop">
            <section v-if="showNotifDropdown" class="sg3-popover-panel">
              <header>
                <div><strong>Thông báo</strong><span>{{ unreadCount ? `${unreadCount} chưa đọc` : "Bạn đã xem hết" }}</span></div>
                <button v-if="unreadCount > 0" type="button" @click="markAllAsRead">Đọc tất cả</button>
              </header>
              <div class="sg3-notification-list">
                <div v-if="notifications.length === 0" class="sg3-notification-empty"><BellOff :size="23" aria-hidden="true" /><p>Chưa có thông báo mới.</p></div>
                <button v-for="notif in notifications" :key="notif.id" type="button" class="sg3-notification-item" :class="{ 'is-unread': !notif.is_read }" @click="markAsRead(notif)">
                  <span class="sg3-notification-item__icon"><CalendarCheck2 :size="16" aria-hidden="true" /></span>
                  <span class="sg3-notification-item__content"><strong>{{ notif.title }}</strong><span>{{ notif.body }}</span><time>{{ formatTime(notif.created_at) }}</time></span>
                  <i v-if="!notif.is_read" aria-label="Chưa đọc"></i>
                </button>
              </div>
            </section>
          </transition>
        </div>

        <div v-if="user" class="sg3-menu-wrap sg3-account-menu">
          <button type="button" class="sg3-account-trigger" aria-label="Mở không gian tài khoản" :aria-expanded="showDropdown" @click.stop="toggleDropdown">
            <span>{{ userInitial }}</span><ChevronDown :size="15" aria-hidden="true" />
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

              <router-link
                v-if="user.role === 'user'"
                to="/bookings"
                class="dd-item"
                @click="showDropdown = false"
              >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <rect x="3" y="5" width="18" height="16" rx="2"/>
                  <path d="M16 3v4M8 3v4M3 10h18"/>
                </svg>
                Lịch đặt sân
              </router-link>

              <router-link
                v-if="user.role === 'user'"
                :to="{ name: 'profile', query: { tab: 'refunds' } }"
                class="dd-item"
                @click="showDropdown = false"
              >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <rect x="2" y="5" width="20" height="14" rx="2"/>
                  <path d="M16 12h4M2 9h20"/>
                </svg>
                Số dư hoàn tiền
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

    <transition name="mobile-nav">
      <div
        v-if="showMobileNav"
        id="public-mobile-navigation"
        class="mobile-nav-panel"
        aria-label="Điều hướng di động"
      >
        <router-link to="/" class="mobile-nav-link" exact-active-class="active-link" @click="closeMobileNav">
          Trang chủ
        </router-link>
        <router-link to="/venues" class="mobile-nav-link" active-class="active-link" @click="closeMobileNav">
          Tìm sân
        </router-link>
        <a href="/#sports" class="mobile-nav-link" @click="closeMobileNav">Môn thể thao</a>
        <router-link to="/news" class="mobile-nav-link" active-class="active-link" @click="closeMobileNav">
          Tin tức
        </router-link>
        <router-link to="/community" class="mobile-nav-link" active-class="active-link" @click="closeMobileNav">
          Cộng đồng
        </router-link>
        <router-link to="/become-partner" class="mobile-nav-link" active-class="active-link" @click="closeMobileNav">
          Chủ sân
        </router-link>
        <a href="/#support" class="mobile-nav-link" @click="closeMobileNav">Hỗ trợ</a>
        <router-link
          v-if="user && user.role === 'user'"
          to="/bookings"
          class="mobile-nav-link"
          active-class="active-link"
          @click="closeMobileNav"
        >
          Lịch đặt sân
        </router-link>
        <router-link
          v-if="user && user.role === 'user'"
          :to="{ name: 'profile', query: { tab: 'refunds' } }"
          class="mobile-nav-link"
          active-class="active-link"
          @click="closeMobileNav"
        >
          Số dư hoàn tiền
        </router-link>
      </div>
    </transition>
  </nav>

  <ComplaintModal
    :is-open="showComplaintModal"
    @close="showComplaintModal = false"
    @success="onComplaintSuccess"
  />
</template>

<script>
import {
  Bell,
  BellOff,
  Building2,
  CalendarCheck2,
  CalendarDays,
  ChevronDown,
  CircleAlert,
  FileUser,
  Home,
  LayoutDashboard,
  LogIn,
  LogOut,
  MapPinned,
  Menu,
  MessagesSquare,
  Newspaper,
  PhoneCall,
  Trophy,
  UserRound,
  UsersRound,
  WalletCards,
  X,
} from "lucide-vue-next";
import { useToast } from "vue-toastification";
import { notificationService } from "../services/notification.service.js";
import { getAuth, logout } from "../stores/auth.js";
import {
  resolveSystemAsset,
  systemName,
  systemProfileState,
} from "../stores/systemProfile.js";
import ComplaintModal from "./ComplaintModal.vue";

export default {
  name: "PublicNavbar",
  components: {
    Bell,
    BellOff,
    Building2,
    CalendarCheck2,
    CalendarDays,
    ChevronDown,
    CircleAlert,
    ComplaintModal,
    FileUser,
    Home,
    LayoutDashboard,
    LogIn,
    LogOut,
    MapPinned,
    Menu,
    MessagesSquare,
    Newspaper,
    PhoneCall,
    Trophy,
    UserRound,
    UsersRound,
    WalletCards,
    X,
  },
  setup() {
    const toast = useToast();
    return { toast };
  },
  data() {
    return {
      user: getAuth(),
      showDropdown: false,
      showNotifDropdown: false,
      showMobileNav: false,
      showComplaintModal: false,
      notifications: [],
      unreadCount: 0,
      notifPollTimer: null,
    };
  },
  computed: {
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
    supportPhone() {
      return systemProfileState.profile.support_phone || "1900 6789";
    },
    supportPhoneRaw() {
      return String(this.supportPhone).replace(/[^\d+]/g, "") || "19006789";
    },
    userInitial() {
      return this.user?.fullName?.charAt(0)?.toUpperCase() || "?";
    },
    roleLabel() {
      const labels = {
        admin: "Quản trị viên",
        owner: "Chủ sân",
        user: "Người chơi",
      };
      return labels[this.user?.role] || "";
    },
    isClientAccount() {
      return Boolean(this.user && this.user.role !== "admin");
    },
    profileRoute() {
      if (!this.user) return "/login";
      return "/profile";
    },
  },
  watch: {
    "$route.fullPath"() {
      this.closeAllMenus();
    },
  },
  mounted() {
    document.addEventListener("pointerdown", this.handleOutsidePointer);
    document.addEventListener("keydown", this.handleEscape);
    window.addEventListener("resize", this.handleViewportResize);
    if (this.user) {
      this.fetchNotifications();
      this.notifPollTimer = window.setInterval(this.fetchNotifications, 30000);
    }
  },
  unmounted() {
    document.removeEventListener("pointerdown", this.handleOutsidePointer);
    document.removeEventListener("keydown", this.handleEscape);
    window.removeEventListener("resize", this.handleViewportResize);
    if (this.notifPollTimer) window.clearInterval(this.notifPollTimer);
  },
  methods: {
    toggleMobileNav() {
      this.showMobileNav = !this.showMobileNav;
      if (this.showMobileNav) {
        this.showDropdown = false;
        this.showNotifDropdown = false;
      }
    },
    closeMobileNav() {
      this.showMobileNav = false;
    },
    handleEscape(event) {
      if (event.key === 'Escape') {
        this.closeMobileNav();
        this.showDropdown = false;
        this.showNotifDropdown = false;
      }
    },
    handleViewportResize() {
      if (window.innerWidth >= 1080) this.closeMobileNav();
    },
    toggleDropdown() {
      this.showDropdown = !this.showDropdown;
      if (this.showDropdown) {
        this.showNotifDropdown = false;
        this.closeMobileNav();
      }
    },
    scheduleHide() {
      this.cancelHide();
      this.hideTimer = setTimeout(() => { this.showDropdown = false; }, 200);
    },
    cancelHide() {
      if (this.hideTimer) clearTimeout(this.hideTimer);
    },
    goToManage() {
      this.showDropdown = false;
      const role = this.user?.role;
      if (role === "admin") {
        this.$router.push("/admin/venue-clusters");
      } else if (role === "owner") {
        this.$router.push("/owner/venue-clusters");
      }
    },
    openComplaintModal() {
      this.showDropdown = false;
      this.showComplaintModal = true;
    },
    onComplaintSuccess() {
      this.showComplaintModal = false;
      this.toast.success(
        "Đã gửi khiếu nại. SportGo sẽ phản hồi trong thời gian sớm nhất.",
      );
    },
    async handleLogout() {
      await logout();
      this.user = null;
      this.closeAllMenus();
      this.$router.push("/login");
    },
    async fetchNotifications() {
      try {
        const response = await notificationService.getNotifications();
        this.notifications = response.data || [];
        this.unreadCount = response.unread_count || 0;
      } catch (error) {
        console.error("Failed to fetch notifications", error);
      }
    },
    async markAsRead(notification) {
      if (!notification.is_read) {
        notification.is_read = true;
        this.unreadCount = Math.max(0, this.unreadCount - 1);
        try {
          await notificationService.markAsRead(notification.id);
        } catch (error) {
          console.error(error);
        }
      }

      const actionUrl = notification.action_url || notification.data?.action_url;
      if (typeof actionUrl === "string" && actionUrl.startsWith("/")) {
        this.$router.push(actionUrl);
        this.showNotifDropdown = false;
      } else if (notif.reference_type === 'partner_application' && notif.reference_id) {
        this.$router.push(`/partner-application/${notif.reference_id}`);
        this.showNotifDropdown = false;
      } else if (notif.type === 'matchmaking_join_request') {
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
      } else if (['post_like', 'post_comment', 'comment_reply'].includes(notif.type)) {
        if (notif.data && notif.data.slug) {
          this.$router.push(`/community/${notif.data.slug}`);
        } else {
          this.$router.push('/community');
        }
        this.showNotifDropdown = false;
      }
    },
    async markAllAsRead() {
      try {
        await notificationService.markAllAsRead();
        this.notifications.forEach((notification) => {
          notification.is_read = true;
        });
        this.unreadCount = 0;
      } catch (error) {
        console.error(error);
      }
    },
    formatTime(dateString) {
      if (!dateString) return "";
      return new Date(dateString).toLocaleString("vi-VN", {
        hour: "2-digit",
        minute: "2-digit",
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
      });
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
  font-weight: 400;
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
  font-weight: 400;
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
  font-weight: 400;
}

.hotline small {
  color: #718078;
  font-size: 11px;
  font-weight: 400;
}

.login-btn,
.register-btn {
  justify-content: center;
  min-height: 40px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 400;
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
  font-weight: 400;
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
  font-weight: 400;
}

.dd-role {
  margin-top: 3px;
  color: #66756d;
  font-size: 12px;
  font-weight: 400;
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
  font-weight: 400;
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
  font-weight: 400;
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
  font-weight: 400;
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
  font-weight: 400;
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

<style>
/* Dark Mode Support for Navbar (Unscoped) */
.dark .navbar {
  background: rgba(9, 9, 11, .94) !important;
  border-bottom: 1px solid rgba(39, 39, 42, .9) !important;
}

.dark .brand-text {
  color: #ffffff !important;
}

.dark .nav-link {
  color: #a1a1aa !important;
}

.dark .nav-link:hover,
.dark .nav-link.active-link {
  color: #10b981 !important;
}

.dark .hotline strong {
  color: #ffffff !important;
}

.dark .hotline small {
  color: #a1a1aa !important;
}

.dark .login-btn {
  background: transparent !important;
  color: #ffffff !important;
  border-color: #27272a !important;
}

.dark .login-btn:hover {
  background: #27272a !important;
}

.dark .notif-btn {
  color: #a1a1aa !important;
}

.dark .notif-btn:hover {
  background: #27272a !important;
  color: #ffffff !important;
}

.dark .brand-icon {
  background: #064e3b !important;
}

.dark .dropdown,
.dark .notif-dropdown {
  background: #18181b !important;
  border-color: #27272a !important;
  box-shadow: 0 24px 56px rgba(0, 0, 0, .8) !important;
}

.dark .dd-name,
.dark .notif-title {
  color: #ffffff !important;
}

.dark .dd-role,
.dark .notif-body,
.dark .notif-time,
.dark .no-notif {
  color: #a1a1aa !important;
}

.dark .dd-divider {
  background: #27272a !important;
}

.dark .dd-item {
  color: #a1a1aa !important;
}

.dark .dd-item:hover {
  background: #27272a !important;
  color: #10b981 !important;
}

.dark .notif-item {
  border-bottom-color: #27272a !important;
}

.dark .notif-item:hover {
  background: #27272a !important;
}

.dark .notif-item.unread {
  background: rgba(14, 165, 233, 0.1) !important;
}
</style>
