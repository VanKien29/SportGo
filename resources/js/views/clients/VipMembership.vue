<template>
  <div class="vip-shell sg-client-page">
    <PublicNavbar />
    <main class="vip-page sg-client-shell">
      <!-- Header -->
      <header class="page-head">
        <div class="page-head-copy">
          <h1>Gói hội viên VIP</h1>
          <p class="page-subtitle">Đặc quyền hoàn tiền sau mỗi lượt đặt sân, ưu đãi voucher và ưu tiên hỗ trợ cho người chơi thể thao thường xuyên.</p>
        </div>
        <div class="head-actions">
          <button class="back-btn" type="button" @click="goBack">
            ← Quay lại hồ sơ
          </button>
        </div>
      </header>

      <!-- Alert notifications -->
      <div v-if="error" class="alert error" role="alert">{{ error }}</div>
      <div v-if="success" class="alert success" role="status">{{ success }}</div>
      <div v-if="hasActiveSubscription" class="alert info" role="status">
        Bạn đang có gói VIP còn hiệu lực. Hệ thống hiện áp dụng 1 gói VIP tại một thời điểm cho mỗi tài khoản.
      </div>

      <!-- Current Active Plan Strip (Flat plain text, NO card box) -->
      <section v-if="subscription" class="current-plan-strip" aria-label="Gói hiện tại">
        <div class="current-plan-item">
          <span class="current-plan-label">Gói đang sử dụng:</span>
          <span class="current-plan-val">{{ subscription.package?.label || subscription.package?.name }}</span>
        </div>
        <div class="current-plan-item">
          <span class="current-plan-label">Hạn sử dụng:</span>
          <span class="current-plan-val">{{ date(subscription.expires_at) }}</span>
        </div>
        <div class="current-plan-item">
          <span class="current-plan-label">Đã thanh toán:</span>
          <span class="current-plan-val">{{ money(subscription.paid_amount) }}</span>
        </div>
      </section>

      <!-- QR Payment Section (Flat layout on white, NO card box) -->
      <section
        v-if="paymentInfo && !hasActiveSubscription"
        class="payment-section"
        aria-label="Thông tin thanh toán"
      >
        <div class="payment-intro">
          <span class="payment-title">Thanh toán kích hoạt gói VIP</span>
          <span class="payment-amount">{{ money(paymentInfo.payment?.amount) }}</span>
          <p class="payment-hint">Vui lòng quét mã QR hoặc chuyển khoản chính xác nội dung bên dưới để hệ thống tự động kích hoạt gói.</p>
        </div>

        <div class="payment-layout">
          <div v-if="paymentInfo.qr_url" class="payment-qr-frame">
            <img :src="paymentInfo.qr_url" alt="QR thanh toán VIP" class="payment-qr-img" />
          </div>

          <div class="payment-details-list">
            <div class="payment-field">
              <span class="payment-field-label">Ngân hàng:</span>
              <span class="payment-field-val">
                {{ paymentInfo.payment_account?.bank_name || paymentInfo.system_bank_account?.bank_name || "-" }}
              </span>
            </div>

            <div class="payment-field">
              <span class="payment-field-label">Số tài khoản:</span>
              <span class="payment-field-val">{{ paymentInfo.payment_account?.account_number || paymentInfo.system_bank_account?.account_number || "-" }}</span>
              <button
                type="button"
                class="btn-copy-inline"
                @click="copyText(paymentInfo.payment_account?.account_number || paymentInfo.system_bank_account?.account_number)"
              >
                Sao chép
              </button>
            </div>

            <div class="payment-field">
              <span class="payment-field-label">Nội dung chuyển khoản:</span>
              <span class="payment-field-val">{{ paymentInfo.transfer_content }}</span>
              <button
                type="button"
                class="btn-copy-inline"
                @click="copyText(paymentInfo.transfer_content)"
              >
                Sao chép
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Loading / Empty states -->
      <div v-if="loading" class="state-text">Đang tải danh sách gói VIP...</div>
      <div v-else-if="!paidPackages.length" class="state-text">
        Hiện chưa có gói VIP đang mở bán. Vui lòng quay lại sau.
      </div>

      <!-- Packages Grid (Flat side-by-side columns, NO background cards, NO icon circles) -->
      <section v-else class="plans-columns" aria-label="Danh sách gói VIP">
        <div
          v-for="pkg in paidPackages"
          :key="pkg.id"
          class="plan-column"
        >
          <!-- Plan Title & Description (NO icon wrap) -->
          <div class="plan-header">
            <div class="plan-title-line">
              <h2 class="plan-name">{{ pkg.label || pkg.name }}</h2>
              <span v-if="isCurrentPackage(pkg)" class="plan-current-indicator">(Đang sử dụng)</span>
            </div>
            <p class="plan-desc">{{ packageDescription(pkg) }}</p>
          </div>

          <!-- Price Display (Clean flat text on white) -->
          <div class="plan-price-display">
            <span class="plan-price-val">{{ money(pkg.monthly_price) }}</span>
            <span class="plan-price-period">/ tháng</span>
          </div>

          <!-- Cycles Options -->
          <div class="plan-cycles-block">
            <div class="cycles-header">
              <span class="block-title">Chọn chu kỳ thanh toán</span>
              <span v-if="cycleDiscount(pkg, 'yearly') > 0" class="saving-note">
                Tiết kiệm đến {{ formatPercent(cycleDiscount(pkg, 'yearly')) }}%/năm
              </span>
            </div>

            <div class="cycle-buttons-stack">
              <button
                v-for="cycle in pkg.available_cycles"
                :key="cycle.key"
                type="button"
                class="cycle-btn"
                :disabled="!canPurchasePackage(pkg) || Boolean(subscribing)"
                @click="openConfirm(pkg, cycle)"
              >
                <div class="cycle-btn-left">
                  <span class="cycle-title">{{ cycleLabel(cycle) }}</span>
                  <span v-if="cycleDiscount(pkg, cycle.key) > 0" class="cycle-saving-label">
                    Tiết kiệm {{ formatPercent(cycleDiscount(pkg, cycle.key)) }}%
                  </span>
                </div>
                <div class="cycle-btn-right">
                  <span class="cycle-total">{{ money(cycle.price) }}</span>
                  <span class="cycle-monthly">{{ money(cycleUnitPrice(cycle)) }}/tháng</span>
                </div>
              </button>
            </div>
          </div>

          <!-- Features (Clean plain list, NO icon circles) -->
          <div class="plan-features-block">
            <span class="block-title">Quyền lợi thành viên</span>
            <ul class="features-list">
              <li v-for="feature in packageFeatures(pkg)" :key="feature.key" class="feature-row">
                <AppIcon name="check" :size="14" class="feature-check-icon" />
                <div class="feature-texts">
                  <span class="feature-main">{{ feature.value }}</span>
                  <span class="feature-sub">{{ feature.label }}</span>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </section>

      <!-- Confirmation Modal -->
      <div
        v-if="pendingPurchase"
        class="modal-overlay"
        role="presentation"
        @click.self="closeConfirm"
      >
        <section class="confirm-modal-card" role="dialog" aria-modal="true">
          <header class="confirm-modal-header">
            <h2 class="confirm-modal-title">Xác nhận đăng ký gói VIP</h2>
            <p class="confirm-modal-subtitle">
              {{ pendingPurchase.package.label || pendingPurchase.package.name }}
            </p>
          </header>

          <div class="confirm-summary-grid">
            <div class="confirm-item">
              <span class="confirm-label">Chu kỳ:</span>
              <span class="confirm-val">{{ cycleLabel(pendingPurchase.cycle) }}</span>
            </div>
            <div class="confirm-item">
              <span class="confirm-label">Tổng thanh toán:</span>
              <span class="confirm-val">{{ money(pendingPurchase.cycle.price) }}</span>
            </div>
          </div>

          <p class="confirm-note">
            Sau khi quét mã thanh toán thành công, hệ thống sẽ tự động kích hoạt gói VIP cho tài khoản của bạn.
          </p>

          <footer class="confirm-modal-actions">
            <button
              type="button"
              class="btn-cancel"
              :disabled="Boolean(subscribing)"
              @click="closeConfirm"
            >
              Hủy
            </button>
            <button
              type="button"
              class="btn-confirm"
              :disabled="Boolean(subscribing)"
              @click="confirmPurchase"
            >
              {{ subscribing ? "Đang xử lý..." : "Tiến hành thanh toán" }}
            </button>
          </footer>
        </section>
      </div>
    </main>
  </div>
