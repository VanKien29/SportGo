<template>
  <header class="sg3-header">
    <div class="sg3-header__inner">
      <router-link to="/" class="sg3-brand" aria-label="Về trang chủ">
        <span class="sg3-brand__mark">
          <img v-if="brandLogo" :src="brandLogo" :alt="brandName" />
          <Trophy v-else :size="22" aria-hidden="true" />
        </span>
        <span class="sg3-brand__name">{{ brandMain }}<strong v-if="brandAccent">{{ brandAccent }}</strong></span>
      </router-link>

      <nav class="sg3-primary-nav" aria-label="Điều hướng chính">
        <router-link to="/" exact-active-class="is-active">Trang chủ</router-link>
        <router-link to="/venues" active-class="is-active">Tìm sân</router-link>
        <router-link to="/offers" active-class="is-active">Ưu đãi</router-link>
        <router-link to="/community" active-class="is-active">Cộng đồng</router-link>
        <router-link to="/news" active-class="is-active">Tin tức</router-link>
      </nav>

      <div class="sg3-header__actions">
        <a class="sg3-support" :href="`tel:${supportPhoneRaw}`">
          <small>Hỗ trợ đặt sân</small>
          <strong>{{ supportPhone }}</strong>
        </a>
        <router-link v-if="user && isClientAccount" to="/bookings" class="sg3-header__button">
          <CalendarDays :size="17" aria-hidden="true" />
          Lịch đặt
        </router-link>
        <router-link to="/become-partner" class="sg3-header__button">
          <Building2 :size="17" aria-hidden="true" />
          Đăng sân
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
          <transition name="spg-pop">
            <section v-if="showDropdown" class="sg3-popover-panel">
              <header class="sg3-account-summary"><span>{{ userInitial }}</span><div><strong>{{ user.fullName }}</strong><small>{{ roleLabel }}</small></div></header>
              <div class="sg3-account-links">
                <router-link :to="profileRoute" @click="showDropdown = false"><UserRound :size="17" aria-hidden="true" />Thông tin cá nhân</router-link>
                <router-link v-if="isClientAccount" to="/bookings" @click="showDropdown = false"><CalendarDays :size="17" aria-hidden="true" />Lịch đặt sân</router-link>
                <router-link v-if="isClientAccount" to="/wallet" @click="showDropdown = false"><WalletCards :size="17" aria-hidden="true" />Ví SportGo</router-link>
                <router-link v-if="isClientAccount" to="/refunds" @click="showDropdown = false"><WalletCards :size="17" aria-hidden="true" />Hoàn tiền</router-link>
                <router-link v-if="isClientAccount" to="/notifications" @click="showDropdown = false"><Bell :size="17" aria-hidden="true" />Thông báo</router-link>
                <router-link to="/chat" @click="showDropdown = false"><MessagesSquare :size="17" aria-hidden="true" />Trò chuyện</router-link>
                <button v-if="isClientAccount" type="button" @click="openComplaintModal"><CircleAlert :size="17" aria-hidden="true" />Khiếu nại</button>
                <button v-if="user.role !== 'user'" type="button" @click="goToManage"><LayoutDashboard :size="17" aria-hidden="true" />{{ user.role === "admin" ? "Trang quản trị" : "Quản lý sân" }}</button>
              </div>
              <button type="button" class="sg3-account-logout" @click="handleLogout"><LogOut :size="17" aria-hidden="true" />Đăng xuất</button>
            </section>
          </transition>
        </div>

        <button ref="mobileNavToggle" type="button" class="sg3-mobile-toggle" :aria-expanded="showMobileNav" :aria-label="showMobileNav ? 'Đóng menu' : 'Mở menu'" aria-controls="sg3-mobile-navigation" @click.stop="toggleMobileNav">
          <X v-if="showMobileNav" :size="21" aria-hidden="true" /><Menu v-else :size="21" aria-hidden="true" />
        </button>
      </div>
    </div>

    <transition name="spg-mobile">
      <nav v-if="showMobileNav" id="sg3-mobile-navigation" class="sg3-mobile-nav" aria-label="Điều hướng di động">
        <router-link to="/" exact-active-class="is-active" @click="closeMobileNav"><Home :size="17" />Trang chủ</router-link>
        <router-link to="/venues" active-class="is-active" @click="closeMobileNav"><MapPinned :size="17" />Tìm sân</router-link>
        <router-link to="/offers" active-class="is-active" @click="closeMobileNav"><WalletCards :size="17" />Ưu đãi</router-link>
        <router-link to="/community" active-class="is-active" @click="closeMobileNav"><UsersRound :size="17" />Cộng đồng</router-link>
        <router-link to="/news" active-class="is-active" @click="closeMobileNav"><Newspaper :size="17" />Tin tức</router-link>
        <router-link v-if="isClientAccount" to="/bookings" active-class="is-active" @click="closeMobileNav"><CalendarDays :size="17" />Lịch đặt sân</router-link>
        <router-link v-if="!user" to="/login" @click="closeMobileNav"><LogIn :size="17" />Đăng nhập</router-link>
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
      if (!event.target.closest?.(".sg3-mobile-toggle, .sg3-mobile-nav")) {
        this.closeMobileNav();
      }
    },
    goToManage() {
      this.showDropdown = false;
      this.$router.push(
        this.user?.role === "admin"
          ? "/admin/dashboard"
          : this.user?.role === "staff"
            ? "/staff/dashboard"
            : "/owner/dashboard",
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

      const actionUrl = notification.action_url || notification.data?.action_url;
      if (typeof actionUrl === "string" && actionUrl.startsWith("/")) {
        this.$router.push(actionUrl);
      } else if (typeof notification.data?.url === "string" && notification.data.url.startsWith("/")) {
        this.$router.push(notification.data.url);
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
      } else if (
        notification.reference_id &&
        (String(notification.reference_type || "").toLowerCase().includes("booking") ||
          String(notification.type || "").toLowerCase().includes("booking"))
      ) {
        this.$router.push({ name: "booking-detail", params: { id: notification.reference_id } });
      } else if (
        notification.reference_id &&
        (String(notification.reference_type || "").toLowerCase().includes("refund") ||
          String(notification.type || "").toLowerCase().includes("refund"))
      ) {
        this.$router.push({ name: "client-refund-detail", params: { id: notification.reference_id } });
      } else if (
        notification.reference_id &&
        (String(notification.reference_type || "").toLowerCase().includes("complaint") ||
          String(notification.type || "").toLowerCase().includes("complaint"))
      ) {
        this.$router.push({ name: "client-complaint-detail", params: { id: notification.reference_id } });
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
