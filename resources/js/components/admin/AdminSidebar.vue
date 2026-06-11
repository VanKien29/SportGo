<template>
  <aside class="sidebar" aria-label="Admin navigation">
    <RouterLink class="admin-brand" to="/admin/dashboard" @click="$emit('navigate')">
      <span class="admin-brand-mark">SG</span>
      <span class="admin-brand-copy">
        <strong>SportGo</strong>
        <small>Admin Console</small>
      </span>
    </RouterLink>

    <nav class="sidebar-nav">
      <section v-for="section in sections" :key="section.label" class="admin-nav-section">
        <p class="nav-group">{{ section.label }}</p>
        <RouterLink
          v-for="item in section.items"
          :key="item.to"
          class="nav-item"
          :class="{ 'nav-active': isActive(item) }"
          :to="item.to"
          @click="$emit('navigate')"
        >
          <AppIcon :name="item.icon" size="17" />
          <span>{{ item.label }}</span>
        </RouterLink>
      </section>
    </nav>

    <div class="sidebar-view-user">
      <RouterLink class="view-user-btn" to="/" @click="$emit('navigate')">
        <AppIcon name="eye" size="16" />
        <span>Xem trang người dùng</span>
      </RouterLink>
    </div>

    <div class="sidebar-user">
      <div class="user-avatar">{{ userInitial }}</div>
      <div class="user-info">
        <div class="user-name">{{ userName }}</div>
        <div class="user-role">{{ roleLabel }}</div>
      </div>
    </div>
  </aside>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import { getAuth } from '../../stores/auth.js';

export default {
  name: 'AdminSidebar',
  components: { AppIcon },
  props: {
    sections: { type: Array, required: true },
    activeRouteName: { type: String, default: '' },
  },
  emits: ['navigate'],
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
    isActive(item) {
      return item.activeNames?.includes(this.activeRouteName);
    },
  },
};
</script>
