<template>
  <header class="alb-header">
    <!-- Main Navigation Bar -->
    <nav class="alb-navbar">
      <div class="alb-navbar__inner sg-container">
        <!-- Brand Logo Only -->
        <router-link to="/" class="alb-brand" aria-label="SportGo Trang chủ">
          <img v-if="brandLogo" :src="brandLogo" :alt="brandName" class="alb-brand__logo" />
          <div v-else class="alb-brand__mark">SG</div>
        </router-link>

        <!-- Main Navigation Links (Desktop) -->
        <div class="alb-nav-links alb-desktop-only">
          <router-link to="/" class="alb-nav-link" exact-active-class="active-link">Trang chủ</router-link>
          <router-link to="/venues" class="alb-nav-link" active-class="active-link">Tìm sân</router-link>
          <router-link to="/news" class="alb-nav-link" active-class="active-link">Tin tức</router-link>
          <router-link to="/community" class="alb-nav-link" active-class="active-link">Cộng đồng</router-link>
          <router-link to="/offers" class="alb-nav-link" active-class="active-link">Ưu đãi</router-link>
          <router-link to="/about" class="alb-nav-link" active-class="active-link">Về chúng tôi</router-link>
          <router-link to="/contact" class="alb-nav-link" active-class="active-link">Liên hệ</router-link>
          <router-link v-if="!isOwner && !isAdmin" to="/become-partner" class="alb-btn-owner">
            <span>Đăng ký Chủ sân</span>
          </router-link>
          <router-link v-else-if="isOwner" to="/owner/dashboard" class="alb-btn-owner">
            <span>Vào trang chủ sân</span>
          </router-link>
        </div>

        <!-- User Actions (Desktop) -->
        <div class="alb-nav-actions alb-desktop-only">
          <!-- Messaging / Chat Link -->
          <router-link v-if="user" to="/chat" class="sg3-icon-button" style="text-decoration: none;" title="Hộp thư tin nhắn">
            <span style="font-size: 13px; font-weight: 500; color: #111827;">Tin nhắn</span>
          </router-link>

          <!-- Notification Bell Link -->
          <div v-if="user" class="sg3-menu-wrap sg3-notifications">
            <button type="button" class="sg3-icon-button" aria-label="Mở thông báo" @click.stop="toggleNotifDropdown">
              <span style="font-size: 13px; font-weight: 500; color: #111827;">Thông báo</span>
              <span v-if="unreadCount > 0" class="sg3-notification-badge">{{ unreadCount > 99 ? "99+" : unreadCount }}</span>
            </button>
            <transition name="spg-pop">
              <section v-if="showNotifDropdown" class="sg3-popover-panel">
                <header>
                  <div><strong>Thông báo</strong><span>{{ unreadCount ? `${unreadCount} chưa đọc` : "Bạn đã xem hết" }}</span></div>
                  <button v-if="unreadCount > 0" type="button" @click="markAllAsRead">Đọc tất cả</button>
                </header>
                <div class="sg3-notification-list">
                  <div v-if="notifications.length === 0" class="sg3-notification-empty"><p>Chưa có thông báo mới.</p></div>
                  <button
                    v-for="notif in notifications"
                    :key="notif.id"
                    type="button"
                    class="sg3-notification-item"
                    :class="{ 'is-unread': !isNotifRead(notif) }"
                    @click="markAsRead(notif)"
                  >
                    <span class="sg3-notification-item__content">
                      <strong>{{ getNotifTitle(notif) }}</strong>
                      <span>{{ getNotifBody(notif) }}</span>
                      <time v-if="notif.created_at">{{ formatTime(notif.created_at) }}</time>
                    </span>
                  </button>
                </div>
              </section>
            </transition>
          </div>

          <!-- Guests -->
          <template v-if="!user">
            <router-link to="/login" class="anc-btn-login" style="color: #111827;">Đăng nhập</router-link>
            <router-link to="/register" class="anc-btn-register">Đăng ký</router-link>
          </template>

          <!-- User Account Menu -->
          <div v-if="user" class="sg3-menu-wrap sg3-account-menu">
            <button type="button" class="sg3-account-trigger" @click.stop="showDropdown = !showDropdown">
              <span class="avatar-circle" :style="!userAvatarUrl ? { backgroundColor: userAvatarBg } : {}">
                <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="user.fullName" class="nav-avatar-img" @error="onNavAvatarError" />
                <span v-else>{{ userInitial }}</span>
              </span>
              <span class="user-name-text" style="color: #111827;">{{ user.fullName }}</span>
            </button>

            <transition name="dd">
              <div v-if="showDropdown" class="dropdown" @click.stop>
                <div class="dropdown-header">
                  <div class="dd-avatar" :style="!userAvatarUrl ? { backgroundColor: userAvatarBg } : {}">
                    <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="user.fullName" class="nav-avatar-img" @error="onNavAvatarError" />
                    <span v-else>{{ userInitial }}</span>
                  </div>
                  <div class="dd-info">
                    <div class="dd-name">{{ user.fullName }}</div>
                    <div class="dd-role">{{ roleLabel }}</div>
                  </div>
                </div>

                <router-link :to="profileRoute" class="dd-item" @click="showDropdown = false">
                  Thông tin cá nhân
                </router-link>

                <router-link v-if="isClientUser" to="/bookings" class="dd-item" @click="showDropdown = false">
                  Lịch đặt sân
                </router-link>

                <router-link v-if="isClientUser" to="/wallet" class="dd-item" @click="showDropdown = false">
                  Ví SportGo
                </router-link>

                <router-link v-if="isClientUser" to="/refunds" class="dd-item" @click="showDropdown = false">
                  Yêu cầu hoàn tiền
                </router-link>

                <router-link v-if="isClientUser" to="/notifications" class="dd-item" @click="showDropdown = false">
                  Thông báo
                </router-link>

                <router-link v-if="isClientUser" to="/complaints" class="dd-item" @click="showDropdown = false">
                  Khiếu nại & Hỗ trợ
                </router-link>

                <router-link to="/chat" class="dd-item" @click="showDropdown = false">
                  Trò chuyện
                </router-link>

                <router-link v-if="isOwner" to="/owner/dashboard" class="dd-item" @click="showDropdown = false">
                  Kênh Chủ sân
                </router-link>

                <router-link v-if="isStaff" to="/staff/bookings" class="dd-item" @click="showDropdown = false">
                  Bàn làm việc Nhân viên
                </router-link>

                <button v-if="isAdmin" type="button" class="dd-item" @click="goToDashboard">
                  Trang quản trị
                </button>

                <button type="button" class="dd-item logout" @click="handleLogout">
                  Đăng xuất
                </button>
              </div>
            </transition>
          </div>
        </div>

        <!-- Mobile Hamburger Button -->
        <button
          type="button"
          class="alb-mobile-toggle"
          :aria-expanded="mobileMenuOpen"
          aria-label="Mở menu điều hướng"
          @click.stop="mobileMenuOpen = !mobileMenuOpen"
        >
          <svg v-if="!mobileMenuOpen" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0f172a" stroke-width="2">
            <line x1="4" y1="6" x2="20" y2="6"/>
            <line x1="4" y1="12" x2="20" y2="12"/>
            <line x1="4" y1="18" x2="20" y2="18"/>
          </svg>
          <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0f172a" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>

        <!-- Mobile Drawer Navigation -->
        <transition name="sg-mobile-nav">
          <div v-if="mobileMenuOpen" class="alb-mobile-menu" @click.stop>
            <div class="alb-mobile-nav-links">
              <router-link to="/" class="alb-mobile-nav-link" exact-active-class="active-link" @click="mobileMenuOpen = false">Trang chủ</router-link>
              <router-link to="/venues" class="alb-mobile-nav-link" active-class="active-link" @click="mobileMenuOpen = false">Tìm sân</router-link>
              <router-link to="/news" class="alb-mobile-nav-link" active-class="active-link" @click="mobileMenuOpen = false">Tin tức</router-link>
              <router-link to="/community" class="alb-mobile-nav-link" active-class="active-link" @click="mobileMenuOpen = false">Cộng đồng</router-link>
              <router-link to="/offers" class="alb-mobile-nav-link" active-class="active-link" @click="mobileMenuOpen = false">Ưu đãi</router-link>
              <router-link to="/about" class="alb-mobile-nav-link" active-class="active-link" @click="mobileMenuOpen = false">Về chúng tôi</router-link>
              <router-link to="/contact" class="alb-mobile-nav-link" active-class="active-link" @click="mobileMenuOpen = false">Liên hệ</router-link>
            </div>

            <div class="alb-mobile-actions">
              <router-link v-if="!isOwner && !isAdmin" to="/become-partner" class="alb-btn-owner sg-w-full" @click="mobileMenuOpen = false">
                <span>Đăng ký Chủ sân</span>
              </router-link>
              <router-link v-else-if="isOwner" to="/owner/dashboard" class="alb-btn-owner sg-w-full" @click="mobileMenuOpen = false">
                <span>Vào trang chủ sân</span>
              </router-link>

              <template v-if="!user">
                <div class="alb-mobile-auth-row">
                  <router-link to="/login" class="anc-btn-login" @click="mobileMenuOpen = false">Đăng nhập</router-link>
                  <router-link to="/register" class="anc-btn-register" @click="mobileMenuOpen = false">Đăng ký</router-link>
                </div>
              </template>

              <template v-else>
                <div class="alb-mobile-user-row">
                  <div class="dd-avatar" :style="!userAvatarUrl ? { backgroundColor: userAvatarBg } : {}">
                    <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="user.fullName" class="nav-avatar-img" @error="onNavAvatarError" />
                    <span v-else>{{ userInitial }}</span>
                  </div>
                  <div class="dd-info">
                    <div class="dd-name">{{ user.fullName }}</div>
                    <div class="dd-role">{{ roleLabel }}</div>
                  </div>
                </div>
                <div class="alb-mobile-user-links">
                  <router-link :to="profileRoute" class="dd-item" @click="mobileMenuOpen = false">Thông tin cá nhân</router-link>
                  <router-link v-if="isClientUser" to="/bookings" class="dd-item" @click="mobileMenuOpen = false">Lịch đặt sân</router-link>
                  <router-link v-if="isClientUser" to="/wallet" class="dd-item" @click="mobileMenuOpen = false">Ví SportGo</router-link>
                  <router-link v-if="isClientUser" to="/notifications" class="dd-item" @click="mobileMenuOpen = false">Thông báo</router-link>
                  <button type="button" class="dd-item logout" @click="handleLogout">Đăng xuất</button>
                </div>
              </template>
            </div>
          </div>
        </transition>
      </div>
    </nav>

    <!-- Complaint Modal -->
    <ComplaintModal
      :is-open="showComplaintModal"
      @close="showComplaintModal = false"
    />
  </header>
