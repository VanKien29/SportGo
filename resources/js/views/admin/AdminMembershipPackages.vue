<template>
  <div class="cluster-profile-surface standalone">
    <div class="profile-section-card vip-packages-main-content">
      <section class="vip-admin-page">
        <div v-if="error" class="vip-feedback vip-feedback-error" role="alert">{{ error }}</div>
        <div v-if="success" class="vip-feedback vip-feedback-success" role="status">{{ success }}</div>

        <header class="vip-page-header">
          <div class="vip-page-heading">
            <span class="vip-eyebrow">SẢN PHẨM & DỊCH VỤ</span>
            <h1>Gói VIP hệ thống</h1>
            <p>Quản lý giá và quyền lợi dành cho người chơi trong cùng một màn hình.</p>
          </div>
          <div class="vip-page-actions">
            <span class="vip-data-note">Dữ liệu trực tiếp từ hệ thống</span>
            <button class="vip-button vip-button-secondary" type="button" :disabled="loading" @click="refreshPage">
              <AppIcon name="refresh" size="16" />
              <span>Làm mới</span>
            </button>
          </div>
        </header>

        <section v-if="!loading" class="vip-summary" aria-label="Tổng quan gói VIP">
          <article class="vip-summary-item">
            <span class="vip-summary-label">Tổng số gói</span>
            <span class="vip-summary-value">{{ packages.length }}</span>
            <span class="vip-summary-note">Bao gồm gói mặc định</span>
          </article>
          <article class="vip-summary-item">
            <span class="vip-summary-label">Gói đang mở bán</span>
            <span class="vip-summary-value">{{ activePaidPackageCount }}</span>
            <span class="vip-summary-note">Trên {{ paidPackageCount }} gói trả phí</span>
          </article>
          <article class="vip-summary-item">
            <span class="vip-summary-label">Lượt voucher theo gói</span>
            <span class="vip-summary-value">{{ voucherBenefitTotal }}</span>
            <span class="vip-summary-note">Quyền lợi cấu hình mỗi tháng</span>
          </article>
          <article class="vip-summary-item vip-summary-item-accent">
            <span class="vip-summary-label">Cập nhật giá</span>
            <span class="vip-summary-value">Tự động</span>
            <span class="vip-summary-note">Quý và năm tính theo giá tháng</span>
          </article>
        </section>

        <section class="vip-plans-section" aria-labelledby="vip-plans-title">
          <header class="vip-section-header">
            <div>
              <span class="vip-eyebrow">CẤU HÌNH GÓI</span>
              <h2 id="vip-plans-title">Giá và quyền lợi</h2>
            </div>
            <p>Giá theo quý và năm được tính tự động theo mức giảm đã cấu hình cho từng loại gói.</p>
          </header>

          <div v-if="loading" class="vip-loading" role="status">
            <AppIcon name="refresh" size="18" />
            <span>Đang tải cấu hình gói VIP...</span>
          </div>
          <div v-else-if="!packages.length" class="vip-empty">Chưa có gói VIP để hiển thị.</div>
          <div v-else class="vip-plan-grid">
            <form v-for="(pkg, index) in packages" :key="pkg.id" class="vip-plan" novalidate @submit.prevent="save(pkg)">
              <header class="vip-plan-header">
                <div class="vip-plan-heading">
                  <span class="vip-plan-index">{{ String(index + 1).padStart(2, '0') }}</span>
                  <div>
                    <span class="vip-plan-type">{{ pkg.type === 'free' ? 'GÓI MẶC ĐỊNH' : 'GÓI TRẢ PHÍ' }}</span>
                    <h3>{{ pkg.label || pkg.name }}</h3>
                    <p>{{ pkg.type === 'free' ? 'Nền tảng cơ bản cho mọi tài khoản.' : 'Mở rộng quyền lợi cho người chơi thường xuyên.' }}</p>
                  </div>
                </div>
                <label v-if="pkg.type !== 'free'" class="vip-active-field">
                  <input v-model="pkg.is_active" type="checkbox" />
                  <span>{{ pkg.is_active ? 'Đang mở bán' : 'Đang tắt' }}</span>
                </label>
                <span v-else class="vip-plan-state">Luôn khả dụng</span>
              </header>

              <div class="vip-plan-body">
                <div class="vip-field-grid vip-field-grid-two">
                  <label class="vip-field">
                    <span>Tên gói</span>
                    <input v-model.trim="pkg.name" autocomplete="off" />
                  </label>
                  <label class="vip-field">
                    <span>Bài giao lưu / tháng</span>
                    <input v-model.trim="pkg.match_post_limit_per_month" type="text" inputmode="numeric" />
                    <small>-1 là không giới hạn</small>
                  </label>
                </div>

                <div v-if="pkg.type === 'free'" class="vip-free-note">
                  <span class="vip-free-note-title">Gói nền tảng</span>
                  <span>Không thu phí và không áp dụng quyền lợi cashback, voucher hoặc ưu tiên khiếu nại.</span>
                </div>

                <template v-else>
                  <div class="vip-subsection">
                    <div class="vip-subsection-heading">
                      <span>Giá theo chu kỳ</span>
                      <small>VND</small>
                    </div>
                    <div class="vip-field-grid vip-field-grid-three">
                      <label class="vip-field">
                        <span>1 tháng</span>
                        <input :value="monthlyPriceText(pkg)" type="text" inputmode="numeric" @focus="beginMonthlyPriceEdit(pkg, $event)" @input="updateMonthlyPrice(pkg, $event)" @blur="endMonthlyPriceEdit(pkg, $event)" />
                      </label>
                      <label class="vip-field">
                        <span>3 tháng · giảm {{ pricingDiscountLabel(pkg, 'quarterly') }}%</span>
                        <input :value="money(pkg.quarterly_price)" readonly />
                      </label>
                      <label class="vip-field">
                        <span>1 năm · giảm {{ pricingDiscountLabel(pkg, 'yearly') }}%</span>
                        <input :value="money(pkg.yearly_price)" readonly />
                      </label>
                    </div>
                  </div>

                  <div class="vip-subsection">
                    <div class="vip-subsection-heading">
                      <span>Quyền lợi thành viên</span>
                      <small>Áp dụng khi gói còn hiệu lực</small>
                    </div>
                    <div class="vip-field-grid vip-field-grid-two">
                      <label class="vip-field">
                        <span>Hoàn tiền booking</span>
                        <span class="vip-input-suffix"><input v-model.trim="pkg.cashback_percent" type="text" inputmode="decimal" /><span>%</span></span>
                      </label>
                      <label class="vip-field">
                        <span>Danh hiệu hiển thị</span>
                        <input v-model.trim="pkg.badge_name" autocomplete="off" />
                      </label>
                      <label class="vip-field">
                        <span>Voucher VIP / tháng</span>
                        <input v-model.trim="pkg.voucher_count_per_month" type="text" inputmode="numeric" />
                      </label>
                      <label class="vip-field">
                        <span>Giảm giá voucher</span>
                        <span class="vip-input-suffix"><input v-model.trim="pkg.voucher_discount_percent" type="text" inputmode="decimal" /><span>%</span></span>
                      </label>
                      <label class="vip-field">
                        <span>Đơn tối thiểu</span>
                        <input v-model.trim="pkg.voucher_min_order_amount" type="text" inputmode="numeric" />
                      </label>
                      <label class="vip-field">
                        <span>Trần giảm / tháng</span>
                        <input v-model.trim="pkg.voucher_max_discount_amount" type="text" inputmode="numeric" />
                      </label>
                    </div>
                  </div>

                  <label class="vip-check-field">
                    <input v-model="pkg.priority_complaint" type="checkbox" />
                    <span>Ưu tiên xử lý khiếu nại của thành viên gói này</span>
                  </label>
                </template>
              </div>

              <footer class="vip-plan-footer">
                <span>Thay đổi chỉ có hiệu lực sau khi lưu.</span>
                <button class="vip-button vip-button-primary" type="submit" :disabled="savingId === pkg.id">
                  <AppIcon name="check" size="16" />
                  <span>{{ savingId === pkg.id ? 'Đang lưu...' : 'Lưu gói' }}</span>
                </button>
              </footer>
            </form>
          </div>
        </section>

      </section>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { vipMembershipService } from '../../services/vipMembershipService.js';

