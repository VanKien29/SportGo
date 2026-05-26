import { getSelectedCluster } from '../../stores/auth.js';
import { api } from '../../services/api.js';

export default {
  name: 'OwnerDashboard',
  data() {
    return {
      cluster: getSelectedCluster(),
      stats: {
        bookings: 0,
        revenue: 0,
        rating: 0,
      },
      isLoading: true,
      error: null,
    };
  },
  async mounted() {
    try {
      this.isLoading = true;
      const data = await api('/api/owner/dashboard');
      this.stats = data;
    } catch (err) {
      this.error = 'Không thể tải dữ liệu thống kê';
      console.error(err);
    } finally {
      this.isLoading = false;
    }
  },
  methods: {
    formatCurrency(amount) {
      if (amount >= 1000000000) {
        return `₫ ${(amount / 1000000000).toFixed(1)}B`;
      }
      if (amount >= 1000000) {
        return `₫ ${(amount / 1000000).toFixed(1)}M`;
      }
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
    }
  }
};
