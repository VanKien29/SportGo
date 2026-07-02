<template>
  <section class="vip-admin-page">
    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <div v-if="loading" class="state">Đang tải gói VIP...</div>
    <div v-else class="package-grid">
      <form v-for="pkg in packages" :key="pkg.id" class="package-card" novalidate @submit.prevent="save(pkg)">
        <header>
          <div>
            <span>{{ pkg.type === 'free' ? 'Mặc định' : 'Trả phí' }}</span>
            <h3>{{ pkg.label || pkg.name }}</h3>
          </div>
          <label v-if="pkg.type !== 'free'" class="toggle">
            <input v-model="pkg.is_active" type="checkbox" :disabled="pkg.type === 'free'" />
            Hoạt động
          </label>
        </header>

        <div class="grid">
          <label>Tên gói<input v-model.trim="pkg.name" /></label>
          <label>Bài giao lưu/tháng<input v-model.trim="pkg.match_post_limit_per_month" type="text" inputmode="numeric" /></label>

          <template v-if="pkg.type !== 'free'">
            <label>Giá 1 tháng<input :value="monthlyPriceText(pkg)" type="text" inputmode="numeric" @focus="beginMonthlyPriceEdit(pkg, $event)" @input="updateMonthlyPrice(pkg, $event)" @blur="endMonthlyPriceEdit(pkg, $event)" /></label>
            <label>Giá 3 tháng (giảm {{ pricingDiscountLabel(pkg, 'quarterly') }}%)<input :value="money(pkg.quarterly_price)" readonly /></label>
            <label>Giá 1 năm (giảm {{ pricingDiscountLabel(pkg, 'yearly') }}%)<input :value="money(pkg.yearly_price)" readonly /></label>
            <label>Danh hiệu<input v-model.trim="pkg.badge_name" /></label>
            <label>Hoàn tiền<span class="suffix-field"><input v-model.trim="pkg.cashback_percent" type="text" inputmode="decimal" /><span>%</span></span></label>
            <label>Voucher VIP/tháng<input v-model.trim="pkg.voucher_count_per_month" type="text" inputmode="numeric" /></label>
            <label>Giảm voucher<span class="suffix-field"><input v-model.trim="pkg.voucher_discount_percent" type="text" inputmode="decimal" /><span>%</span></span></label>
            <label>Đơn tối thiểu<input v-model.trim="pkg.voucher_min_order_amount" type="text" inputmode="numeric" /></label>
            <label>Trần giảm voucher hàng tháng<input v-model.trim="pkg.voucher_max_discount_amount" type="text" inputmode="numeric" /></label>
          </template>
        </div>

        <label v-if="pkg.type !== 'free'" class="check">
          <input v-model="pkg.priority_complaint" type="checkbox" :disabled="pkg.type === 'free'" />
          Đưa khiếu nại VIP lên đầu hàng chờ
        </label>

        <button class="btn primary" type="submit" :disabled="savingId === pkg.id">
          {{ savingId === pkg.id ? 'Đang lưu...' : 'Lưu gói' }}
        </button>
      </form>
    </div>

    <section class="voucher-section">
      <div class="section-head">
        <div>
          <span>Voucher VIP</span>
          <h3>Voucher thủ công theo gói</h3>
        </div>
      </div>

      <form class="voucher-form" novalidate @submit.prevent="saveVipVoucher">
        <div class="voucher-grid">
          <label :class="{ invalid: voucherErrors.code }">Mã voucher<input v-model.trim="vipVoucherForm.code" maxlength="50" @input="normalizeVoucherCode" /><small v-if="voucherErrors.code" class="field-error">{{ voucherErrors.code }}</small></label>
          <label :class="{ invalid: voucherErrors.name }">Tên voucher<input v-model.trim="vipVoucherForm.name" maxlength="255" @input="validateVipVoucherField('name')" /><small v-if="voucherErrors.name" class="field-error">{{ voucherErrors.name }}</small></label>
          <label :class="{ invalid: voucherErrors.package_type }">Gói áp dụng
            <select v-model="vipVoucherForm.package_type" @change="validateVipVoucherField('package_type')">
              <option v-for="pkg in availableVipPackages" :key="pkg.type" :value="pkg.type">{{ pkg.label }}</option>
            </select>
            <small v-if="voucherErrors.package_type" class="field-error">{{ voucherErrors.package_type }}</small>
          </label>
          <label :class="{ invalid: voucherErrors.discount_type }">Loại giảm
            <select v-model="vipVoucherForm.discount_type" @change="handleVipVoucherDiscountTypeChange">
              <option value="percent">Phần trăm</option>
              <option value="fixed">Số tiền</option>
            </select>
            <small v-if="voucherErrors.discount_type" class="field-error">{{ voucherErrors.discount_type }}</small>
          </label>
          <label :class="{ invalid: voucherErrors.discount_value }">{{ voucherDiscountValueLabel }}
            <input
              v-model.trim="vipVoucherForm.discount_value"
              type="text"
              :inputmode="vipVoucherForm.discount_type === 'percent' ? 'decimal' : 'numeric'"
              @input="validateVipVoucherField('discount_value')"
              @change="normalizeVipVoucherMoneyFields"
            />
            <small v-if="voucherErrors.discount_value" class="field-error">{{ voucherErrors.discount_value }}</small>
          </label>
          <label v-if="vipVoucherForm.discount_type === 'percent'" :class="{ invalid: voucherErrors.max_discount_amount }">Giảm tối đa VNĐ<input v-model.trim="vipVoucherForm.max_discount_amount" type="text" inputmode="numeric" @input="validateVipVoucherField('max_discount_amount')" @change="normalizeVipVoucherMoneyFields" /><small v-if="voucherErrors.max_discount_amount" class="field-error">{{ voucherErrors.max_discount_amount }}</small></label>
          <label :class="{ invalid: voucherErrors.min_order_amount }">Đơn tối thiểu<input v-model.trim="vipVoucherForm.min_order_amount" type="text" inputmode="numeric" @input="validateVipVoucherField('min_order_amount')" @change="normalizeVipVoucherMoneyFields" /><small v-if="voucherErrors.min_order_amount" class="field-error">{{ voucherErrors.min_order_amount }}</small></label>
          <label :class="{ invalid: voucherErrors.per_user_limit }">Giới hạn mỗi khách<input v-model.trim="vipVoucherForm.per_user_limit" type="text" inputmode="numeric" @input="validateVipVoucherField('per_user_limit')" /><small v-if="voucherErrors.per_user_limit" class="field-error">{{ voucherErrors.per_user_limit }}</small></label>
          <label :class="{ invalid: voucherErrors.valid_from }">Bắt đầu<input v-model="vipVoucherForm.valid_from" type="datetime-local" @input="validateVipVoucherField('valid_from')" /><small v-if="voucherErrors.valid_from" class="field-error">{{ voucherErrors.valid_from }}</small></label>
          <label :class="{ invalid: voucherErrors.valid_to }">Kết thúc<input v-model="vipVoucherForm.valid_to" type="datetime-local" @input="validateVipVoucherField('valid_to')" /><small v-if="voucherErrors.valid_to" class="field-error">{{ voucherErrors.valid_to }}</small></label>
          <label :class="{ invalid: voucherErrors.status }">Trạng thái
            <select v-model="vipVoucherForm.status">
              <option value="active">Đang áp dụng</option>
              <option value="inactive">Đã tắt</option>
            </select>
            <small v-if="voucherErrors.status" class="field-error">{{ voucherErrors.status }}</small>
          </label>
        </div>

        <label :class="{ invalid: voucherErrors.description }">Mô tả<textarea v-model.trim="vipVoucherForm.description" maxlength="2000" rows="3"></textarea><small v-if="voucherErrors.description" class="field-error">{{ voucherErrors.description }}</small></label>

        <div class="voucher-actions">
          <button class="btn secondary" type="button" @click="resetVipVoucherForm">Làm mới</button>
          <button class="btn primary" type="submit" :disabled="voucherSaving || availableVipPackages.length === 0">
            {{ voucherSaving ? 'Đang tạo...' : 'Tạo voucher' }}
          </button>
        </div>
      </form>

      <div class="voucher-table">
        <div v-if="voucherLoading" class="state">Đang tải voucher VIP...</div>
        <div v-else-if="vipPackageVouchers.length === 0" class="state">Chưa có voucher áp dụng theo gói VIP.</div>
        <table v-else>
          <thead>
            <tr>
              <th>Mã</th>
              <th>Tên</th>
              <th>Gói áp dụng</th>
              <th>Giảm</th>
              <th>Đơn tối thiểu</th>
              <th>Mỗi khách</th>
              <th>Đã dùng</th>
              <th>Hiệu lực</th>
              <th>Trạng thái</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="voucher in vipPackageVouchers" :key="voucher.id">
              <td><strong>{{ voucher.code }}</strong></td>
              <td>{{ voucher.name }}</td>
              <td>{{ vipVoucherPackageLabel(voucher) }}</td>
              <td>{{ discountText(voucher) }}</td>
              <td>{{ money(voucher.min_order_amount) }}</td>
              <td>{{ voucher.per_user_limit || 'Không giới hạn' }}</td>
              <td>{{ voucher.used_quantity }}</td>
              <td>{{ date(voucher.valid_from) }} - {{ date(voucher.valid_to) }}</td>
              <td><span class="badge" :class="voucher.status">{{ voucher.status_label }}</span></td>
              <td class="actions-col">
                <button
                  class="mini-btn danger"
                  type="button"
                  :disabled="voucher.status === 'inactive'"
                  @click="deactivateVipVoucher(voucher)"
                >
                  Tắt
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="pendingDeactivateVoucher" class="modal-backdrop" @click.self="pendingDeactivateVoucher = null">
      <div class="confirm-modal">
        <h3>Tắt voucher VIP?</h3>
        <p>Voucher {{ pendingDeactivateVoucher.code }} sẽ ngừng áp dụng cho các lượt sử dụng mới.</p>
        <div class="voucher-actions">
          <button class="btn secondary" type="button" @click="pendingDeactivateVoucher = null">Hủy</button>
          <button class="btn primary" type="button" :disabled="voucherSaving" @click="confirmDeactivateVipVoucher">
            {{ voucherSaving ? 'Đang xử lý...' : 'Tắt voucher' }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import { adminVoucherService } from '../../services/adminVoucherService.js';
import { vipMembershipService } from '../../services/vipMembershipService.js';

export default {
  name: 'AdminMembershipPackages',
  data() {
    return {
      packages: [],
      vouchers: [],
      loading: false,
      voucherLoading: false,
      voucherSaving: false,
      savingId: '',
      error: '',
      success: '',
      pendingDeactivateVoucher: null,
      voucherErrors: {},
      vipVoucherForm: this.emptyVipVoucherForm(),
    };
  },
  mounted() {
    this.load();
    this.loadVipVouchers();
  },
  computed: {
    availableVipPackages() {
      const packages = this.packages
        .filter((pkg) => pkg.type !== 'free')
        .map((pkg) => ({
          type: pkg.type,
          label: pkg.label || pkg.name,
        }));

      return packages.length
        ? packages
        : [
            { type: 'saving', label: 'Tiết kiệm' },
            { type: 'pro', label: 'Pro' },
          ];
    },
    vipPackageVouchers() {
      return this.vouchers.filter((voucher) => (voucher.scopes || [])
        .some((scope) => scope.scope_type === 'vip_package'));
    },
    voucherDiscountValueLabel() {
      return this.vipVoucherForm.discount_type === 'percent'
        ? 'Phần trăm giảm (%)'
        : 'Số tiền giảm (VND)';
    },
  },
  methods: {
    emptyVipVoucherForm() {
      const validFrom = new Date();
      const validTo = new Date();
      validTo.setMonth(validTo.getMonth() + 1);

      return {
        code: '',
        name: '',
        description: '',
        package_type: 'saving',
        discount_type: 'percent',
        discount_value: 10,
        max_discount_amount: null,
        min_order_amount: 0,
        per_user_limit: 1,
        valid_from: this.toDatetimeLocal(validFrom),
        valid_to: this.toDatetimeLocal(validTo),
        status: 'active',
      };
    },
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const response = await vipMembershipService.adminPackages();
        this.packages = (response.data || [])
          .map((pkg) => this.decoratePackage(pkg))
          .sort((a, b) => this.packageSortOrder(a) - this.packageSortOrder(b));
        if (!this.availableVipPackages.some((pkg) => pkg.type === this.vipVoucherForm.package_type)) {
          this.vipVoucherForm.package_type = this.availableVipPackages[0]?.type || 'saving';
        }
      } catch (error) {
        this.error = error.message || 'Không thể tải gói VIP.';
      } finally {
        this.loading = false;
      }
    },
    async loadVipVouchers() {
      this.voucherLoading = true;
      try {
        const response = await adminVoucherService.list({ per_page: 50 });
        this.vouchers = response.data || [];
      } catch (error) {
        this.error = error.message || 'Không thể tải voucher VIP.';
      } finally {
        this.voucherLoading = false;
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
    vipVoucherPayload() {
      const discountValue = this.vipVoucherForm.discount_type === 'percent'
        ? this.decimalInputValue(this.vipVoucherForm.discount_value)
        : this.vndIntegerInputValue(this.vipVoucherForm.discount_value);
      const maxDiscount = this.vipVoucherForm.max_discount_amount === null || this.vipVoucherForm.max_discount_amount === ''
        ? null
        : this.vndIntegerInputValue(this.vipVoucherForm.max_discount_amount);
      const minOrder = this.vndIntegerInputValue(this.vipVoucherForm.min_order_amount || 0);
      const perUserLimit = this.integerInputValue(this.vipVoucherForm.per_user_limit);

      return {
        code: String(this.vipVoucherForm.code || '').trim().toUpperCase(),
        name: String(this.vipVoucherForm.name || '').trim(),
        description: this.vipVoucherForm.description || null,
        discount_type: this.vipVoucherForm.discount_type,
        discount_value: discountValue,
        max_discount_amount: this.vipVoucherForm.discount_type === 'percent'
          ? maxDiscount
          : null,
        min_order_amount: minOrder,
        total_quantity: null,
        per_user_limit: perUserLimit === -1
          ? null
          : perUserLimit,
        valid_from: this.vipVoucherForm.valid_from,
        valid_to: this.vipVoucherForm.valid_to,
        status: this.vipVoucherForm.status,
        scopes: [{
          scope_type: 'vip_package',
          scope_id: this.vipVoucherForm.package_type,
        }],
      };
    },
    validateVipVoucher() {
      const errors = this.vipVoucherErrors();
      this.voucherErrors = errors;

      if (Object.keys(errors).length > 0) {
        this.error = 'Vui lòng kiểm tra lại thông tin voucher được đánh dấu đỏ.';
        return false;
      }

      this.error = '';
      return true;
    },
    vipVoucherErrors() {
      const errors = {};
      const code = String(this.vipVoucherForm.code || '').trim().toUpperCase();
      const name = String(this.vipVoucherForm.name || '').trim();
      const description = String(this.vipVoucherForm.description || '').trim();
      const discountValue = this.vipVoucherForm.discount_type === 'percent'
        ? this.decimalInputValue(this.vipVoucherForm.discount_value)
        : this.vndIntegerInputValue(this.vipVoucherForm.discount_value);
      const maxDiscount = this.vipVoucherForm.max_discount_amount === null || this.vipVoucherForm.max_discount_amount === ''
        ? null
        : this.vndIntegerInputValue(this.vipVoucherForm.max_discount_amount);
      const minOrder = this.vndIntegerInputValue(this.vipVoucherForm.min_order_amount || 0);
      const perUserLimit = this.integerInputValue(this.vipVoucherForm.per_user_limit);
      const validFrom = new Date(this.vipVoucherForm.valid_from).getTime();
      const validTo = new Date(this.vipVoucherForm.valid_to).getTime();

      if (!code) {
        errors.code = 'Vui lòng nhập mã voucher.';
      } else if (code.length < 3 || code.length > 50) {
        errors.code = 'Mã voucher phải có từ 3 đến 50 ký tự.';
      } else if (!/^[A-Z0-9_-]+$/.test(code)) {
        errors.code = 'Mã voucher chỉ gồm chữ không dấu, số, dấu gạch ngang hoặc gạch dưới.';
      }

      if (!name) {
        errors.name = 'Vui lòng nhập tên voucher.';
      } else if (name.length < 3 || name.length > 255) {
        errors.name = 'Tên voucher phải có từ 3 đến 255 ký tự.';
      }

      if (!this.availableVipPackages.some((pkg) => pkg.type === this.vipVoucherForm.package_type)) {
        errors.package_type = 'Vui lòng chọn gói VIP hợp lệ.';
      }

      if (!['percent', 'fixed'].includes(this.vipVoucherForm.discount_type)) {
        errors.discount_type = 'Loại giảm giá không hợp lệ.';
      }

      if (!Number.isFinite(discountValue) || discountValue <= 0) {
        errors.discount_value = this.vipVoucherForm.discount_type === 'percent'
          ? 'Phần trăm giảm phải lớn hơn 0.'
          : 'Số tiền giảm phải lớn hơn 0.';
      } else if (this.vipVoucherForm.discount_type === 'percent') {
        if (discountValue > 100) {
          errors.discount_value = 'Phần trăm giảm không được lớn hơn 100%.';
        } else if (!this.hasAtMostTwoDecimals(this.vipVoucherForm.discount_value)) {
          errors.discount_value = 'Phần trăm giảm chỉ được có tối đa 2 chữ số thập phân.';
        }
      } else if (!Number.isInteger(discountValue) || discountValue > 9999999999) {
        errors.discount_value = 'Số tiền giảm phải là số nguyên VND hợp lệ.';
      }

      if (this.vipVoucherForm.discount_type === 'percent' && maxDiscount !== null && (!Number.isInteger(maxDiscount) || maxDiscount < 0 || maxDiscount > 9999999999)) {
        errors.max_discount_amount = 'Trần giảm phải là số nguyên VND không âm.';
      }

      if (!Number.isInteger(minOrder) || minOrder < 0 || minOrder > 9999999999) {
        errors.min_order_amount = 'Đơn tối thiểu phải là số nguyên VND không âm.';
      }

      if (!Number.isInteger(perUserLimit) || perUserLimit === 0 || perUserLimit < -1) {
        errors.per_user_limit = 'Chỉ nhập -1 hoặc số nguyên từ 1 trở lên; -1 là không giới hạn.';
      }

      if (!Number.isFinite(validFrom)) {
        errors.valid_from = 'Vui lòng chọn thời gian bắt đầu.';
      }

      if (!Number.isFinite(validTo)) {
        errors.valid_to = 'Vui lòng chọn thời gian kết thúc.';
      } else if (Number.isFinite(validFrom) && validTo <= validFrom) {
        errors.valid_to = 'Thời gian kết thúc phải sau thời gian bắt đầu.';
      } else if (validTo <= Date.now()) {
        errors.valid_to = 'Thời gian kết thúc phải sau thời điểm hiện tại.';
      }

      if (!['active', 'inactive'].includes(this.vipVoucherForm.status)) {
        errors.status = 'Trạng thái voucher không hợp lệ.';
      }

      if (description.length > 2000) {
        errors.description = 'Mô tả không được vượt quá 2.000 ký tự.';
      }

      return errors;
    },
    validateVipVoucherField(field) {
      if (field === 'code') {
        this.vipVoucherForm.code = String(this.vipVoucherForm.code || '').toUpperCase();
      }

      const errors = this.vipVoucherErrors();
      const nextErrors = { ...this.voucherErrors };
      if (errors[field]) nextErrors[field] = errors[field];
      else delete nextErrors[field];
      this.voucherErrors = nextErrors;
    },
    async saveVipVoucher() {
      if (!this.validateVipVoucher()) return;
      this.normalizeVipVoucherMoneyFields();

      this.voucherSaving = true;
      this.error = '';
      try {
        const response = await adminVoucherService.create(this.vipVoucherPayload());
        this.success = response.message || 'Đã tạo voucher VIP.';
        this.resetVipVoucherForm();
        await this.loadVipVouchers();
      } catch (error) {
        const apiErrors = error.data?.errors || {};
        this.voucherErrors = Object.fromEntries(Object.entries(apiErrors).map(([field, messages]) => {
          const rootField = field.split('.')[0];
          const formField = rootField === 'scopes' ? 'package_type' : rootField;
          return [formField, messages?.[0] || String(messages)];
        }));
        this.error = Object.keys(this.voucherErrors).length
          ? 'Vui lòng kiểm tra lại thông tin voucher được đánh dấu đỏ.'
          : error.message || 'Không thể tạo voucher VIP.';
      } finally {
        this.voucherSaving = false;
      }
    },
    resetVipVoucherForm() {
      this.vipVoucherForm = this.emptyVipVoucherForm();
      this.vipVoucherForm.package_type = this.availableVipPackages[0]?.type || 'saving';
      this.voucherErrors = {};
      this.error = '';
    },
    normalizeVoucherCode(event) {
      this.vipVoucherForm.code = String(event.target.value || '').toUpperCase();
      this.validateVipVoucherField('code');
    },
    handleVipVoucherDiscountTypeChange() {
      if (this.vipVoucherForm.discount_type === 'fixed') {
        this.vipVoucherForm.max_discount_amount = null;
      }
      this.validateVipVoucherField('discount_type');
      this.validateVipVoucherField('discount_value');
      this.validateVipVoucherField('max_discount_amount');
    },
    normalizeVipVoucherMoneyFields() {
      if (this.vipVoucherForm.discount_type === 'percent') {
        const percent = this.decimalInputValue(this.vipVoucherForm.discount_value);
        if (Number.isFinite(percent)) {
          this.vipVoucherForm.discount_value = Math.min(Math.max(Number(percent.toFixed(2)), 0.01), 100);
        }

        if (this.vipVoucherForm.max_discount_amount === null || this.vipVoucherForm.max_discount_amount === '') {
          this.vipVoucherForm.max_discount_amount = null;
        } else {
          const maxDiscount = this.vndIntegerInputValue(this.vipVoucherForm.max_discount_amount);
          if (Number.isInteger(maxDiscount)) this.vipVoucherForm.max_discount_amount = maxDiscount;
        }
      } else {
        const discount = this.vndIntegerInputValue(this.vipVoucherForm.discount_value);
        if (Number.isInteger(discount)) this.vipVoucherForm.discount_value = Math.max(discount, 1);
        this.vipVoucherForm.max_discount_amount = null;
      }

      const minOrder = this.vndIntegerInputValue(this.vipVoucherForm.min_order_amount || 0);
      if (Number.isInteger(minOrder)) this.vipVoucherForm.min_order_amount = minOrder;
      const perUserLimit = this.integerInputValue(this.vipVoucherForm.per_user_limit);
      if (Number.isInteger(perUserLimit)) this.vipVoucherForm.per_user_limit = perUserLimit;
    },
    vndIntegerInputValue(value) {
      const normalized = String(value ?? '').trim();
      return /^\d+$/.test(normalized) ? Number(normalized) : NaN;
    },
    hasAtMostTwoDecimals(value) {
      const normalized = this.normalizedNumericText(value);
      return /^\d+(?:\.\d{1,2})?$/.test(normalized);
    },
    async deactivateVipVoucher(voucher) {
      this.pendingDeactivateVoucher = voucher;
    },
    async confirmDeactivateVipVoucher() {
      if (!this.pendingDeactivateVoucher) return;
      const voucher = this.pendingDeactivateVoucher;
      this.voucherSaving = true;
      try {
        const response = await adminVoucherService.deactivate(voucher.id, 'Admin tắt voucher áp dụng theo gói VIP.');
        this.success = response.message || 'Đã tắt voucher VIP.';
        this.pendingDeactivateVoucher = null;
        await this.loadVipVouchers();
      } catch (error) {
        this.error = error.message || 'Không thể tắt voucher VIP.';
      } finally {
        this.voucherSaving = false;
      }
    },
    vipVoucherPackageLabel(voucher) {
      const scope = (voucher.scopes || []).find((item) => item.scope_type === 'vip_package');
      const matched = this.availableVipPackages.find((pkg) => pkg.type === scope?.scope_id);
      return matched?.label || scope?.scope_id || '-';
    },
    discountText(voucher) {
      return voucher.discount_type === 'percent'
        ? `${Number(voucher.discount_value)}%`
        : this.money(voucher.discount_value);
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
.vip-admin-page{display:grid;gap:16px}.alert,.state{padding:12px 14px;border-radius:10px;font-weight:750}.alert.error{background:#fee2e2;color:#b91c1c}.alert.success{background:#dcfce7;color:#166534}.state{background:#fff;border:1px solid #e2e8f0;color:#64748b}.package-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.package-card,.voucher-section{display:grid;gap:14px;padding:16px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.package-card header,.section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.package-card h3,.section-head h3{margin:3px 0 0;color:#0f172a}.package-card header span,.section-head span{color:#059669;font-size:11px;font-weight:900;text-transform:uppercase}.grid,.voucher-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}label{display:grid;gap:5px;color:#475569;font-size:12px;font-weight:800}input,select,textarea{border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit}input,select{height:38px}textarea{padding-top:10px;resize:vertical}input[readonly]{background:#f8fafc;color:#334155;font-weight:850}.package-note{margin:0;border-radius:8px;background:#f8fafc;color:#64748b;font-size:12px;font-weight:800;line-height:1.45;padding:10px 12px}.toggle,.check{display:flex;align-items:center;gap:8px}.toggle input,.check input{width:16px;height:16px}.btn,.mini-btn{border:0;border-radius:8px;font-weight:850;cursor:pointer}.btn{padding:10px 14px}.mini-btn{padding:7px 10px}.primary{background:#16a34a;color:#fff}.secondary{background:#f1f5f9;color:#0f172a}.danger{background:#fee2e2;color:#b91c1c}.primary:disabled,.mini-btn:disabled{opacity:.55;cursor:not-allowed}.voucher-form{display:grid;gap:12px}.voucher-actions{display:flex;justify-content:flex-end;gap:10px}.voucher-table{overflow:auto;border:1px solid #e2e8f0;border-radius:10px}table{width:100%;min-width:1040px;border-collapse:collapse}th,td{padding:11px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:middle}tbody tr:last-child td{border-bottom:0}.badge{border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e2e8f0}.badge.active{background:#dcfce7;color:#166534}.badge.inactive,.badge.expired{background:#fee2e2;color:#b91c1c}.badge.draft{background:#f1f5f9;color:#475569}.actions-col{text-align:right}.modal-backdrop{position:fixed;inset:0;z-index:900;display:grid;place-items:center;background:rgba(15,23,42,.55);padding:20px}.confirm-modal{display:grid;gap:12px;width:min(440px,calc(100vw - 32px));border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:20px}.confirm-modal h3,.confirm-modal p{margin:0}.confirm-modal p{color:#475569;font-weight:700;line-height:1.5}@media(max-width:1100px){.package-grid{grid-template-columns:1fr}.grid,.voucher-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.grid,.voucher-grid{grid-template-columns:1fr}.voucher-actions{justify-content:stretch}.voucher-actions .btn{flex:1}}
.package-grid{align-items:start}
.package-card{align-content:start;grid-auto-rows:max-content}
.package-card>.btn{align-self:start;min-height:40px}
.suffix-field{display:grid;grid-template-columns:minmax(0,1fr)38px;align-items:center;overflow:hidden;border:1px solid #cbd5e1;border-radius:8px;background:#fff}
.suffix-field input{width:100%;min-width:0;height:36px;border:0;border-radius:0}
.suffix-field span{display:grid;height:100%;place-items:center;border-left:1px solid #e2e8f0;background:#f8fafc;color:#475569;font-weight:900}
label.invalid input,label.invalid select,label.invalid textarea{border-color:#ef4444;background:#fff7f7}
.field-error{color:#dc2626;font-size:11px;font-weight:800;line-height:1.35}
</style>
