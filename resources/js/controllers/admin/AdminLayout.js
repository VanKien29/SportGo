import SidebarLayout from '../../components/SidebarLayout.vue';

export default {
  name: 'AdminLayout',
  components: { SidebarLayout },
  computed: {
    currentTitle() {
      const map = {
        'AdminDashboard': 'Dashboard',
        'Profile': 'Thông tin cá nhân',
        'AdminUsers': 'Quản lý tài khoản',
      };
      return map[this.$route.name] || 'Admin';
    },
  },
};