</template>

<script>
import { notificationService } from "../services/notification.service.js";
import { getAuth, logout } from "../stores/auth.js";
import { resolveSystemAsset, systemName, systemProfileState } from "../stores/systemProfile.js";
import { getAvatarColorHex, getAvatarInitial } from "../utils/avatar.js";
import ComplaintModal from "./ComplaintModal.vue";

export default {
  name: "PublicNavbar",
  components: {
    ComplaintModal,
  },
  data() {
    return {
      user: getAuth(),
      showDropdown: false,
      showNotifDropdown: false,
      showComplaintModal: false,
      mobileMenuOpen: false,
      notifications: [],
      unreadCount: 0,
      notifTimer: null,
      notificationLoadTimer: null,
      notificationsLoading: false,
      notificationsLoaded: false,
    };
  },
  computed: {
    brandName() {
      return systemName() || "SportGo";
    },
    brandLogo() {
      return resolveSystemAsset(systemProfileState.profile.logo_url);
    },
    supportPhone() {
      return systemProfileState.profile.support_phone || "1900 6789";
    },
    supportPhoneRaw() {
      return String(this.supportPhone).replace(/[^\d+]/g, "") || "19006789";
    },
    userInitial() {
      return getAvatarInitial(this.user?.fullName);
    },
    userAvatarBg() {
      return getAvatarColorHex(this.user?.fullName);
    },
    userAvatarUrl() {
      const path = this.user?.avatar_url || this.user?.avatarUrl || this.user?.avatar;
      if (!path) return null;
      if (path.startsWith('http') || path.startsWith('/')) return path;
      return `/storage/${path}`;
    },
    roleLabel() {
      const labels = { admin: "Quản trị viên", owner: "Chủ sân", user: "Người chơi", customer: "Người chơi" };
      return labels[this.user?.role] || "Tài khoản";
    },
    isClientUser() {
      return Boolean(this.user);
    },
    isOwner() {
      return this.user?.role === "owner" || this.user?.role_group === "owner";
    },
    isStaff() {
      return this.user?.role === "venue_staff" || this.user?.role === "staff" || this.user?.role_group === "staff";
    },
    isAdmin() {
      return this.user?.role === "admin" || this.user?.role_group === "admin";
    },
    profileRoute() {
      return "/profile";
    },
  },
  watch: {
    "$route.fullPath"() {
      this.showDropdown = false;
      this.showNotifDropdown = false;
      this.mobileMenuOpen = false;
    },
  },
  mounted() {
    document.addEventListener("pointerdown", this.handleOutside);
    if (this.user) {
      // Notifications are secondary UI. Let the page render first and only
      // start polling if the user stays on the current screen.
      this.notificationLoadTimer = setTimeout(() => {
        this.fetchNotifications();
        this.notifTimer = setInterval(this.fetchNotifications, 30000);
      }, 300);
    }
  },
  beforeUnmount() {
    document.removeEventListener("pointerdown", this.handleOutside);
    if (this.notificationLoadTimer) clearTimeout(this.notificationLoadTimer);
    if (this.notifTimer) clearInterval(this.notifTimer);
  },
  methods: {
    onNavAvatarError() {
      if (this.user) {
        this.user.avatar_url = null;
        this.user.avatarUrl = null;
        this.user.avatar = null;
      }
    },
    handleOutside(event) {
      if (!event.target.closest(".sg3-account-menu")) this.showDropdown = false;
      if (!event.target.closest(".sg3-notifications")) this.showNotifDropdown = false;
      if (!event.target.closest(".alb-navbar")) this.mobileMenuOpen = false;
    },
    toggleNotifDropdown() {
      this.showNotifDropdown = !this.showNotifDropdown;
      this.showDropdown = false;
      if (this.showNotifDropdown && this.user && !this.notificationsLoaded) {
        this.fetchNotifications();
      }
    },
    isNotifRead(n) {
      if (!n) return true;
      if (typeof n.is_read === "boolean") return n.is_read;
      return Boolean(n.read_at);
    },
    getNotifTitle(n) {
      return n?.title || n?.data?.title || "Thông báo hệ thống";
    },
    getNotifBody(n) {
      return n?.content || n?.body || n?.data?.message || n?.data?.content || "Không có nội dung chi tiết.";
    },
    async fetchNotifications() {
      if (this.notificationsLoading) return;
      this.notificationsLoading = true;
      try {
        const res = await notificationService.getNotifications();
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.notifications = list;
        this.unreadCount = typeof res?.unread_count === "number"
          ? res.unread_count
          : list.filter((n) => !this.isNotifRead(n)).length;
      } catch (error) {
        // silent
      } finally {
        this.notificationsLoaded = true;
        this.notificationsLoading = false;
      }
    },
    async markAllAsRead() {
      try {
        await notificationService.markAllAsRead();
        this.notifications.forEach((n) => {
          n.is_read = true;
          n.read_at = new Date().toISOString();
        });
        this.unreadCount = 0;
      } catch (error) {
        // silent
      }
    },
    async markAsRead(notif) {
      if (!this.isNotifRead(notif)) {
        try {
          await notificationService.markAsRead(notif.id);
          notif.is_read = true;
          notif.read_at = new Date().toISOString();
          this.unreadCount = Math.max(0, this.unreadCount - 1);
        } catch (e) {
          // silent
        }
      }
      this.showNotifDropdown = false;
      const target = this.resolveNotificationTarget(notif);
      if (target) {
        await this.$router.push(target).catch(() => {});
      }
    },
    resolveNotificationTarget(notif) {
      const candidates = [
        notif?.action_url,
        notif?.data?.action_url,
        notif?.data?.url,
        notif?.data?.link,
      ];
      const explicitTarget = candidates.find((target) => typeof target === "string" && target.startsWith("/"));
      if (explicitTarget) return explicitTarget;

      const type = `${notif?.reference_type || ""} ${notif?.type || ""}`.toLowerCase();
      const referenceId = notif?.reference_id || notif?.data?.booking_id;
      if (referenceId && type.includes("booking")) return `/booking/${referenceId}`;
      if (referenceId && type.includes("refund")) return `/refunds/${referenceId}`;
      if (referenceId && type.includes("complaint")) return `/complaints/${referenceId}`;
      if (referenceId && type.includes("partner_application")) return `/partner-application/${referenceId}`;
      if (referenceId && type.includes("player_post")) {
        return type.includes("participant") || type.includes("matchmaking_request")
          ? `/matchmaking-requests/${referenceId}`
          : `/matchmaking-posts/${referenceId}/manage`;
      }
      if (type.includes("post_like") || type.includes("post_comment") || type.includes("comment_reply")) {
        return notif?.data?.slug ? `/community/${notif.data.slug}` : "/community";
      }
      if (type.includes("post_approved")) {
        return notif?.data?.slug && type.includes("system_post")
          ? `/news/${notif.data.slug}`
          : "/community";
      }
      if (type.includes("wallet") || type.includes("membership")) {
        return type.includes("membership") ? "/vip-membership" : "/wallet";
      }
      return "/notifications";
    },
    goToDashboard() {
      this.showDropdown = false;
      if (this.user?.role === "admin") {
        this.$router.push("/admin/dashboard");
      } else if (this.user?.role === "staff") {
        this.$router.push("/staff/dashboard");
      } else {
        this.$router.push("/owner/dashboard");
      }
    },
    async handleLogout() {
      await logout();
      this.user = null;
      this.showDropdown = false;
      this.$router.push("/login");
    },
    formatTime(dateString) {
      if (!dateString) return "";
      return new Date(dateString).toLocaleString("vi-VN", {
        hour: "2-digit",
        minute: "2-digit",
        day: "2-digit",
        month: "2-digit",
      });
    },
  },
};
</script>

