<template>
  <section class="vip-admin-page">
    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <div v-if="loading" class="state">Đang tải gói VIP...</div>
    <div v-else class="package-grid">
      <form v-for="pkg in packages" :key="pkg.id" class="package-card" @submit.prevent="save(pkg)">
        <header>
          <div>
            <span>{{ pkg.type === 'free' ? 'Mặc định' : 'Trả phí' }}</span>
            <h3>{{ pkg.label || pkg.name }}</h3>
          </div>
          <label class="toggle">
            <input v-model="pkg.is_active" type="checkbox" :disabled="pkg.type === 'free'" />
            Hoạt động
          </label>
        </header>

        <div class="grid">
          <label>Tên gói<input v-model.trim="pkg.name" required /></label>
          <label>Thứ tự<input v-model.number="pkg.sort_order" type="number" min="0" max="255" required /></label>
          <label>Giá tháng<input v-model.number="pkg.monthly_price" type="number" min="0" step="1000" :disabled="pkg.type === 'free'" /></label>
          <label>Giá quý<input v-model.number="pkg.quarterly_price" type="number" min="0" step="1000" :disabled="pkg.type === 'free'" /></label>
          <label>Giá năm<input v-model.number="pkg.yearly_price" type="number" min="0" step="1000" :disabled="pkg.type === 'free'" /></label>
          <label>Badge<input v-model.trim="pkg.badge_name" :disabled="pkg.type === 'free'" /></label>
          <label>Voucher/tháng<input v-model.number="pkg.voucher_count_per_month" type="number" min="0" max="50" :disabled="pkg.type === 'free'" required /></label>
          <label>% giảm voucher<input v-model.number="pkg.voucher_discount_percent" type="number" min="0" max="100" step="0.01" :disabled="pkg.type === 'free'" required /></label>
          <label>Đơn tối thiểu<input v-model.number="pkg.voucher_min_order_amount" type="number" min="0" step="1000" :disabled="pkg.type === 'free'" required /></label>
          <label>Trần giảm<input v-model.number="pkg.voucher_max_discount_amount" type="number" min="0" step="1000" :disabled="pkg.type === 'free'" /></label>
          <label>% cashback<input v-model.number="pkg.cashback_percent" type="number" min="0" max="100" step="0.01" :disabled="pkg.type === 'free'" required /></label>
          <label>Bài giao lưu/tháng<input v-model.number="pkg.match_post_limit_per_month" type="number" min="-1" required /></label>
        </div>

        <label class="check">
          <input v-model="pkg.priority_complaint" type="checkbox" :disabled="pkg.type === 'free'" />
          Ưu tiên khiếu nại
        </label>

        <button class="btn primary" type="submit" :disabled="savingId === pkg.id">
          {{ savingId === pkg.id ? 'Đang lưu...' : 'Lưu gói' }}
        </button>
      </form>
    </div>
  </section>
</template>

<script>
import { vipMembershipService } from '../../services/vipMembershipService.js';

export default {
  name: 'AdminMembershipPackages',
  data() {
    return {
      packages: [],
      loading: false,
      savingId: '',
      error: '',
      success: '',
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const response = await vipMembershipService.adminPackages();
        this.packages = (response.data || []).map((pkg) => ({ ...pkg }));
      } catch (error) {
        this.error = error.message || 'Không thể tải gói VIP.';
      } finally {
        this.loading = false;
      }
    },
    payload(pkg) {
      return {
        name: pkg.name,
        monthly_price: pkg.monthly_price,
        quarterly_price: pkg.quarterly_price || null,
        yearly_price: pkg.yearly_price || null,
        voucher_count_per_month: Number(pkg.voucher_count_per_month || 0),
        voucher_discount_percent: Number(pkg.voucher_discount_percent || 0),
        voucher_min_order_amount: Number(pkg.voucher_min_order_amount || 0),
        voucher_max_discount_amount: pkg.voucher_max_discount_amount || null,
        cashback_percent: Number(pkg.cashback_percent || 0),
        match_post_limit_per_month: Number(pkg.match_post_limit_per_month || 0),
        priority_complaint: Boolean(pkg.priority_complaint),
        badge_name: pkg.badge_name || null,
        is_active: Boolean(pkg.is_active),
        sort_order: Number(pkg.sort_order || 0),
      };
    },
    async save(pkg) {
      this.savingId = pkg.id;
      this.error = '';
      try {
        const response = await vipMembershipService.updateAdminPackage(pkg.id, this.payload(pkg));
        this.success = response.message || 'Đã lưu gói VIP.';
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể lưu gói VIP.';
      } finally {
        this.savingId = '';
      }
    },
  },
};
</script>

<style scoped>
.vip-admin-page{display:grid;gap:16px}.alert,.state{padding:12px 14px;border-radius:10px;font-weight:750}.alert.error{background:#fee2e2;color:#b91c1c}.alert.success{background:#dcfce7;color:#166534}.state{background:#fff;border:1px solid #e2e8f0;color:#64748b}.package-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.package-card{display:grid;gap:14px;padding:16px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.package-card header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.package-card h3{margin:3px 0 0;color:#0f172a}.package-card header span{color:#059669;font-size:11px;font-weight:900;text-transform:uppercase}.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}label{display:grid;gap:5px;color:#475569;font-size:12px;font-weight:800}input{height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit}.toggle,.check{display:flex;align-items:center;gap:8px}.toggle input,.check input{width:16px;height:16px}.btn{border:0;border-radius:8px;padding:10px 14px;font-weight:850;cursor:pointer}.primary{background:#16a34a;color:#fff}.primary:disabled{opacity:.55;cursor:not-allowed}@media(max-width:1100px){.package-grid{grid-template-columns:1fr}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.grid{grid-template-columns:1fr}}
</style>
