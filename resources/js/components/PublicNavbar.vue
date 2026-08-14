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

        <!-- Main Navigation Links -->
        <div class="alb-nav-links">
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

        <!-- User Actions -->
        <div style="display: flex; align-items: center; gap: 16px;">
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
              <span class="avatar-circle">{{ userInitial }}</span>
              <span class="user-name-text" style="color: #111827;">{{ user.fullName }}</span>
            </button>

            <transition name="dd">
              <div v-if="showDropdown" class="dropdown" @click.stop>
                <div class="dropdown-header">
                  <div class="dd-avatar">{{ userInitial }}</div>
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
                  Vào trang chủ sân
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
      return this.user?.fullName?.trim()?.charAt(0)?.toUpperCase() || "?";
    },
    roleLabel() {
      const labels = { admin: "Quản trị viên", owner: "Chủ sân", user: "Người chơi", customer: "Người chơi" };
      return labels[this.user?.role] || "Tài khoản";
    },
    isClientUser() {
      return Boolean(this.user);
    },
    isOwner() {
      return this.user?.role === "owner";
    },
    isAdmin() {
      return this.user?.role === "admin";
    },
    profileRoute() {
      return "/profile";
    },
  },
  watch: {
    "$route.fullPath"() {
      this.showDropdown = false;
      this.showNotifDropdown = false;
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
    handleOutside(event) {
      if (!event.target.closest(".sg3-account-menu")) this.showDropdown = false;
      if (!event.target.closest(".sg3-notifications")) this.showNotifDropdown = false;
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
  background: #15803d;
  color: #ffffff;
  font-size: 13px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.user-name-text {
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
}

/* DROPDOWN PANEL */
.dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 230px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
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
  background: #15803d;
  color: #ffffff;
  font-size: 14px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.dd-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  overflow: hidden;
}

.dd-name {
  font-size: 13px;
  font-weight: 500;
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
  color: #15803d;
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
  color: #16a34a;
  font-size: 12.5px;
  font-weight: 500;
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
  border-left: 3px solid #16a34a;
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
</style>