<style scoped>
/* ACCOUNT MENU & DROPDOWN FIXES */
.sg3-menu-wrap {
  position: relative;
}

.sg3-account-trigger {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
  transition: background 0.15s ease;
}

.sg3-account-trigger:hover {
  background: #f1f5f9;
}

.avatar-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #54656f;
  color: #ffffff;
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}

.nav-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
  display: block;
}

.user-name-text {
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
}

/* GUEST BUTTONS */
.anc-btn-login {
  display: inline-flex;
  align-items: center;
  color: #475569 !important;
  font-size: 13.5px;
  font-weight: 600;
  text-decoration: none;
  padding: 6px 12px;
  transition: color 0.15s ease;
}

.anc-btn-login:hover {
  color: #0f172a !important;
}

.anc-btn-register {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #54656f;
  color: #ffffff !important;
  font-size: 13px;
  font-weight: 600;
  padding: 6px 16px;
  border-radius: 999px;
  text-decoration: none;
  box-shadow: 0 2px 8px rgba(84, 101, 111, 0.2);
  transition: all 0.15s ease;
}

.anc-btn-register:hover {
  background: #405059;
  transform: translateY(-1px);
}

/* DROPDOWN PANEL */
.dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 230px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
  z-index: 1000;
  padding: 6px 0;
  display: flex;
  flex-direction: column;
}

