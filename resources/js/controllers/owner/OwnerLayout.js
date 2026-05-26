import SidebarLayout from '../../components/SidebarLayout.vue';
import { getSelectedCluster } from '../../stores/auth.js';

export default {
  name: 'OwnerLayout',
  components: { SidebarLayout },
  data() {
    return {
      selectedCluster: getSelectedCluster(),
    };
  },
  computed: {
    clusterName() {
      return this.selectedCluster?.name || '';
    },
    currentTitle() {
      const map = {
        'OwnerSelectCluster': 'Chọn cụm sân',
        'OwnerDashboard': 'Dashboard',
        'Profile': 'Thông tin cá nhân',
      };
      return map[this.$route.name] || 'Chủ sân';
    },
  },
  methods: {
    refreshCluster() {
      this.selectedCluster = getSelectedCluster();
    },
  },
  watch: {
    $route() {
      this.selectedCluster = getSelectedCluster();
    },
  },
};