export default {
  name: 'AdminMembershipPackages',
  components: { AppIcon },
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
  computed: {
    voucherBenefitTotal() {
      return this.packages
        .filter((pkg) => pkg.type !== 'free')
        .reduce((total, pkg) => total + Math.max(Number(pkg.voucher_count_per_month) || 0, 0), 0);
    },
    paidPackageCount() {
      return this.packages.filter((pkg) => pkg.type !== 'free').length;
    },
    activePaidPackageCount() {
      return this.packages.filter((pkg) => pkg.type !== 'free' && pkg.is_active).length;
    },
  },
  methods: {
    async refreshPage() {
      await this.load();
    },
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const response = await vipMembershipService.adminPackages();
        this.packages = (response.data || [])
          .map((pkg) => this.decoratePackage(pkg))
          .sort((a, b) => this.packageSortOrder(a) - this.packageSortOrder(b));
      } catch (error) {
        this.error = error.message || 'Không thể tải gói VIP.';
      } finally {
        this.loading = false;
      }
    },
    payload(pkg) {
      this.recalculatePaidPrices(pkg);
      return {
        name: pkg.name,
        monthly_price: this.integerInputValue(pkg.monthly_price),
        voucher_count_per_month: pkg.type === 'free' ? 0 : this.integerInputValue(pkg.voucher_count_per_month || 0),
        voucher_discount_percent: pkg.type === 'free' ? 0 : this.decimalInputValue(pkg.voucher_discount_percent || 0),
        voucher_min_order_amount: pkg.type === 'free' ? 0 : this.integerInputValue(pkg.voucher_min_order_amount || 0),
        voucher_max_discount_amount: pkg.type === 'free' || pkg.voucher_max_discount_amount === '' || pkg.voucher_max_discount_amount === null
          ? null
          : this.integerInputValue(pkg.voucher_max_discount_amount),
        cashback_percent: pkg.type === 'free' ? 0 : this.decimalInputValue(pkg.cashback_percent || 0),
        match_post_limit_per_month: this.integerInputValue(pkg.match_post_limit_per_month || 0),
        priority_complaint: Boolean(pkg.priority_complaint),
        badge_name: pkg.type === 'free' ? null : pkg.badge_name || null,
        is_active: pkg.type === 'free' ? true : Boolean(pkg.is_active),
        sort_order: this.packageSortOrder(pkg),
      };
    },
    packageSortOrder(pkg) {
      return {
        free: 1,
        saving: 2,
        pro: 3,
      }[pkg.type] || Number(pkg.sort_order || 99);
    },
    validatePackage(pkg) {
      const monthlyPrice = this.integerInputValue(pkg.monthly_price);
      const postLimit = this.integerInputValue(pkg.match_post_limit_per_month);
      const cashback = this.decimalInputValue(pkg.cashback_percent || 0);
      const voucherCount = this.integerInputValue(pkg.voucher_count_per_month || 0);
      const voucherDiscount = this.decimalInputValue(pkg.voucher_discount_percent || 0);
      const voucherMinOrder = this.integerInputValue(pkg.voucher_min_order_amount || 0);
      const voucherMaxDiscount = pkg.voucher_max_discount_amount === '' || pkg.voucher_max_discount_amount === null
        ? 0
        : this.integerInputValue(pkg.voucher_max_discount_amount);

      if (!pkg.name) {
        this.error = 'Vui lòng nhập tên gói.';
        return false;
      }

      if (!Number.isFinite(postLimit) || postLimit < -1) {
        this.error = 'Bài giao lưu/tháng chỉ được nhập -1 hoặc số từ 0 trở lên. -1 nghĩa là không giới hạn.';
        return false;
      }

      if (pkg.type === 'free') {
        return true;
      }

      if (!Number.isInteger(monthlyPrice) || monthlyPrice < 1000) {
        this.error = 'Giá 1 tháng phải là số nguyên VND từ 1.000đ trở lên.';
        return false;
      }

      if (!Number.isFinite(cashback) || cashback < 0 || cashback > 100) {
        this.error = '% Hoàn tiền phải nằm trong khoảng 0 đến 100.';
        return false;
      }

      if (!Number.isFinite(voucherCount) || voucherCount < 0 || voucherCount > 50) {
        this.error = 'Voucher VIP/tháng phải nằm trong khoảng 0 đến 50.';
        return false;
      }

      if (!Number.isFinite(voucherDiscount) || voucherDiscount < 0 || voucherDiscount > 100) {
        this.error = '% giảm voucher phải nằm trong khoảng 0 đến 100.';
        return false;
      }

      if (!Number.isInteger(voucherMinOrder) || voucherMinOrder < 0) {
        this.error = 'Đơn tối thiểu phải là số nguyên VND không âm.';
        return false;
      }

      if (!Number.isInteger(voucherMaxDiscount) || voucherMaxDiscount < 0) {
        this.error = 'Trần giảm voucher hằng tháng phải là số nguyên VND không âm.';
        return false;
      }

      return true;
    },
    decoratePackage(pkg) {
      const decorated = { ...pkg };
      const defaultNames = {
        free: 'Thường',
        saving: 'Tiết kiệm',
        pro: 'Pro',
      };
      const normalizedName = String(pkg.name || '').trim().toLowerCase();
      if (
        (pkg.type === 'free' && normalizedName === 'thuong') ||
        (pkg.type === 'saving' && normalizedName === 'tiet kiem')
      ) {
        decorated.name = defaultNames[pkg.type];
      }
      decorated.monthly_price = this.displayInteger(pkg.monthly_price ?? 0);
      decorated.quarterly_price = pkg.quarterly_price === null ? null : this.displayInteger(pkg.quarterly_price);
      decorated.yearly_price = pkg.yearly_price === null ? null : this.displayInteger(pkg.yearly_price);
      decorated.match_post_limit_per_month = this.displayInteger(pkg.match_post_limit_per_month ?? 0);
      decorated.voucher_count_per_month = this.displayInteger(pkg.voucher_count_per_month ?? 0);
      decorated.voucher_min_order_amount = this.displayInteger(pkg.voucher_min_order_amount ?? 0);
      decorated.voucher_max_discount_amount = pkg.voucher_max_discount_amount === null ? '' : this.displayInteger(pkg.voucher_max_discount_amount);
      decorated.cashback_percent = this.displayDecimal(pkg.cashback_percent ?? 0);
      decorated.voucher_discount_percent = this.displayDecimal(pkg.voucher_discount_percent ?? 0);

      if (decorated.type === 'free') {
        decorated.monthly_price = '0';
        decorated.quarterly_price = null;
        decorated.yearly_price = null;
        decorated.is_active = true;
      } else {
        this.recalculatePaidPrices(decorated);
      }

      return decorated;
    },
    recalculatePaidPrices(pkg) {
      if (pkg.type === 'free') {
        pkg.monthly_price = '0';
        pkg.quarterly_price = null;
        pkg.yearly_price = null;
        return;
      }

      const monthlyPrice = this.integerInputValue(pkg.monthly_price);
      const quarterlyDiscount = Number(pkg.pricing_discounts?.quarterly || 0);
      const yearlyDiscount = Number(pkg.pricing_discounts?.yearly || 0);

      if (Number.isInteger(monthlyPrice) && monthlyPrice > 0 && Number.isFinite(quarterlyDiscount)) {
        pkg.quarterly_price = this.periodPrice(monthlyPrice, 3, quarterlyDiscount);
      }

      if (Number.isInteger(monthlyPrice) && monthlyPrice > 0 && Number.isFinite(yearlyDiscount)) {
        pkg.yearly_price = this.periodPrice(monthlyPrice, 12, yearlyDiscount);
      }
    },
    periodPrice(monthlyPrice, months, discountPercent) {
      const clampedDiscount = Math.min(Math.max(Number(discountPercent || 0), 0), 100);
      return Math.round(monthlyPrice * months * (100 - clampedDiscount) / 100 / 1000) * 1000;
    },
    monthlyPriceText(pkg) {
      const monthlyPrice = this.integerInputValue(pkg.monthly_price);
      if (pkg.editing_monthly_price) return String(pkg.monthly_price ?? '');
      return Number.isInteger(monthlyPrice) ? this.money(monthlyPrice) : String(pkg.monthly_price ?? '');
    },
    beginMonthlyPriceEdit(pkg, event) {
      pkg.editing_monthly_price = true;
      this.$nextTick(() => {
        event.target.value = String(pkg.monthly_price ?? '');
        event.target.select();
      });
    },
    updateMonthlyPrice(pkg, event) {
      pkg.monthly_price = String(event.target.value || '').replace(/\D/g, '');
      this.recalculatePaidPrices(pkg);
    },
    endMonthlyPriceEdit(pkg, event) {
      pkg.editing_monthly_price = false;
      this.$nextTick(() => {
        event.target.value = this.monthlyPriceText(pkg);
      });
    },
    pricingDiscountLabel(pkg, cycle) {
      return this.displayDecimal(pkg.pricing_discounts?.[cycle] || 0);
    },
    normalizedNumericText(value) {
      return String(value ?? '').trim().replace(',', '.');
    },
    decimalInputValue(value) {
      const normalized = this.normalizedNumericText(value);
      return /^-?\d+(?:\.\d+)?$/.test(normalized) ? Number(normalized) : NaN;
    },
    integerInputValue(value) {
      const normalized = String(value ?? '').trim();
      return /^-?\d+$/.test(normalized) ? Number(normalized) : NaN;
    },
    displayInteger(value) {
      const numeric = Number(value || 0);
      return Number.isFinite(numeric) ? String(Math.round(numeric)) : '';
    },
    displayDecimal(value) {
      const numeric = Number(value || 0);
      if (!Number.isFinite(numeric)) return '0';
      return numeric.toLocaleString('vi-VN', { maximumFractionDigits: 2 });
    },
    async save(pkg) {
      if (!this.validatePackage(pkg)) return;

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
    cycleLabel(cycle) {
      return {
        monthly: 'Tháng',
        quarterly: 'Quý',
        yearly: 'Năm',
      }[cycle.key] || cycle.label || cycle.key;
    },
    postLimitLabel(value) {
      return Number(value) < 0 ? '∞' : Number(value || 0);
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
    },
    date(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    toDatetimeLocal(value) {
      const date = value instanceof Date ? value : new Date(value);
      return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    },
  },
};
</script>

