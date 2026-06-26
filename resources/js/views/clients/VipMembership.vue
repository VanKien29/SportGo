<template>
  <section class="vip-page">
    <header class="page-head">
      <div>
        <p>SportGo VIP</p>
        <h1>Gói thành viên hệ thống</h1>
      </div>
      <router-link class="link-btn" to="/profile">Hồ sơ</router-link>
    </header>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <section v-if="subscription" class="current-plan">
      <div>
        <span>Đang dùng</span>
        <strong>{{ subscription.package?.label || subscription.package?.name }}</strong>
      </div>
      <div>
        <span>Hiệu lực đến</span>
        <strong>{{ date(subscription.expires_at) }}</strong>
      </div>
      <div>
        <span>Đã thanh toán</span>
        <strong>{{ money(subscription.paid_amount) }}</strong>
      </div>
    </section>

    <div v-if="loading" class="state">Đang tải gói VIP...</div>
    <section v-else class="plan-grid">
      <article v-for="pkg in paidPackages" :key="pkg.id" class="plan-card" :class="`plan-${pkg.type}`">
        <header>
          <span>{{ pkg.badge_name || pkg.label }}</span>
          <h2>{{ pkg.label || pkg.name }}</h2>
        </header>

        <ul>
          <li>{{ pkg.voucher_count_per_month }} voucher/tháng, giảm {{ pkg.voucher_discount_percent }}%</li>
          <li>Đơn tối thiểu {{ money(pkg.voucher_min_order_amount) }}</li>
          <li>Cashback {{ pkg.cashback_percent }}% sau booking hoàn tất</li>
          <li>{{ postLimitText(pkg.match_post_limit_per_month) }}</li>
          <li>{{ pkg.priority_complaint ? 'Ưu tiên xử lý khiếu nại' : 'Xử lý khiếu nại tiêu chuẩn' }}</li>
        </ul>

        <div class="cycle-list">
          <button
            v-for="cycle in pkg.available_cycles"
            :key="cycle.key"
            type="button"
            :disabled="subscribing === `${pkg.id}-${cycle.key}`"
            @click="subscribe(pkg, cycle)"
          >
            <span>{{ cycle.label }}</span>
            <strong>{{ money(cycle.price) }}</strong>
          </button>
        </div>
      </article>
    </section>
  </section>
</template>

<script>
import { vipMembershipService } from '../../services/vipMembershipService.js';

export default {
  name: 'VipMembership',
  data() {
    return {
      packages: [],
      subscription: null,
      loading: false,
      subscribing: '',
      error: '',
      success: '',
    };
  },
  computed: {
    paidPackages() {
      return this.packages.filter((pkg) => pkg.type !== 'free' && pkg.is_active);
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const response = await vipMembershipService.playerIndex();
        this.packages = response.packages || [];
        this.subscription = response.subscription || null;
      } catch (error) {
        this.error = error.message || 'Không thể tải gói VIP.';
      } finally {
        this.loading = false;
      }
    },
    async subscribe(pkg, cycle) {
      this.subscribing = `${pkg.id}-${cycle.key}`;
      this.error = '';
      try {
        const response = await vipMembershipService.subscribe({
          package_id: pkg.id,
          billing_cycle: cycle.key,
        });
        this.success = response.message || 'Đã kích hoạt gói VIP.';
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể kích hoạt gói VIP.';
      } finally {
        this.subscribing = '';
      }
    },
    postLimitText(limit) {
      return Number(limit) < 0 ? 'Bài giao lưu không giới hạn' : `${Number(limit || 0)} bài giao lưu/tháng`;
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
    },
    date(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
  },
};
</script>

<style scoped>
.vip-page{max-width:1120px;margin:0 auto;padding:24px;display:grid;gap:16px}.page-head,.current-plan{display:flex;justify-content:space-between;align-items:center;gap:14px}.page-head p{margin:0 0 4px;color:#059669;font-size:12px;font-weight:900;text-transform:uppercase}.page-head h1{margin:0;color:#0f172a}.link-btn{padding:9px 12px;border-radius:8px;background:#f1f5f9;color:#0f172a;text-decoration:none;font-weight:850}.alert,.state,.current-plan{padding:14px;border-radius:10px}.alert.error{background:#fee2e2;color:#b91c1c}.alert.success{background:#dcfce7;color:#166534}.state{border:1px solid #e2e8f0;background:#fff;color:#64748b}.current-plan{border:1px solid #bbf7d0;background:#ecfdf5}.current-plan span{display:block;color:#047857;font-size:11px;font-weight:900;text-transform:uppercase}.current-plan strong{color:#0f172a}.plan-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.plan-card{display:grid;gap:16px;padding:18px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.plan-card header span{color:#0ea5e9;font-size:11px;font-weight:900;text-transform:uppercase}.plan-card h2{margin:4px 0 0;color:#0f172a}.plan-card ul{display:grid;gap:8px;margin:0;padding-left:18px;color:#334155;font-weight:650}.plan-pro{border-color:#fbbf24}.plan-pro header span{color:#b45309}.cycle-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.cycle-list button{display:grid;gap:3px;min-height:58px;border:1px solid #d1fae5;border-radius:9px;background:#ecfdf5;color:#065f46;font:inherit;font-weight:850;cursor:pointer}.cycle-list button:disabled{opacity:.55;cursor:not-allowed}.cycle-list span{font-size:12px}.cycle-list strong{font-size:14px}@media(max-width:820px){.plan-grid,.cycle-list{grid-template-columns:1fr}.page-head,.current-plan{display:grid}}
</style>