</template>

<script>
import PublicNavbar from "../../components/PublicNavbar.vue";
import AppIcon from "../../components/AppIcon.vue";
import { vipMembershipService } from "../../services/vipMembershipService.js";

export default {
  name: "VipMembership",
  components: { AppIcon, PublicNavbar },
  data() {
    return {
      packages: [],
      subscription: null,
      loading: false,
      subscribing: "",
      error: "",
      success: "",
      pendingPurchase: null,
      paymentInfo: null,
      paymentPollInterval: null,
    };
  },
  computed: {
    paidPackages() {
      return this.packages.filter(
        (pkg) => pkg.type !== "free" && pkg.is_active,
      );
    },
    hasActiveSubscription() {
      return Boolean(
        this.subscription?.status === "active" &&
        this.subscription?.expires_at &&
        new Date(this.subscription.expires_at) > new Date(),
      );
    },
  },
  mounted() {
    this.load();
  },
  beforeUnmount() {
    this.stopPaymentPolling();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = "";
      try {
        const response = await vipMembershipService.playerIndex();
        this.packages = response.packages || [];
        this.subscription = response.subscription || null;
        if (this.hasActiveSubscription) {
          this.paymentInfo = null;
          this.stopPaymentPolling();
        }
      } catch (error) {
        this.error = error.message || "Không thể tải gói VIP.";
      } finally {
        this.loading = false;
      }
    },
    isCurrentPackage(pkg) {
      return this.subscription?.package?.id === pkg.id;
    },
    canPurchasePackage(pkg) {
      return !this.hasActiveSubscription && !this.isCurrentPackage(pkg);
    },
    goBack() {
      this.$router.push("/profile");
    },
    packageDescription(pkg) {
      if (pkg.type === "saving") return "Tiết kiệm chi phí và tối ưu quyền lợi cho người chơi đều đặn.";
      if (pkg.type === "pro") return "Tối đa đặc quyền hoàn tiền, bài giao lưu và ưu đãi đặt sân.";
      return "Quyền lợi VIP tiện ích dành riêng cho người chơi SportGo.";
    },
    packageFeatures(pkg) {
      const cashback = Number(pkg.cashback_percent || 0);
      const postLimit = Number(pkg.match_post_limit_per_month || 0);
      const voucherCount = Number(pkg.voucher_count_per_month || 0);
      const voucherDiscount = Number(pkg.voucher_discount_percent || 0);
      const voucherMax = Number(pkg.voucher_max_discount_amount || 0);
      const voucherMin = Number(pkg.voucher_min_order_amount || 0);

      return [
        {
          key: "cashback",
          value: cashback > 0 ? `Hoàn tiền ${this.formatPercent(cashback)}%` : "Không có hoàn tiền",
          label: "Cộng trực tiếp vào ví sau khi hoàn tất lượt đặt sân",
        },
        {
          key: "matchmaking",
          value: this.postLimitText(postLimit),
          label: "Đăng tin tìm đối giao lưu trên hệ thống",
        },
        {
          key: "voucher-count",
          value: voucherCount > 0 ? `${voucherCount} voucher VIP / tháng` : "Không có voucher VIP",
          label: "Tự động phát vào đầu mỗi chu kỳ thành viên",
        },
        {
          key: "voucher-value",
          value: voucherDiscount > 0 ? `Ưu đãi giảm ${this.formatPercent(voucherDiscount)}%` : "Ưu đãi chuẩn",
          label: voucherDiscount > 0 && voucherMax > 0
            ? `Tối đa ${this.money(voucherMax)} · đơn từ ${this.money(voucherMin)}`
            : "Áp dụng theo chính sách gói",
        },
        {
          key: "complaint",
          value: pkg.priority_complaint ? "Ưu tiên hỗ trợ & xử lý khiếu nại" : "Hỗ trợ tiêu chuẩn",
          label: "Thời gian tiếp nhận và phản hồi nhanh chóng",
        },
      ];
    },
    cycleDiscount(pkg, cycle) {
      return Number(pkg.pricing_discounts?.[cycle] || 0);
    },
    cycleUnitPrice(cycle) {
      const months = Number(cycle.months || 1);
      return Number(cycle.price || 0) / Math.max(months, 1);
    },
    formatPercent(value) {
      return Number(value || 0).toLocaleString("vi-VN", { maximumFractionDigits: 2 });
    },
    cycleLabel(cycle) {
      return (
        {
          monthly: "Hằng tháng (1 tháng)",
          quarterly: "Hằng quý (3 tháng)",
          yearly: "Hằng năm (12 tháng)",
        }[cycle.key] ||
        cycle.label ||
        "Chu kỳ"
      );
    },
    openConfirm(pkg, cycle) {
      this.error = "";
      this.success = "";
      if (!this.canPurchasePackage(pkg)) {
        this.error =
          "Bạn đang có gói VIP còn hiệu lực. Không thể mua thêm hoặc đổi sang gói khác cho đến khi gói hiện tại hết hạn.";
        return;
      }
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
      this.error = "";
      try {
        const response = await vipMembershipService.subscribe({
          package_id: pkg.id,
          billing_cycle: cycle.key,
        });
        this.success =
          response.message || "Đã tạo thông tin thanh toán gói VIP.";
        this.paymentInfo = response;
        this.pendingPurchase = null;
        this.startPaymentPolling();
      } catch (error) {
        this.error = error.message || "Không thể kích hoạt gói VIP.";
      } finally {
        this.subscribing = "";
      }
    },
    startPaymentPolling() {
      this.stopPaymentPolling();
      this.paymentPollInterval = setInterval(() => {
        this.load();
      }, 5000);
    },
    stopPaymentPolling() {
      if (!this.paymentPollInterval) return;
      clearInterval(this.paymentPollInterval);
      this.paymentPollInterval = null;
    },
    async copyText(value) {
      if (!value) return;
      try {
        await navigator.clipboard.writeText(String(value));
        this.success = "Đã sao chép thông tin thanh toán.";
      } catch {
        this.error = "Không thể sao chép. Vui lòng sao chép thủ công.";
      }
    },
    postLimitText(limit) {
      return Number(limit) < 0
        ? "Đăng bài giao lưu không giới hạn"
        : `${Number(limit || 0)} bài giao lưu / tháng`;
    },
    money(value) {
      return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
      }).format(value || 0);
    },
    date(value) {
      return value ? new Date(value).toLocaleDateString("vi-VN") : "-";
    },
  },
};
</script>

