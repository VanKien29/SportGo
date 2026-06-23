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
      <ThemeToggle />

      <UserProfileDropdown
        :user="user"
        profile-url="/admin/profile"
        billing-url="/admin/billing"
        settings-url="/admin/settings"
        @logout="handleLogout"
      />
    </div>
  </header>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import ThemeToggle from '../ui/ThemeToggle.vue';
import UserProfileDropdown from '../ui/UserProfileDropdown.vue';
import { adminLogout, getAuth } from '../../stores/auth.js';

export default {
  name: 'AdminTopbar',
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
      await adminLogout();
      this.$router.push('/admin/login');
    },
  },
};
</script>
