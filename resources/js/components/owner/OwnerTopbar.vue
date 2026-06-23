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

      <UserProfileDropdown
        :user="user"
        profile-url="/owner/profile"
        billing-url="/owner/billing"
        settings-url="/owner/settings"
        @logout="handleLogout"
      />
    </div>
  </header>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import ThemeToggle from '../ui/ThemeToggle.vue';
import UserProfileDropdown from '../ui/UserProfileDropdown.vue';
import { logout, getAuth } from '../../stores/auth.js';

export default {
  name: 'OwnerTopbar',
  components: { AppIcon, ThemeToggle, UserProfileDropdown },
  props: {
    title: { type: String, required: true },
    sectionLabel: { type: String, default: '' },
  },
  emits: ['toggle-sidebar'],
  computed: {
    user() {
      return getAuth() || {};
    },
  },
  methods: {
    async handleLogout() {
      await logout();
      this.$router.push('/login');
    },
  },
};
</script>