<style scoped>
/* ==========================================================================
   VIP MEMBERSHIP PAGE - ULTRA MINIMALIST, FLAT & SINGLE WHITE SURFACE
   - 100% pure white background (#ffffff)
   - Zero card boxes, zero box shadows, zero icon wrappers
   - Zero badges/chips/pills, zero emoji, zero gradients
   - Strict font-weight: 400 throughout
   - Crisp, high-contrast dark text (#0f172a, #334155, #475569)
   - Clean choice buttons with thin sharp outline (#94a3b8)
   ========================================================================== */
.vip-shell {
  min-height: 100vh;
  background: #ffffff !important;
  color: #0f172a;
  font-family: inherit;
}

/* Global reset to font-weight 400 throughout */
.vip-shell *,
.vip-shell h1,
.vip-shell h2,
.vip-shell button,
.vip-shell span,
.vip-shell strong,
.vip-shell small,
.vip-shell p,
.vip-shell label,
.vip-shell li {
  font-weight: 400 !important;
  background-image: none !important;
}

.vip-page {
  max-width: 1100px;
  margin: 0 auto;
  padding: 32px 24px 64px;
}

/* Page Header - No bottom border */
.page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 24px;
  border: none !important;
}

.page-head-copy {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-width: 720px;
}

.page-head-copy h1 {
  font-size: 24px;
  color: #0f172a;
  margin: 0;
  line-height: 1.25;
}

