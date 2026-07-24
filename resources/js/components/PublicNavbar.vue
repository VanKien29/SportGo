<template>
  <header class="spg-public-header">
    <div class="spg-public-header__bar">
      <router-link to="/" class="spg-public-brand" aria-label="SportGo - Trang chủ">
        <span class="spg-public-brand__mark">
          <img v-if="brandLogo" :src="brandLogo" :alt="brandName" />
          <Trophy v-else :size="23" aria-hidden="true" />
        </span>
        <span class="spg-public-brand__name">
          {{ brandMain }}<strong v-if="brandAccent">{{ brandAccent }}</strong>
        </span>
      </router-link>

      <nav class="spg-public-nav" aria-label="Điều hướng chính">
        <router-link to="/" exact-active-class="is-active">Trang chủ</router-link>
        <router-link to="/venues" active-class="is-active">Tìm sân</router-link>
        <router-link to="/community" active-class="is-active">Cộng đồng</router-link>
        <router-link to="/news" active-class="is-active">Tin tức</router-link>
      </nav>

      <div class="spg-public-actions">
        <a class="spg-public-support" href="tel:19006789">
          <PhoneCall :size="18" aria-hidden="true" />
          <span>
            <small>Hỗ trợ đặt sân</small>
            <strong>1900 6789</strong>
          </span>
        </a>

        <router-link to="/become-partner" class="spg-public-partner">
          <Building2 :size="18" aria-hidden="true" />
          <span>Đăng sân</span>
        </router-link>

        <template v-if="!user">
          <router-link to="/login" class="spg-public-login">
            <LogIn :size="18" aria-hidden="true" />
            Đăng nhập
          </router-link>
          <router-link to="/register" class="spg-public-register">Đăng ký</router-link>
        </template>

        <div v-if="user" class="spg-popover spg-notifications">
          <button
            type="button"
            class="spg-icon-button"
            aria-label="Mở thông báo"
            :aria-expanded="showNotifDropdown"
            @click.stop="toggleNotifDropdown"
          >
            <Bell :size="21" aria-hidden="true" />
            <span v-if="unreadCount > 0" class="spg-notification-badge">
              {{ unreadCount > 99 ? "99+" : unreadCount }}
            </span>
          </button>

          <transition name="spg-pop">
            <section v-if="showNotifDropdown" class="spg-popover-panel spg-notification-panel">
              <header>
                <div>
                  <strong>Thông báo</strong>
                  <span>{{ unreadCount ? `${unreadCount} chưa đọc` : "Bạn đã xem hết" }}</span>
                </div>
                <button v-if="unreadCount > 0" type="button" @click="markAllAsRead">
                  Đọc tất cả
                </button>
              </header>

              <div class="spg-notification-list">
                <div v-if="notifications.length === 0" class="spg-notification-empty">
                  <BellOff :size="25" aria-hidden="true" />
                  <p>Chưa có thông báo mới.</p>
                </div>
                <button
                  v-for="notif in notifications"
                  :key="notif.id"
                  type="button"
                  class="spg-notification-item"
                  :class="{ 'is-unread': !notif.is_read }"
                  @click="markAsRead(notif)"
                >
                  <span class="spg-notification-item__icon">
                    <CalendarCheck2 :size="18" aria-hidden="true" />
                  </span>
                  <span class="spg-notification-item__content">
                    <strong>{{ notif.title }}</strong>
                    <span>{{ notif.body }}</span>
                    <time>{{ formatTime(notif.created_at) }}</time>
                  </span>
                  <i v-if="!notif.is_read" aria-label="Chưa đọc"></i>
                </button>
              </div>
            </section>
          </transition>
        </div>

        <div v-if="user" class="spg-popover spg-account-menu">
          <button
            type="button"
            class="spg-account-trigger"
            aria-label="Mở menu tài khoản"
            :aria-expanded="showDropdown"
            @click.stop="toggleDropdown"
          >
            <span>{{ userInitial }}</span>
            <ChevronDown :size="15" aria-hidden="true" />
          </button>

          <transition name="spg-pop">
            <section v-if="showDropdown" class="spg-popover-panel spg-account-panel">
              <header class="spg-account-summary">
                <span>{{ userInitial }}</span>
                <div>
                  <strong>{{ user.fullName }}</strong>
                  <small>{{ roleLabel }}</small>
                </div>
              </header>

              <div class="spg-account-links">
                <router-link :to="profileRoute" @click="showDropdown = false">
                  <UserRound :size="18" aria-hidden="true" />
                  Thông tin cá nhân
                </router-link>
                <router-link
                  v-if="user.role === 'user'"
                  to="/bookings"
                  @click="showDropdown = false"
                >
                  <CalendarDays :size="18" aria-hidden="true" />
                  Lịch đặt sân
                </router-link>
                <router-link
                  v-if="user.role === 'user'"
                  :to="{ name: 'profile', query: { tab: 'refunds' } }"
                  @click="showDropdown = false"
                >
                  <WalletCards :size="18" aria-hidden="true" />
                  Số dư hoàn tiền
                </router-link>
                <router-link to="/chat" @click="showDropdown = false">
                  <MessagesSquare :size="18" aria-hidden="true" />
                  Trò chuyện
                </router-link>
                <button v-if="user.role === 'user'" type="button" @click="openComplaintModal">
                  <CircleAlert :size="18" aria-hidden="true" />
                  Gửi khiếu nại
                </button>
                <router-link
                  v-if="user.role === 'user'"
                  to="/partner-application"
                  @click="showDropdown = false"
                >
                  <Building2 :size="18" aria-hidden="true" />
                  Đăng ký đối tác
                </router-link>
                <router-link
                  v-if="user.role === 'owner'"
                  to="/owner/partner-profile"
                  @click="showDropdown = false"
                >
                  <FileUser :size="18" aria-hidden="true" />
                  Hồ sơ đối tác
                </router-link>
                <button v-if="user.role !== 'user'" type="button" @click="goToManage">
                  <LayoutDashboard :size="18" aria-hidden="true" />
                  {{ user.role === "admin" ? "Trang quản trị" : "Quản lý sân" }}
                </button>
              </div>

              <button type="button" class="spg-account-logout" @click="handleLogout">
                <LogOut :size="18" aria-hidden="true" />
                Đăng xuất
              </button>
            </section>
          </transition>
        </div>

        <button
          ref="mobileNavToggle"
          type="button"
          class="spg-mobile-toggle"
          :aria-expanded="showMobileNav"
          :aria-label="showMobileNav ? 'Đóng menu' : 'Mở menu'"
          aria-controls="spg-mobile-navigation"
          @click.stop="toggleMobileNav"
        >
          <X v-if="showMobileNav" :size="23" aria-hidden="true" />
          <Menu v-else :size="23" aria-hidden="true" />
        </button>
      </div>
    </div>

    <transition name="spg-mobile">
      <nav
        v-if="showMobileNav"
        id="spg-mobile-navigation"
        class="spg-mobile-navigation"
        aria-label="Điều hướng di động"
      >
        <router-link to="/" exact-active-class="is-active" @click="closeMobileNav">
          <Home :size="19" aria-hidden="true" />
          Trang chủ
        </router-link>
        <router-link to="/venues" active-class="is-active" @click="closeMobileNav">
          <MapPinned :size="19" aria-hidden="true" />
          Tìm sân
        </router-link>
        <router-link to="/community" active-class="is-active" @click="closeMobileNav">
          <UsersRound :size="19" aria-hidden="true" />
          Cộng đồng
        </router-link>
        <router-link to="/news" active-class="is-active" @click="closeMobileNav">
          <Newspaper :size="19" aria-hidden="true" />
          Tin tức
        </router-link>
        <router-link to="/become-partner" @click="closeMobileNav">
          <Building2 :size="19" aria-hidden="true" />
          Dành cho chủ sân
        </router-link>
        <router-link
          v-if="user?.role === 'user'"
          to="/bookings"
          active-class="is-active"
          @click="closeMobileNav"
        >
          <CalendarDays :size="19" aria-hidden="true" />
          Lịch đã đặt
        </router-link>
        <router-link v-if="!user" to="/login" @click="closeMobileNav">
          <LogIn :size="19" aria-hidden="true" />
          Đăng nhập
        </router-link>
      </nav>
    </transition>
  </header>

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
    profileRoute() {
      if (!this.user) return "/login";
      return this.user.role === "owner" ? "/owner/profile" : "/profile";
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
    closeAllMenus() {
      this.showMobileNav = false;
      this.showDropdown = false;
      this.showNotifDropdown = false;
    },
    handleEscape(event) {
      if (event.key === "Escape") this.closeAllMenus();
    },
    handleViewportResize() {
      if (window.innerWidth >= 1080) this.closeMobileNav();
    },
    toggleDropdown() {
      this.showDropdown = !this.showDropdown;
      this.showNotifDropdown = false;
      this.closeMobileNav();
    },
    toggleNotifDropdown() {
      this.showNotifDropdown = !this.showNotifDropdown;
      this.showDropdown = false;
      this.closeMobileNav();
    },
    handleOutsidePointer(event) {
      if (!event.target.closest?.(".spg-notifications")) {
        this.showNotifDropdown = false;
      }
      if (!event.target.closest?.(".spg-account-menu")) {
        this.showDropdown = false;
      }
      if (!event.target.closest?.(".spg-mobile-toggle, .spg-mobile-navigation")) {
        this.closeMobileNav();
      }
    },
    goToManage() {
      this.showDropdown = false;
      this.$router.push(
        this.user?.role === "admin" ? "/admin/dashboard" : "/owner/dashboard",
      );
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

      const actionUrl = notification.data?.action_url;
      if (typeof actionUrl === "string" && actionUrl.startsWith("/")) {
        this.$router.push(actionUrl);
      } else if (
        notification.reference_type === "partner_application" &&
        notification.reference_id
      ) {
        this.$router.push(`/partner-application/${notification.reference_id}`);
      } else if (notification.type === "matchmaking_join_request") {
        this.$router.push(
          `/matchmaking-posts/${notification.reference_id}/manage`,
        );
      } else if (
        ["matchmaking_join_approved", "matchmaking_join_rejected"].includes(
          notification.type,
        )
      ) {
        this.$router.push("/community");
      } else if (
        ["post_like", "post_comment", "comment_reply"].includes(
          notification.type,
        )
      ) {
        this.$router.push(
          notification.data?.slug
            ? `/community/${notification.data.slug}`
            : "/community",
        );
      } else if (notification.type === "post_approved") {
        if (notification.reference_type === "system_posts") {
          this.$router.push(
            notification.data?.slug
              ? `/news/${notification.data.slug}`
              : "/news",
          );
        } else {
          this.$router.push(
            notification.data?.slug
              ? `/community/${notification.data.slug}`
              : "/community",
          );
        }
      }
      this.showNotifDropdown = false;
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