.dropdown-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: #f8fafc;
  border-bottom: 1px solid #f1f5f9;
}

.dd-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #54656f;
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}

.dd-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  overflow: hidden;
}

.dd-name {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dd-role {
  font-size: 11.5px;
  color: #64748b;
}

.dd-item {
  display: flex;
  align-items: center;
  padding: 9px 14px;
  font-size: 13px;
  color: #1e293b;
  text-decoration: none;
  background: transparent;
  border: none;
  cursor: pointer;
  width: 100%;
  text-align: left;
  font-family: inherit;
  transition: background 0.15s ease, color 0.15s ease;
}

.dd-item:hover {
  background: #f8fafc;
  color: #5c7e6e;
}

.dd-item.logout {
  color: #dc2626;
}

.dd-item.logout:hover {
  background: #fef2f2;
  color: #dc2626;
}

/* NOTIFICATION POPPER & PANEL */
.sg3-notifications {
  position: relative;
}

.sg3-icon-button {
  background: transparent;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 6px;
  position: relative;
  transition: background 0.15s ease;
}

.sg3-icon-button:hover {
  background: #f1f5f9;
}

.sg3-notification-badge {
  background: #ef4444;
  color: #ffffff;
  font-size: 11px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 9999px;
  line-height: 1.2;
}

.sg3-popover-panel {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 340px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
  z-index: 1000;
  overflow: hidden;
}

.sg3-popover-panel header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  background: #ffffff;
  border-bottom: 1px solid #f1f5f9;
}