.page-subtitle {
  font-size: 14px;
  color: #334155;
  line-height: 1.5;
  margin: 0;
}

.head-actions {
  flex-shrink: 0;
}

.back-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border: 1px solid #94a3b8;
  background: #ffffff;
  color: #334155;
  border-radius: 6px;
  font-size: 13.5px;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.back-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
  border-color: #64748b;
}

/* Alerts */
.alert {
  padding: 11px 15px;
  border-radius: 6px;
  font-size: 13.5px;
  line-height: 1.45;
  margin-bottom: 18px;
  border: 1px solid transparent;
}

.alert.error {
  background: #fef2f2;
  border-color: #fecaca;
  color: #b91c1c;
}

.alert.success {
  background: #f0fdf4;
  border-color: #bbf7d0;
  color: #15803d;
}

.alert.info {
  background: #f8fafc;
  border-color: #cbd5e1;
  color: #334155;
}

/* Current Plan Strip - Plain flat row on white (NO card box) */
.current-plan-strip {
  display: flex;
  align-items: center;
  gap: 28px;
  flex-wrap: wrap;
  margin-bottom: 24px;
  padding: 10px 0;
  border: none !important;
}

.current-plan-item {
  display: flex;
  align-items: baseline;
  gap: 8px;
  font-size: 13.5px;
}

