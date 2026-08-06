<template>
  <header class="alb-header">
    <!-- Top Utility Bar -->
    <div class="alb-topbar">
      <div class="alb-topbar__inner sg-container">
        <div class="alb-topbar__left">
          <a :href="`tel:${supportPhoneRaw}`" class="alb-topbar__link">
            <span>Hotline 24/7: {{ supportPhone }}</span>
          </a>
          <span class="alb-topbar__link" style="cursor: default;">
            <span>Hệ thống 500+ cụm sân thể thao toàn quốc</span>
          </span>
        </div>

        <div class="alb-topbar__right">
          <router-link to="/become-partner" class="alb-topbar__link">
            <span class="alb-topbar__badge">Dành cho Chủ Sân</span>
          </router-link>
          <a href="#support" class="alb-topbar__link" @click.prevent="showComplaintModal = true">
            <span>Trợ giúp & Khiếu nại</span>
          </a>
        </div>
      </div>
    </div>

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
          <router-link to="/become-partner" class="alb-btn-owner">
            <span>Đăng ký Chủ sân</span>
          </router-link>
        </div>

        <!-- User Actions -->
        <div style="display: flex; align-items: center; gap: 16px;">
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
                  <button v-for="notif in notifications" :key="notif.id" type="button" class="sg3-notification-item" :class="{ 'is-unread': !notif.is_read }" @click="markAsRead(notif)">
                    <span class="sg3-notification-item__content"><strong>{{ notif.title }}</strong><span>{{ notif.body }}</span><time>{{ formatTime(notif.created_at) }}</time></span>
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

                <router-link v-if="isClientUser" :to="{ name: 'profile', query: { tab: 'refunds' } }" class="dd-item" @click="showDropdown = false">
                  Số dư hoàn tiền
                </router-link>

                <router-link to="/chat" class="dd-item" @click="showDropdown = false">
                  Trò chuyện
                </router-link>

                <button v-if="!isClientUser" type="button" class="dd-item" @click="goToDashboard">
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
      return !["admin", "owner"].includes(this.user?.role);
    },
    profileRoute() {
      return this.user?.role === "owner" ? "/owner/profile" : "/profile";
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
      this.fetchNotifications();
      this.notifTimer = setInterval(this.fetchNotifications, 30000);
    }
  },
  beforeUnmount() {
    document.removeEventListener("pointerdown", this.handleOutside);
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
    },
    async fetchNotifications() {
      try {
        const data = await notificationService.getNotifications();
        this.notifications = data || [];
        this.unreadCount = this.notifications.filter((n) => !n.is_read).length;
      } catch (error) {
        // silent
      }
    },
    async markAllAsRead() {
      try {
        await notificationService.markAllAsRead();
        this.notifications.forEach((n) => (n.is_read = true));
        this.unreadCount = 0;
      } catch (error) {
        // silent
      }
    },
    async markAsRead(notif) {
      if (!notif.is_read) {
        try {
          await notificationService.markAsRead(notif.id);
          notif.is_read = true;
          this.unreadCount = Math.max(0, this.unreadCount - 1);
        } catch (e) {
          // silent
        }
      }
      this.showNotifDropdown = false;
    },
    goToDashboard() {
      this.showDropdown = false;
      this.$router.push(this.user?.role === "admin" ? "/admin/dashboard" : "/owner/dashboard");
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

/* NOTIFICATION DROPDOWN FIXES */
.sg3-popover-panel {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 320px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  z-index: 1000;
  overflow: hidden;
}

.sg3-icon-button {
  background: transparent;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  position: relative;
}

.sg3-notification-badge {
  background: #dc2626;
  color: #ffffff;
  font-size: 10px;
  font-weight: 500;
  padding: 1px 5px;
  border-radius: 10px;
}
</style>