<style scoped>
.vip-admin-page{display:grid;gap:16px}.alert,.state{padding:12px 14px;border-radius:10px;font-weight:400}.alert.error{background:var(--admin-danger-soft);color:var(--admin-danger-text)}.alert.success{background:var(--admin-success-soft);color:var(--admin-success-text)}.state{background:var(--admin-surface);border:1px solid var(--admin-border);color:var(--admin-muted)}.package-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.package-card,.voucher-section{display:grid;gap:14px;padding:16px;border:1px solid var(--admin-border);border-radius:12px;background:var(--admin-surface)}.package-card header,.section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.package-card h3,.section-head h3{margin:3px 0 0;color:var(--admin-text)}.package-card header span,.section-head span{color:var(--admin-success-text);font-size:11px;font-weight:400;text-transform:uppercase}.grid,.voucher-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}label{display:grid;gap:5px;color:var(--admin-muted);font-size:12px;font-weight:400}input,select,textarea{border:1px solid var(--admin-border);border-radius:8px;padding:0 10px;font:inherit}input,select{height:38px}textarea{padding-top:10px;resize:vertical}input[readonly]{background:var(--admin-surface-muted);color:var(--admin-text);font-weight:400}.package-note{margin:0;border-radius:8px;background:var(--admin-surface-muted);color:var(--admin-muted);font-size:12px;font-weight:400;line-height:1.45;padding:10px 12px}.toggle,.check{display:flex;align-items:center;gap:8px}.toggle input,.check input{width:16px;height:16px}.btn,.mini-btn{border:0;border-radius:8px;font-weight:400;cursor:pointer}.btn{padding:10px 14px}.mini-btn{padding:7px 10px}.primary{background:var(--admin-primary);color:var(--admin-primary-text)}.secondary{background:var(--admin-surface-muted);color:var(--admin-text)}.danger{background:var(--admin-danger-soft);color:var(--admin-danger-text)}.primary:disabled,.mini-btn:disabled{opacity:.55;cursor:not-allowed}.voucher-form{display:grid;gap:12px}.voucher-actions{display:flex;justify-content:flex-end;gap:10px}.voucher-table{overflow:auto;border:1px solid var(--admin-border);border-radius:10px}table{width:100%;min-width:1040px;border-collapse:collapse}th,td{padding:11px;border-bottom:1px solid var(--admin-border);text-align:left;vertical-align:middle}tbody tr:last-child td{border-bottom:0}.badge{border-radius:999px;padding:5px 9px;font-size:12px;font-weight:400;background:var(--admin-border)}.badge.active{background:var(--admin-success-soft);color:var(--admin-success-text)}.badge.inactive,.badge.expired{background:var(--admin-danger-soft);color:var(--admin-danger-text)}.badge.draft{background:var(--admin-surface-muted);color:var(--admin-muted)}.actions-col{text-align:right}.modal-backdrop{position:fixed;inset:0;z-index:900;display:grid;place-items:center;background:color-mix(in srgb,var(--admin-bg) 72%,transparent);padding:20px}.confirm-modal{display:grid;gap:12px;width:min(440px,calc(100vw - 32px));border:1px solid var(--admin-border);border-radius:10px;background:var(--admin-surface);padding:20px}.confirm-modal h3,.confirm-modal p{margin:0}.confirm-modal p{color:var(--admin-muted);font-weight:400;line-height:1.5}
.package-overview{display:grid;gap:18px;padding:20px;border:1px solid var(--admin-border);border-radius:16px;background:linear-gradient(135deg,var(--admin-primary-soft),var(--admin-surface))}.package-overview__heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px}.package-overview__heading>div{display:grid;gap:5px}.package-overview__heading span,.package-editor__heading span{color:var(--admin-success-text);font-size:11px;font-weight:800;letter-spacing:.08em}.package-overview__heading h2,.package-editor__heading h2{margin:0;color:var(--admin-text);font-size:22px}.package-overview__heading p,.package-editor__heading p{margin:0;color:var(--admin-muted);font-size:12px;line-height:1.5}.package-overview__source{padding:7px 10px;border:1px solid var(--admin-border);border-radius:999px;background:var(--admin-surface);color:var(--admin-muted)!important;font-size:11px!important;font-weight:650!important;letter-spacing:0!important}.package-overview__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.package-overview__card{display:grid;align-content:start;gap:15px;min-width:0;padding:18px;border:1px solid var(--admin-border);border-radius:14px;background:var(--admin-surface);box-shadow:0 12px 24px color-mix(in srgb,var(--admin-text) 7%,transparent)}.package-overview__card.is-saving{border-color:color-mix(in srgb,var(--admin-primary) 50%,var(--admin-border))}.package-overview__card.is-pro{border-color:color-mix(in srgb,#d97706 45%,var(--admin-border));background:color-mix(in srgb,#fff7ed 62%,var(--admin-surface))}.package-overview__card header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.package-overview__card h3{margin:3px 0 0;color:var(--admin-text);font-size:20px}.package-overview__eyebrow{color:var(--admin-success-text);font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.package-overview__status{padding:5px 8px;border-radius:999px;background:var(--admin-success-soft);color:var(--admin-success-text);font-size:10px;font-weight:800}.package-overview__status.inactive{background:var(--admin-danger-soft);color:var(--admin-danger-text)}.package-overview__price{display:flex;align-items:baseline;gap:5px;padding-bottom:14px;border-bottom:1px solid var(--admin-border)}.package-overview__price strong{color:var(--admin-text);font-size:30px;letter-spacing:-.04em}.package-overview__price span{color:var(--admin-muted);font-size:12px}.package-overview__cycles{display:grid;gap:7px}.package-overview__cycles div{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 10px;border-radius:9px;background:var(--admin-surface-muted)}.package-overview__cycles span{color:var(--admin-muted);font-size:11px}.package-overview__cycles strong{color:var(--admin-text);font-size:12px}.package-overview__card ul{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin:0;padding:0;list-style:none}.package-overview__card li{display:grid;gap:2px;padding-top:9px;border-top:1px solid var(--admin-border)}.package-overview__card li strong{color:var(--admin-text);font-size:13px}.package-overview__card li span{color:var(--admin-muted);font-size:10px}.package-editor{display:grid;gap:14px}.package-editor__heading{display:flex;align-items:end;justify-content:space-between;gap:16px}.package-editor__heading>div{display:grid;gap:5px}.package-editor__heading p{max-width:460px;text-align:right}.package-grid{align-items:stretch}.package-card{align-content:start;grid-auto-rows:max-content}.package-card>.btn{align-self:start;min-height:40px}.suffix-field{display:grid;grid-template-columns:minmax(0,1fr)38px;align-items:center;overflow:hidden;border:1px solid var(--admin-border);border-radius:8px;background:var(--admin-surface)}.suffix-field input{width:100%;min-width:0;height:36px;border:0;border-radius:0}.suffix-field span{display:grid;height:100%;place-items:center;border-left:1px solid var(--admin-border);background:var(--admin-surface-muted);color:var(--admin-muted);font-weight:400}.package-card label.invalid input,.package-card label.invalid select,.package-card label.invalid textarea{border-color:var(--admin-danger);background:var(--admin-danger-soft)}.field-error{color:var(--admin-danger);font-size:11px;font-weight:400;line-height:1.35}
.package-grid{align-items:start}
.package-card{align-content:start;grid-auto-rows:max-content}
.package-card>.btn{align-self:start;min-height:40px}
.suffix-field{display:grid;grid-template-columns:minmax(0,1fr)38px;align-items:center;overflow:hidden;border:1px solid var(--admin-border);border-radius:8px;background:var(--admin-surface)}
.suffix-field input{width:100%;min-width:0;height:36px;border:0;border-radius:0}
.suffix-field span{display:grid;height:100%;place-items:center;border-left:1px solid var(--admin-border);background:var(--admin-surface-muted);color:var(--admin-muted);font-weight: 400}
label.invalid input,label.invalid select,label.invalid textarea{border-color:var(--admin-danger);background:var(--admin-danger-soft)}
.field-error{color:var(--admin-danger);font-size:11px;font-weight: 400;line-height:1.35}

.profile-section-card.vip-packages-main-content {
  background: var(--admin-surface, #ffffff);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 0;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.voucher-table,
.state,
.section-head {
  border: none !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  padding: 0 !important;
}

/* Package page follows the shared admin rules: flat surfaces and text-first pricing. */
.vip-admin-page,
.vip-admin-page * {
  font-weight: 400 !important;
}

.vip-admin-page h1,
.vip-admin-page h2,
.vip-admin-page h3,
.vip-admin-page h4,
.vip-admin-page strong,
.vip-admin-page button {
  font-weight: 400 !important;
}

.profile-section-card.vip-packages-main-content,
.package-overview,
.package-card,
.voucher-section,
.confirm-modal,
.voucher-table {
  border-radius: 0;
  box-shadow: none;
}

.profile-section-card.vip-packages-main-content {
  border: 0;
  background: transparent;
  padding: 0;
}

.package-overview {
  border: 1px solid var(--admin-border);
  background: var(--admin-surface);
}

.package-overview__source,
.package-overview__status,
.badge {
  border: 0;
  border-radius: 0;
  background: transparent;
  color: var(--admin-text) !important;
  padding: 0;
}

.package-overview__card {
  border: 0;
  border-radius: 0;
  background: var(--admin-surface-muted);
  box-shadow: none;
}

.package-overview__price,
.package-overview__card li,
.voucher-table th,
.voucher-table td {
  border: 0;
}

.package-overview__cycles div,
.suffix-field {
  border-radius: 0;
}

.package-card,
.voucher-section {
  border: 1px solid var(--admin-border);
}

@media (max-width: 620px) {
  .package-overview__grid,
  .package-grid {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 1100px) {
  .package-overview__grid,
  .package-grid { grid-template-columns: 1fr; }
  .package-overview__heading,
  .package-editor__heading { align-items: flex-start; flex-direction: column; }
  .package-editor__heading p { max-width: none; text-align: left; }
}
@media (max-width: 620px) {
  .package-overview { padding: 16px; }
  .package-overview__card ul,
  .grid,
  .voucher-grid { grid-template-columns: 1fr; }
  .package-overview__source { white-space: normal; }
}
</style>

<style scoped>
.vip-admin-page,
.vip-admin-page * {
  font-weight: 400;
}

.vip-admin-page {
  display: grid;
  gap: 26px;
  min-width: 0;
  color: var(--admin-text);
}

.vip-feedback {
  padding: 12px 14px;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  line-height: 1.45;
}

.vip-feedback-error {
  border-color: var(--admin-danger);
  background: var(--admin-danger-soft);
  color: var(--admin-danger-text);
}

.vip-feedback-success {
  border-color: var(--admin-success);
  background: var(--admin-success-soft);
  color: var(--admin-success-text);
}

.vip-page-header,
.vip-section-header,
.vip-page-actions,
.vip-plan-header,
.vip-plan-footer,
.vip-modal-actions {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.vip-page-header {
  align-items: flex-end;
  padding: 4px 0;
}

.vip-page-heading {
  display: grid;
  gap: 6px;
}

.vip-eyebrow,
.vip-plan-type,
.vip-form-kicker {
  color: var(--admin-success-text);
  font-size: 11px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.vip-page-heading h1,
.vip-section-header h2,
.vip-confirm-modal h3 {
  margin: 0;
  color: var(--admin-text);
  font-weight: 400;
}

.vip-page-heading h1 {
  font-size: 30px;
  line-height: 1.15;
}

.vip-page-heading p,
.vip-section-header p,
.vip-plan-heading p,
.vip-vip-form-note,
.vip-plan-footer > span,
.vip-confirm-modal p {
  margin: 0;
  color: var(--admin-muted);
  line-height: 1.5;
}

.vip-page-heading p {
  max-width: 650px;
  font-size: 13px;
}

.vip-page-actions {
  align-items: center;
  flex: 0 0 auto;
}

.vip-data-note,
.vip-form-note,
.vip-list-count {
  color: var(--admin-muted);
  font-size: 12px;
}

.vip-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 40px;
  border: 1px solid transparent;
  border-radius: 8px;
  padding: 0 14px;
  font: inherit;
  cursor: pointer;
}

.vip-button-primary {
  border-color: var(--admin-primary);
  background: var(--admin-primary);
  color: var(--admin-primary-text);
}

.vip-button-secondary {
  border-color: var(--admin-border);
  background: var(--admin-surface);
  color: var(--admin-text);
}

.vip-button:hover:not(:disabled),
.vip-button:focus-visible {
  border-color: var(--admin-primary-dark);
  outline: none;
}

.vip-button:disabled {
  cursor: not-allowed;
  opacity: 0.56;
}

.vip-summary {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  overflow: hidden;
  border: 1px solid var(--admin-border);
  background: var(--admin-surface-muted);
}

.vip-summary-item {
  display: grid;
  gap: 5px;
  min-height: 104px;
  padding: 17px 18px;
  border-left: 1px solid var(--admin-border);
}

.vip-summary-item:first-child {
  border-left: 0;
}

.vip-summary-item-accent {
  background: var(--admin-primary-soft);
}

.vip-summary-label,
.vip-summary-note {
  color: var(--admin-muted);
  font-size: 12px;
}

.vip-summary-value {
  color: var(--admin-text);
  font-size: 24px;
  line-height: 1.15;
}

.vip-summary-note {
  color: var(--admin-text);
  font-size: 11px;
}

.vip-plans-section {
  display: grid;
  gap: 16px;
  min-width: 0;
}

.vip-section-header {
  align-items: flex-end;
}

.vip-section-header > div {
  display: grid;
  gap: 5px;
}

.vip-section-header h2 {
  font-size: 22px;
  line-height: 1.2;
}

.vip-section-header p {
  max-width: 540px;
  font-size: 12px;
  text-align: right;
}

.vip-loading,
.vip-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  min-height: 118px;
  border: 1px solid var(--admin-border);
  background: var(--admin-surface-muted);
  color: var(--admin-text);
  font-size: 13px;
}

.vip-plan-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  min-width: 0;
}

.vip-plan {
  display: grid;
  align-content: start;
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--admin-border);
  border-radius: 10px;
  background: var(--admin-surface);
}

.vip-plan-header {
  padding: 18px;
  background: var(--admin-primary-soft);
}

.vip-plan-heading {
  display: flex;
  gap: 12px;
  min-width: 0;
}

.vip-plan-index {
  flex: 0 0 auto;
  color: var(--admin-primary-dark);
  font-size: 12px;
  line-height: 1.4;
}

.vip-plan-heading > div:last-child {
  min-width: 0;
}

.vip-plan-heading h3 {
  margin: 4px 0 0;
  color: var(--admin-text);
  font-size: 20px;
  line-height: 1.2;
}

.vip-plan-heading p {
  margin-top: 6px;
  color: var(--admin-text);
  font-size: 12px;
}

.vip-active-field,
.vip-check-field {
  display: flex;
  align-items: center;
  gap: 7px;
  color: var(--admin-text);
  font-size: 12px;
  cursor: pointer;
}

.vip-active-field {
  flex: 0 0 auto;
  padding-top: 2px;
}

.vip-active-field input,
.vip-check-field input {
  width: 16px;
  height: 16px;
  margin: 0;
  accent-color: var(--admin-primary);
}

.vip-plan-state {
  flex: 0 0 auto;
  color: var(--admin-success-text);
  font-size: 12px;
}

.vip-plan-body {
  display: grid;
  align-content: start;
  gap: 18px;
  padding: 18px;
}

.vip-field-grid {
  display: grid;
  gap: 12px;
  min-width: 0;
}

.vip-field-grid-two {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.vip-field-grid-three {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.vip-field {
  display: grid;
  align-content: start;
  gap: 6px;
  min-width: 0;
  color: var(--admin-text);
  font-size: 12px;
}

.vip-field > span:first-child {
  line-height: 1.35;
}

.vip-field input,
.vip-field select,
.vip-field textarea {
  width: 100%;
  min-width: 0;
  border: 1px solid var(--admin-border);
  border-radius: 7px;
  padding: 0 10px;
  background: var(--admin-surface);
  color: var(--admin-text);
  font: inherit;
}

.vip-field input,
.vip-field select {
  min-height: 40px;
}

.vip-field textarea {
  min-height: 84px;
  padding-top: 10px;
  resize: vertical;
}

.vip-field input:focus,
.vip-field select:focus,
.vip-field textarea:focus,
.vip-input-suffix:focus-within {
  border-color: var(--admin-primary);
  outline: none;
  box-shadow: 0 0 0 3px var(--admin-primary-ring);
}

.vip-field input[readonly] {
  background: var(--admin-surface-muted);
  color: var(--admin-text);
}

.vip-field small {
  color: var(--admin-muted);
  font-size: 11px;
  line-height: 1.35;
}

.vip-subsection {
  display: grid;
  gap: 12px;
  padding: 14px;
  border: 1px solid var(--admin-border-soft);
  background: var(--admin-surface-muted);
}

.vip-subsection-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  color: var(--admin-text);
  font-size: 12px;
}

.vip-subsection-heading small {
  color: var(--admin-muted);
  font-size: 11px;
}

.vip-input-suffix {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 36px;
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--admin-border);
  border-radius: 7px;
  background: var(--admin-surface);
}

.vip-input-suffix input {
  min-width: 0;
  min-height: 38px;
  border: 0;
  border-radius: 0;
}

.vip-input-suffix > span {
  display: grid;
  place-items: center;
  border-left: 1px solid var(--admin-border);
  color: var(--admin-text);
}

.vip-free-note {
  display: grid;
  gap: 5px;
  padding: 14px;
  border: 1px solid var(--admin-border-soft);
  background: var(--admin-surface-muted);
  color: var(--admin-text);
  font-size: 12px;
  line-height: 1.5;
}

.vip-free-note-title {
  color: var(--admin-success-text);
}

.vip-check-field {
  justify-content: flex-start;
}

.vip-field-invalid input,
.vip-field-invalid select,
.vip-field-invalid textarea {
  border-color: var(--admin-danger);
  background: var(--admin-danger-soft);
}

.vip-field-error {
  color: var(--admin-danger-text) !important;
}

.vip-plan-footer {
  align-items: center;
  padding: 14px 18px 18px;
  background: var(--admin-surface);
}

.vip-plan-footer > span,
.vip-form-actions > span {
  max-width: 210px;
  font-size: 11px;
}

@media (max-width: 1120px) {
  .vip-plan-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 760px) {
  .vip-page-header,
  .vip-section-header,
  .vip-page-actions,
  .vip-plan-header,
  .vip-plan-footer,
  .vip-modal-actions {
    align-items: flex-start;
    flex-direction: column;
  }

  .vip-page-actions {
    width: 100%;
  }

  .vip-button {
    width: 100%;
  }

  .vip-section-header p {
    max-width: none;
    text-align: left;
  }

  .vip-summary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .vip-summary-item:nth-child(3) {
    border-left: 0;
  }

  .vip-field-grid-two,
  .vip-field-grid-three {
    grid-template-columns: 1fr;
  }

  .vip-plan-footer > span {
    max-width: none;
  }
}

@media (max-width: 480px) {
  .vip-summary {
    grid-template-columns: 1fr;
  }

  .vip-summary-item,
  .vip-summary-item:nth-child(3) {
    border-left: 0;
  }
}
</style>