.current-plan-label {
  color: #475569;
}

.current-plan-val {
  color: #0f172a;
}

/* QR Payment Section - Flat on white (NO card box) */
.payment-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 32px;
  padding: 0 0 20px 0;
  border: none !important;
}

.payment-intro {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.payment-title {
  font-size: 14px;
  color: #475569;
}

.payment-amount {
  font-size: 22px;
  color: #0f172a;
}

.payment-hint {
  font-size: 13px;
  color: #334155;
  margin: 2px 0 0;
}

.payment-layout {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 20px;
  align-items: center;
}

.payment-qr-frame {
  width: 140px;
  height: 140px;
  border: 1px solid #94a3b8;
  border-radius: 6px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ffffff;
}

.payment-qr-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.payment-details-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.payment-field {
  display: flex;
  align-items: baseline;
  gap: 8px;
  font-size: 13.5px;
}

.payment-field-label {
  color: #475569;
}

.payment-field-val {
  color: #0f172a;
}

.btn-copy-inline {
  padding: 2px 7px;
  border: 1px solid #94a3b8;
  background: #ffffff;
  color: #0f172a;
  border-radius: 4px;
  font-size: 11.5px;
  cursor: pointer;
  margin-left: 6px;
  transition: background 0.15s ease;
}

.btn-copy-inline:hover {
  background: #f1f5f9;
}

/* Loading & Empty State */
.state-text {
  padding: 40px 0;
  text-align: center;
  color: #475569;
  font-size: 14px;
}

/* Plans Columns (Flat columns on white, NO background cards, NO shadows) */
.plans-columns {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 40px;
  align-items: start;
}

.plan-column {
  display: flex;
  flex-direction: column;
  gap: 20px;
  background: transparent;
  border: none !important;
  padding: 0;
  box-shadow: none !important;
}

/* Plan Header (NO icon wrap box) */
.plan-header {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.plan-title-line {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.plan-name {
  font-size: 20px;
  color: #0f172a;
  margin: 0;
  line-height: 1.25;
}

.plan-current-indicator {
  font-size: 13px;
  color: #15803d;
}

.plan-desc {
  font-size: 13px;
  color: #475569;
  margin: 0;
  line-height: 1.45;
}

/* Price Display (Clean flat text) */
.plan-price-display {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.plan-price-val {
  font-size: 24px;
  color: #0f172a;
}

.plan-price-period {
  font-size: 13px;
  color: #475569;
}

/* Cycles Section */
.plan-cycles-block {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cycles-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.block-title {
  font-size: 13.5px;
  color: #0f172a;
}

.saving-note {
  font-size: 12px;
  color: #15803d;
}

.cycle-buttons-stack {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cycle-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 14px;
  border: 1px solid #94a3b8;
  border-radius: 6px;
  background: #ffffff;
  color: #0f172a;
  cursor: pointer;
  text-align: left;
  transition: border-color 0.15s ease, background 0.15s ease;
  box-sizing: border-box;
}

.cycle-btn:hover:not(:disabled) {
  border-color: #0f172a;
  background: #f8fafc;
}

.cycle-btn:disabled {
  background: #ffffff;
  color: #94a3b8;
  cursor: not-allowed;
  border-color: #cbd5e1;
}

.cycle-btn-left {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cycle-title {
  font-size: 13px;
  color: #0f172a;
}

.cycle-saving-label {
  font-size: 11.5px;
  color: #15803d;
}

.cycle-btn-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.cycle-total {
  font-size: 13.5px;
  color: #0f172a;
}

.cycle-monthly {
  font-size: 11.5px;
  color: #475569;
}

/* Features Block (NO circular icon wrappers) */
.plan-features-block {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.features-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.feature-row {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 13px;
  line-height: 1.4;
}

.feature-check-icon {
  color: #0f172a;
  flex-shrink: 0;
  margin-top: 3px;
}

.feature-texts {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.feature-main {
  color: #0f172a;
}

.feature-sub {
  font-size: 12px;
  color: #475569;
}

/* Confirmation Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: rgba(15, 23, 42, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.confirm-modal-card {
  width: 100%;
  max-width: 440px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.confirm-modal-header {
  display: flex;
  flex-direction: column;
  gap: 4px;
  border: none !important;
}

.confirm-modal-title {
  font-size: 18px;
  color: #0f172a;
  margin: 0;
}

.confirm-modal-subtitle {
  font-size: 13px;
  color: #334155;
  margin: 0;
}

.confirm-summary-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px 0;
}

.confirm-item {
  display: flex;
  align-items: baseline;
  gap: 8px;
  font-size: 13.5px;
}

.confirm-label {
  color: #475569;
}

.confirm-val {
  color: #0f172a;
}

.confirm-note {
  font-size: 13px;
  color: #334155;
  line-height: 1.5;
  margin: 0;
}

.confirm-modal-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 4px;
  border: none !important;
}

.btn-cancel {
  padding: 8px 16px;
  border: 1px solid #94a3b8;
  background: #ffffff;
  color: #334155;
  border-radius: 6px;
  font-size: 13.5px;
  cursor: pointer;
  transition: background 0.15s ease;
}

.btn-cancel:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.btn-confirm {
  padding: 8px 18px;
  border: none;
  background: #0f172a;
  color: #ffffff;
  border-radius: 6px;
  font-size: 13.5px;
  cursor: pointer;
  transition: background 0.15s ease;
}

.btn-confirm:hover {
  background: #1e293b;
}

.btn-confirm:disabled {
  background: #cbd5e1;
  cursor: not-allowed;
}

/* Responsive */
@media (max-width: 768px) {
  .plans-columns {
    grid-template-columns: 1fr;
    gap: 32px;
  }

  .current-plan-strip {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .payment-layout {
    grid-template-columns: 1fr;
  }

  .payment-qr-frame {
    margin: 0 auto;
  }
}
</style>