.sg3-popover-panel header div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sg3-popover-panel header strong {
  font-size: 15px;
  font-weight: 600;
  color: #0f172a;
}

.sg3-popover-panel header span {
  font-size: 12px;
  color: #64748b;
}

.sg3-popover-panel header button {
  background: transparent;
  border: none;
  color: #5c7e6e;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}

.sg3-popover-panel header button:hover {
  text-decoration: underline;
}

.sg3-notification-list {
  max-height: 320px;
  overflow-y: auto;
  padding: 6px;
}

.sg3-notification-empty {
  padding: 24px 16px;
  text-align: center;
}

.sg3-notification-empty p {
  font-size: 13.5px;
  color: #64748b;
  margin: 0;
}

.sg3-notification-item {
  display: flex;
  width: 100%;
  padding: 10px 12px;
  text-align: left;
  background: #ffffff;
  border: 1px solid #f1f5f9;
  border-radius: 8px;
  cursor: pointer;
  margin-bottom: 6px;
  transition: all 0.15s ease;
  box-sizing: border-box;
}

.sg3-notification-item:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.sg3-notification-item.is-unread {
  background: #ffffff;
  border-color: #cbd5e1;
  border-left: 3px solid #5c7e6e;
}

.sg3-notification-item__content {
  display: flex;
  flex-direction: column;
  gap: 3px;
  width: 100%;
}

.sg3-notification-item__content strong {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
}

.sg3-notification-item__content span {
  font-size: 12.5px;
  color: #475569;
  line-height: 1.4;
}

.sg3-notification-item__content time {
  font-size: 11px;
  color: #94a3b8;
  margin-top: 2px;
}

/* DESKTOP & MOBILE RESPONSIVE UTILITIES */
.alb-nav-actions {
  display: flex;
  align-items: center;
  gap: 16px;
}

.alb-mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 6px;
  border-radius: 6px;
  transition: background 0.15s ease;
}

