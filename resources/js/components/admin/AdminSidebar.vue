<template>
  <DoubleSidebar
    :sections="sections"
    :active-route-name="activeRouteName"
    :user="user"
    :role-label="roleLabel"
    show-view-user-btn
    view-user-route="/"
    view-user-label="Xem trang người dùng"
    @navigate="$emit('navigate')"
  />
</template>

<script>
import DoubleSidebar from '../ui/DoubleSidebar.vue';
import { getAuth } from '../../stores/auth.js';

export default {
  name: 'AdminSidebar',
  components: { DoubleSidebar },
  props: {
    sections: { type: Array, required: true },
    activeRouteName: { type: String, default: '' },
  },
  emits: ['navigate'],
  computed: {
    user() {
      return getAuth() || {};
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
};
</script>

