<template>
  <header class="topbar">
    <div class="topbar-left">
      <button class="hamburger" type="button" title="Mở menu" @click="$emit('toggle-sidebar')">
        <AppIcon name="menu" size="21" />
      </button>

      <div class="admin-crumbs" aria-label="Breadcrumb">
        <span>Owner</span>
        <AppIcon name="chevronRight" size="13" />
        <span>{{ sectionLabel || 'Tổng quan' }}</span>
        <AppIcon name="chevronRight" size="13" />
        <strong>{{ title }}</strong>
      </div>
    </div>

    <div class="topbar-actions">
      <div class="topbar-command">
        <AppIcon name="search" size="15" />
        <span>Tìm chức năng</span>
        <kbd>/</kbd>
      </div>

      <RouterLink class="topbar-icon" to="/" title="Xem website">
        <AppIcon name="eye" size="18" />
      </RouterLink>

      <ThemeToggle />

      <RouterLink class="topbar-icon" to="/owner/profile" title="Hồ sơ chủ sân">
        <AppIcon name="users" size="18" />
      </RouterLink>

      <div class="topbar-profile" @mouseenter="showMenu = true" @mouseleave="scheduleHide">
        <button class="profile-trigger" type="button" @click="toggleMenu">
          <span class="profile-avatar">{{ userInitial }}</span>
          <span class="profile-copy">
            <strong>{{ userName }}</strong>
            <small>{{ roleLabel }}</small>
          </span>
          <AppIcon name="chevronDown" size="14" />
        </button>

        <transition name="admin-menu">
          <div v-if="showMenu" class="profile-menu" @mouseenter="cancelHide" @mouseleave="scheduleHide">
            <RouterLink class="profile-menu-item" to="/owner/profile" @click="showMenu = false">
              Thông tin cá nhân
            </RouterLink>
            <button class="profile-menu-item danger" type="button" @click="handleLogout">
              Đăng xuất
            </button>
          </div>
        </transition>
      </div>
    </div>
  </header>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import ThemeToggle from '../ui/ThemeToggle.vue';
import { getAuth, logout } from '../../stores/auth.js';

export default {
  name: 'OwnerTopbar',
  components: { AppIcon, ThemeToggle },
  props: {
    title: { type: String, required: true },
    sectionLabel: { type: String, default: '' },
  },
  emits: ['toggle-sidebar'],
  data() {
    return {
      showMenu: false,
      hideTimer: null,
    };
  },
  computed: {
    user() {
      return getAuth() || {};
    },
    userName() {
      return this.user.fullName || this.user.full_name || this.user.username || 'Chủ sân';
    },
    userInitial() {
      return this.userName.charAt(0).toUpperCase();
    },
    roleLabel() {
      return 'Chủ sân';
    },
  },
  methods: {
    toggleMenu() {
      this.showMenu = !this.showMenu;
    },
    scheduleHide() {
      this.hideTimer = setTimeout(() => {
        this.showMenu = false;
      }, 160);
    },
    cancelHide() {
      if (this.hideTimer) clearTimeout(this.hideTimer);
    },
    async handleLogout() {
      await logout();
      this.showMenu = false;
      this.$router.push('/login');
    },

  },
  beforeUnmount() {
    this.cancelHide();
  },
};
</script>