.alb-mobile-toggle:hover {
  background: #f1f5f9;
}

@media (max-width: 992px) {
  .alb-desktop-only {
    display: none !important;
  }

  .alb-mobile-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
}

.alb-mobile-menu {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #ffffff;
  border-bottom: 1.5px solid #cbd5e1;
  box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
  padding: 18px 20px 24px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.alb-mobile-nav-links {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.alb-mobile-nav-link {
  font-size: 15px;
  font-weight: 500;
  color: #334155;
  text-decoration: none;
  padding: 9px 14px;
  border-radius: 8px;
  transition: background 0.15s ease, color 0.15s ease;
}

.alb-mobile-nav-link:hover,
.alb-mobile-nav-link.active-link {
  background: #f1f5f9;
  color: #5c7e6e;
  font-weight: 600;
}

.alb-mobile-actions {
  border-top: 1px solid #f1f5f9;
  padding-top: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.sg-w-full {
  width: 100%;
  justify-content: center;
}

.alb-mobile-auth-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.alb-mobile-auth-row .anc-btn-login,
.alb-mobile-auth-row .anc-btn-register {
  flex: 1;
  text-align: center;
  justify-content: center;
}

.alb-mobile-user-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-bottom: 10px;
  border-bottom: 1px solid #f1f5f9;
}

.alb-mobile-user-links {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sg-mobile-nav-enter-active,
.sg-mobile-nav-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.sg-mobile-nav-enter-from,
.sg-mobile-nav-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
