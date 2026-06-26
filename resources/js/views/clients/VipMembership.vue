<template>
  <section class="vip-page">
    <header class="page-head">
      <div>
        <p>SportGo VIP</p>
        <h1>Chọn gói VIP</h1>
      </div>
      <div class="head-actions">
        <button class="back-btn" type="button" @click="goBack">← Quay lại</button>
        <router-link class="link-btn" to="/profile">Hồ sơ</router-link>
      </div>
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
      <article
        v-for="pkg in paidPackages"
        :key="pkg.id"
        class="plan-card"
        :class="[`plan-${pkg.type}`, { current: isCurrentPackage(pkg) }]"
      >
        <header>
          <div>
            <span>{{ packageBadgeText(pkg) }}</span>
            <h2>{{ pkg.label || pkg.name }}</h2>
          </div>
          <em v-if="isCurrentPackage(pkg)">Đang dùng</em>
        </header>

        <ul>
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
            @click="openConfirm(pkg, cycle)"
          >
            <span>Mua {{ cycleLabel(cycle).toLowerCase() }}</span>
            <strong>{{ money(cycle.price) }}</strong>
          </button>
        </div>
      </article>
    </section>

    <div v-if="pendingPurchase" class="confirm-backdrop" @click.self="closeConfirm">
      <section class="confirm-modal" role="dialog" aria-modal="true">
        <header>
          <span>Xác nhận mua gói</span>
          <h2>{{ pendingPurchase.package.label || pendingPurchase.package.name }}</h2>
        </header>
        <div class="confirm-summary">
          <div>
            <small>Chu kỳ</small>
            <strong>{{ cycleLabel(pendingPurchase.cycle) }}</strong>
          </div>
          <div>
            <small>Số tiền</small>
            <strong>{{ money(pendingPurchase.cycle.price) }}</strong>
          </div>
        </div>
        <p>
          Sau khi xác nhận, gói VIP hiện tại nếu có sẽ được thay bằng gói này và thời hạn mới sẽ bắt đầu ngay.
        </p>
        <footer>
          <button class="ghost-btn" type="button" @click="closeConfirm">Hủy</button>
          <button
            class="confirm-btn"
            type="button"
            :disabled="Boolean(subscribing)"
            @click="confirmPurchase"
          >
            {{ subscribing ? 'Đang xử lý...' : 'Xác nhận mua' }}
          </button>
        </footer>
      </section>
    </div>
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
      pendingPurchase: null,
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
    isCurrentPackage(pkg) {
      return this.subscription?.package?.id === pkg.id;
    },
    goBack() {
      if (window.history.length > 1) {
        this.$router.back();
        return;
      }

      this.$router.push('/profile');
    },
    packageBadgeText(pkg) {
      if (pkg.type === 'saving') return 'SportGo Tiết kiệm';
      if (pkg.type === 'pro') return 'SportGo Pro';
      return pkg.badge_name || pkg.label || pkg.name;
    },
    cycleLabel(cycle) {
      return {
        monthly: 'Hằng tháng',
        quarterly: 'Hằng quý',
        yearly: 'Hằng năm',
      }[cycle.key] || cycle.label || 'Chu kỳ';
    },
    openConfirm(pkg, cycle) {
      this.error = '';
      this.success = '';
      this.pendingPurchase = { package: pkg, cycle };
    },
    closeConfirm() {
      if (this.subscribing) return;
      this.pendingPurchase = null;
    },
    async confirmPurchase() {
      if (!this.pendingPurchase) return;

      const { package: pkg, cycle } = this.pendingPurchase;
      this.subscribing = `${pkg.id}-${cycle.key}`;
      this.error = '';
      try {
        const response = await vipMembershipService.subscribe({
          package_id: pkg.id,
          billing_cycle: cycle.key,
        });
        this.success = response.message || 'Đã kích hoạt gói VIP.';
        this.pendingPurchase = null;
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
.vip-page{max-width:1120px;margin:0 auto;padding:24px;display:grid;gap:16px}.page-head,.current-plan{display:flex;justify-content:space-between;align-items:center;gap:14px}.page-head p{margin:0 0 4px;color:#059669;font-size:12px;font-weight:900;text-transform:uppercase}.page-head h1{margin:0;color:#0f172a}.head-actions{display:flex;align-items:center;gap:8px}.link-btn,.back-btn{min-height:42px;padding:10px 14px;border-radius:10px;font:inherit;font-weight:900;text-decoration:none;cursor:pointer}.link-btn{background:#16a34a;color:#fff;box-shadow:0 10px 24px rgba(22,163,74,.2)}.back-btn{border:1px solid #d1fae5;background:#ecfdf5;color:#047857}.alert,.state,.current-plan{padding:14px;border-radius:10px}.alert.error{background:#fee2e2;color:#b91c1c}.alert.success{background:#dcfce7;color:#166534}.state{border:1px solid #e2e8f0;background:#fff;color:#64748b}.current-plan{border:1px solid #bbf7d0;background:#ecfdf5}.current-plan span{display:block;color:#047857;font-size:11px;font-weight:900;text-transform:uppercase}.current-plan strong{color:#0f172a}.plan-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.plan-card{display:grid;gap:16px;padding:20px;border:1px solid #bbf7d0;border-radius:12px;background:linear-gradient(180deg,#fff 0%,#f8fffb 100%);box-shadow:0 12px 34px rgba(15,23,42,.07)}.plan-card.current{border-color:#16a34a;box-shadow:0 16px 38px rgba(22,163,74,.18)}.plan-card header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.plan-card header span{color:#0ea5e9;font-size:11px;font-weight:900;text-transform:uppercase}.plan-card header em{padding:5px 8px;border-radius:999px;background:#dcfce7;color:#166534;font-size:11px;font-style:normal;font-weight:900;white-space:nowrap}.plan-card h2{margin:4px 0 0;color:#0f172a}.plan-card ul{display:grid;gap:8px;margin:0;padding-left:18px;color:#334155;font-weight:750}.plan-pro{border-color:#fbbf24;background:linear-gradient(180deg,#fff 0%,#fffaf0 100%)}.plan-pro header span{color:#b45309}.cycle-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.cycle-list button{display:grid;gap:3px;min-height:62px;border:1px solid #bbf7d0;border-radius:9px;background:#ecfdf5;color:#065f46;font:inherit;font-weight:900;cursor:pointer}.cycle-list button:hover{background:#dcfce7}.cycle-list button:disabled{opacity:.55;cursor:not-allowed}.cycle-list span{font-size:12px}.cycle-list strong{font-size:14px}.confirm-backdrop{position:fixed;inset:0;z-index:900;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.55)}.confirm-modal{width:min(440px,100%);display:grid;gap:16px;padding:20px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;box-shadow:0 24px 60px rgba(15,23,42,.26)}.confirm-modal header span{color:#059669;font-size:11px;font-weight:900;text-transform:uppercase}.confirm-modal h2{margin:4px 0 0;color:#0f172a}.confirm-summary{display:grid;grid-template-columns:1fr 1fr;gap:10px}.confirm-summary div{display:grid;gap:4px;padding:12px;border:1px solid #d1fae5;border-radius:9px;background:#ecfdf5}.confirm-summary small{color:#047857;font-weight:850}.confirm-summary strong{color:#064e3b}.confirm-modal p{margin:0;color:#475569;font-weight:650;line-height:1.5}.confirm-modal footer{display:flex;justify-content:flex-end;gap:10px}.ghost-btn,.confirm-btn{border:0;border-radius:8px;padding:10px 14px;font:inherit;font-weight:850;cursor:pointer}.ghost-btn{background:#f1f5f9;color:#0f172a}.confirm-btn{background:#16a34a;color:#fff}.confirm-btn:disabled{opacity:.6;cursor:not-allowed}@media(max-width:820px){.plan-grid,.cycle-list,.confirm-summary{grid-template-columns:1fr}.page-head,.current-plan{display:grid}.head-actions{justify-content:start;flex-wrap:wrap}}
</style>
