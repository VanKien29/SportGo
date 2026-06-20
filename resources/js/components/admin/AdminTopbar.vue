<template>
  <header class="topbar">
    <div class="topbar-left">
      <button class="hamburger" type="button" title="Mở menu" @click="$emit('toggle-sidebar')">
        <AppIcon name="menu" size="21" />
      </button>

      <div class="admin-crumbs" aria-label="Breadcrumb">
        <span>Admin</span>
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

      <button class="topbar-icon" type="button" :title="theme === 'dark' ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối'" @click="toggleTheme">
        <AppIcon :name="theme === 'dark' ? 'sun' : 'moon'" size="18" />
      </button>

      <RouterLink class="topbar-icon" to="/admin/profile" title="Hồ sơ admin">
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
            <RouterLink class="profile-menu-item" to="/admin/profile" @click="showMenu = false">
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
import { adminLogout, getAuth } from '../../stores/auth.js';

export default {
  name: 'AdminTopbar',
  components: { AppIcon },
  props: {
    title: { type: String, required: true },
    sectionLabel: { type: String, default: '' },
  },
  emits: ['toggle-sidebar'],
  data() {
    return {
      showMenu: false,
      hideTimer: null,
      theme: 'light',
    };
  },
  created() {
    this.theme = localStorage.getItem('admin-theme') || 'light';
    document.documentElement.setAttribute('data-theme', this.theme);
  },
  computed: {
    user() {
      return getAuth() || {};
    },
    userName() {
      return this.user.fullName || this.user.full_name || this.user.username || 'Admin';
    },
    userInitial() {
      return this.userName.charAt(0).toUpperCase();
    },
    roleLabel() {
      const role = this.user.role || this.user.role_group;
      const labels = {
        admin: 'Quản trị viên',
        super_admin: 'Super admin',
        system_staff: 'Nhân viên hệ thống',
      };
      return labels[role] || 'Admin';
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
      await adminLogout();
      this.showMenu = false;
      this.$router.push('/admin/login');
    },
    toggleTheme() {
      this.theme = this.theme === 'dark' ? 'light' : 'dark';
      localStorage.setItem('admin-theme', this.theme);
      document.documentElement.setAttribute('data-theme', this.theme);
    },
  },
  beforeUnmount() {
    this.cancelHide();
  },
};
</script>
