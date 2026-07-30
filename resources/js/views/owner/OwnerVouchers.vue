<template>
  <div class="cluster-profile-surface standalone">
    <!-- Floating Add Button -->
    <div class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
      <button class="btn-float-add" type="button" @click="openForm()" title="Tạo voucher">
        <AppIcon name="plus" size="20" />
        <span class="btn-float-text">Tạo voucher</span>
      </button>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <div class="profile-section-card vouchers-main-content">
      <div class="services-table-section">
        <!-- Loading State -->
        <div v-if="loading" class="table-state-card">
          <div class="spinner-sm"></div>
          <span>Đang tải danh sách voucher...</span>
        </div>

        <!-- Empty State -->
        <div v-else-if="vouchers.length === 0" class="table-state-card">
          <span>Chưa có voucher của sân.</span>
        </div>

        <!-- Vouchers Data Table -->
        <div v-else class="services-table-wrapper">
          <table class="services-data-table vouchers-table">
            <thead>
              <tr>
                <th>Mã</th>
                <th>Tên voucher</th>
                <th>Loại giảm</th>
                <th>Giá trị</th>
                <th>Đơn tối thiểu</th>
                <th>Còn lại</th>
                <th>Lượt dùng</th>
                <th>Hiệu lực</th>
                <th>Trạng thái</th>
                <th>Phạm vi</th>
                <th class="action-col">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="voucher in vouchers" :key="voucher.id">
                <td><strong class="code">{{ voucher.code }}</strong></td>
                <td>
                  <strong>{{ voucher.name }}</strong>
                </td>
                <td>{{ voucher.type_label }}</td>
                <td class="money">
                  <strong>{{ discountText(voucher) }}</strong>
                </td>
                <td class="money">{{ money(voucher.min_order_amount) }}</td>
                <td>{{ remainingText(voucher) }}</td>
                <td>{{ usageText(voucher) }}</td>
                <td>
                  <small class="cell-sub">{{ date(voucher.valid_from) }} - {{ date(voucher.valid_to) }}</small>
                </td>
                <td>
                  <span class="status-pill" :class="voucher.status">
                    {{ voucher.status_label }}
                  </span>
                </td>
                <td>{{ voucher.scope_label || 'Toàn cụm sân' }}</td>
                <td class="action-col">
                  <TableActionGroup>
                    <ActionIconButton icon="pencil" label="Sửa voucher" @click="openForm(voucher)" />
                    <ActionIconButton icon="power" label="Tắt voucher" variant="danger" @click="turnOff(voucher)" />
                  </TableActionGroup>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <div v-if="showModal" class="modal-backdrop" @mousedown="handleBackdropMousedown" @click="handleBackdropClick($event, closeForm)">
      <form class="modal" novalidate @submit.prevent="save" @mousedown.stop>
        <h3>{{ form.id ? 'Sửa voucher sân' : 'Tạo voucher sân' }}</h3>
        <div class="grid">
          <label>Mã voucher<input v-model.trim="form.code" @input="clearFieldError('code')" /><small v-if="validationErrors.code" class="field-error">{{ validationErrors.code }}</small></label>
          <label>Tên voucher<input v-model.trim="form.name" @input="clearFieldError('name')" /><small v-if="validationErrors.name" class="field-error">{{ validationErrors.name }}</small></label>
          <label>Loại giảm
            <select v-model="form.discount_type" @change="normalizeDiscountFields">
              <option value="fixed">Số tiền cố định (VNĐ)</option>
              <option value="percent">Phần trăm (%)</option>
            </select>
          </label>
          <label>{{ discountValueLabel }}
            <div class="suffix-field">
              <input v-model="form.discount_value" type="text" inputmode="decimal" @input="clearFieldError('discount_value')" />
              <span>{{ discountValueUnit }}</span>
            </div>
            <small v-if="validationErrors.discount_value" class="field-error">{{ validationErrors.discount_value }}</small>
          </label>
          <label>Số tiền giảm tối đa
            <div class="suffix-field">
              <input v-model="form.max_discount_amount" type="text" inputmode="numeric" :disabled="form.discount_type === 'fixed'" @input="clearFieldError('max_discount_amount')" />
              <span>VNĐ</span>
            </div>
            <small v-if="validationErrors.max_discount_amount" class="field-error">{{ validationErrors.max_discount_amount }}</small>
          </label>
          <label>Đơn tối thiểu
            <div class="suffix-field">
              <input v-model="form.min_order_amount" type="text" inputmode="numeric" @input="clearFieldError('min_order_amount')" />
              <span>VNĐ</span>
            </div>
            <small v-if="validationErrors.min_order_amount" class="field-error">{{ validationErrors.min_order_amount }}</small>
          </label>
          <label>Tổng số lượng phát hành<input v-model.number="form.total_quantity" type="number" min="1" @input="clearFieldError('total_quantity')" /><small v-if="validationErrors.total_quantity" class="field-error">{{ validationErrors.total_quantity }}</small></label>
          <label>Giới hạn dùng / 1 khách<input v-model.number="form.per_user_limit" type="number" min="1" @input="clearFieldError('per_user_limit')" /><small v-if="validationErrors.per_user_limit" class="field-error">{{ validationErrors.per_user_limit }}</small></label>
          <label>Bắt đầu<input v-model="form.valid_from" type="datetime-local" @input="clearFieldError('valid_from')" /><small v-if="validationErrors.valid_from" class="field-error">{{ validationErrors.valid_from }}</small></label>
          <label>Kết thúc<input v-model="form.valid_to" type="datetime-local" @input="clearFieldError('valid_to')" /><small v-if="validationErrors.valid_to" class="field-error">{{ validationErrors.valid_to }}</small></label>
        </div>

        <div class="scope-editor">
          <label>Phạm vi áp dụng
            <select v-model="form.scopes[0].scope_type" @change="resetScopeId">
              <option value="venue_cluster">Toàn cụm sân</option>
              <option value="court_type">Theo loại sân</option>
              <option value="membership_tier">Theo hạng thành viên</option>
              <option value="booking_type">Theo loại booking</option>
            </select>
          </label>

          <label v-if="form.scopes[0].scope_type === 'court_type'">Loại sân
            <select v-model="form.scopes[0].scope_id">
              <option v-for="item in scopeOptions.court_types" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </label>
          <label v-else-if="form.scopes[0].scope_type === 'membership_tier'">Hạng thành viên
            <select v-model="form.scopes[0].scope_id">
              <option v-for="item in scopeOptions.membership_tiers" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </label>
          <label v-else-if="form.scopes[0].scope_type === 'booking_type'">Loại booking
            <select v-model="form.scopes[0].scope_id">
              <option v-for="item in scopeOptions.booking_types" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </label>
          <p class="scope-hint">{{ scopeHint }}</p>
        </div>
        <footer>
          <button class="btn secondary" type="button" @click="closeForm">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">{{ saving ? 'Đang lưu...' : 'Lưu' }}</button>
        </footer>
      </form>
    </div>
  </div>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import TableActionGroup from '../../components/TableActionGroup.vue';
import { ownerVoucherService } from '../../services/ownerVoucherService.js';

export default {
  name: 'OwnerVouchers',
  components: { ActionIconButton, AppIcon, TableActionGroup },
  data() {
    return {
      vouchers: [],
      loading: false,
      saving: false,
      showModal: false,
      error: '',
      success: '',
      form: this.emptyForm(),
      validationErrors: {},
      scopeOptions: this.emptyScopeOptions(),
      showScrollTop: false,
      mousedownWasOnBackdrop: false,
    };
  },
  mounted() {
    window.addEventListener('owner-cluster-changed', this.load);
    window.addEventListener('scroll', this.handleScroll);
    this.load();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.load);
    window.removeEventListener('scroll', this.handleScroll);
  },
  methods: {
    handleBackdropMousedown(event) {
      this.mousedownWasOnBackdrop = event.target === event.currentTarget;
    },
    handleBackdropClick(event, closeFn) {
      if (this.mousedownWasOnBackdrop && event.target === event.currentTarget) {
        closeFn();
      }
      this.mousedownWasOnBackdrop = false;
    },
    emptyForm() {
      return {
        id: null,
        code: '',
        name: '',
        description: null,
        discount_type: 'percent',
        discount_value: 10,
        max_discount_amount: null,
        min_order_amount: 0,
        total_quantity: 100,
        per_user_limit: 1,
        valid_from: '',
        valid_to: '',
        status: 'active',
        scopes: [{ scope_type: 'venue_cluster', scope_id: null }],
      };
    },
    emptyScopeOptions() {
      return {
        court_types: [],
        membership_tiers: [
          { id: 'standard', name: 'Thường' },
          { id: 'silver', name: 'Bạc' },
          { id: 'gold', name: 'Vàng' },
          { id: 'diamond', name: 'Kim cương' },
        ],
        booking_types: [
          { id: 'single', name: 'Đơn lẻ' },
          { id: 'recurring', name: 'Lịch cố định' },
        ],
      };
    },
    async load() {
      this.loading = true;
      try {
        const response = await ownerVoucherService.list();
        this.vouchers = response.data || [];
        this.scopeOptions = { ...this.emptyScopeOptions(), ...(response.meta?.scope_options || {}) };
      } catch (error) {
        this.error = error.message || 'Không thể tải voucher của sân.';
      } finally {
        this.loading = false;
      }
    },
    openForm(voucher = null) {
      this.form = voucher ? this.formFromVoucher(voucher) : this.emptyForm();
      this.validationErrors = {};
      this.error = '';
      this.ensureScopeValue();
      this.showModal = true;
    },
    formFromVoucher(voucher) {
      const scope = (voucher.scopes || [])[0] || { scope_type: 'venue_cluster', scope_id: null };
      return {
        ...this.emptyForm(),
        ...voucher,
        valid_from: this.inputDate(voucher.valid_from),
        valid_to: this.inputDate(voucher.valid_to),
        description: null,
        status: voucher.status === 'draft' ? 'active' : voucher.status,
        scopes: [{
          scope_type: scope.scope_type || 'venue_cluster',
          scope_id: scope.scope_id !== null && scope.scope_id !== undefined ? String(scope.scope_id) : null,
        }],
      };
    },
    closeForm() {
      this.showModal = false;
      this.validationErrors = {};
    },
    async save() {
      if (!this.validateForm()) return;
      this.normalizeDiscountFields();

      this.saving = true;
      try {
        const payload = {
          ...this.form,
          description: null,
        };
        const response = this.form.id
          ? await ownerVoucherService.update(this.form.id, payload)
          : await ownerVoucherService.create(payload);
        this.success = response.message;
        this.closeForm();
        await this.load();
      } catch (error) {
        this.applyApiValidationErrors(error);
        this.error = error.message || 'Không thể lưu voucher.';
      } finally {
        this.saving = false;
      }
    },
    validateForm() {
      const errors = {};
      const discountValue = this.form.discount_type === 'percent'
        ? this.decimalInputValue(this.form.discount_value)
        : this.vndIntegerInputValue(this.form.discount_value);
      const maxDiscountAmount = this.form.max_discount_amount === null || this.form.max_discount_amount === ''
        ? 0
        : this.vndIntegerInputValue(this.form.max_discount_amount);
      const minOrderAmount = this.vndIntegerInputValue(this.form.min_order_amount || 0);
      const totalQuantity = this.integerInputValue(this.form.total_quantity);
      const perUserLimit = this.integerInputValue(this.form.per_user_limit);

      if (!this.form.code) errors.code = 'Vui lòng nhập mã voucher.';
      if (!this.form.name) errors.name = 'Vui lòng nhập tên voucher.';
      if (!discountValue || discountValue <= 0) {
        errors.discount_value = this.form.discount_type === 'percent'
          ? 'Vui lòng nhập phần trăm giảm lớn hơn 0.'
          : 'Vui lòng nhập số tiền giảm lớn hơn 0.';
      } else if (this.form.discount_type === 'percent' && discountValue > 100) {
        errors.discount_value = 'Phần trăm giảm không được vượt quá 100%.';
      }

      if (!errors.discount_value && this.form.discount_type === 'fixed' && !Number.isInteger(discountValue)) {
        errors.discount_value = 'Số tiền giảm phải là số nguyên VND, không nhập phần thập phân.';
      }
      if (!errors.discount_value && this.form.discount_type === 'percent' && !this.hasAtMostTwoDecimals(this.form.discount_value)) {
        errors.discount_value = 'Phần trăm giảm chỉ được tối đa 2 chữ số thập phân.';
      }

      if (this.form.discount_type === 'percent' && (!Number.isInteger(maxDiscountAmount) || maxDiscountAmount < 0)) {
        errors.max_discount_amount = 'Số tiền giảm tối đa không được âm.';
      }

      if (!Number.isInteger(minOrderAmount) || minOrderAmount < 0) errors.min_order_amount = 'Đơn tối thiểu phải là số nguyên VND không âm.';
      if (!Number.isInteger(totalQuantity) || totalQuantity < 1) errors.total_quantity = 'Tổng số lượng phải từ 1 trở lên.';
      if (!Number.isInteger(perUserLimit) || perUserLimit < 1) errors.per_user_limit = 'Giới hạn mỗi khách phải từ 1 trở lên.';
      if (Number.isInteger(totalQuantity) && Number.isInteger(perUserLimit) && perUserLimit > totalQuantity) {
        errors.per_user_limit = 'Giới hạn mỗi khách không được lớn hơn tổng số lượng.';
      }
      if (!this.form.valid_from) errors.valid_from = 'Vui lòng chọn thời gian bắt đầu.';
      if (!this.form.valid_to) errors.valid_to = 'Vui lòng chọn thời gian kết thúc.';
      if (this.form.valid_from && this.form.valid_to && new Date(this.form.valid_to) <= new Date(this.form.valid_from)) {
        errors.valid_to = 'Thời gian kết thúc phải sau thời gian bắt đầu.';
      }

      this.validationErrors = errors;
      return Object.keys(errors).length === 0;
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
      return /^\d+$/.test(normalized) ? Number(normalized) : NaN;
    },
    vndIntegerInputValue(value) {
      return this.integerInputValue(value);
    },
    hasAtMostTwoDecimals(value) {
      const normalized = this.normalizedNumericText(value);
      return /^\d+(?:\.\d{1,2})?$/.test(normalized);
    },
    clearFieldError(field) {
      if (!this.validationErrors[field]) return;
      const { [field]: removed, ...rest } = this.validationErrors;
      this.validationErrors = rest;
    },
    applyApiValidationErrors(error) {
      const apiErrors = error?.data?.errors || {};
      const mapped = {};

      Object.entries(apiErrors).forEach(([field, messages]) => {
        mapped[field] = Array.isArray(messages) ? messages[0] : String(messages);
      });

      this.validationErrors = {
        ...this.validationErrors,
        ...mapped,
      };
    },
    async turnOff(voucher) {
      const response = await ownerVoucherService.deactivate(voucher.id, 'Chủ sân tắt voucher.');
      this.success = response.message;
      await this.load();
    },
    discountText(voucher) {
      if (voucher.discount_label) return voucher.discount_label;
      return voucher.discount_type === 'percent' ? `${this.formatPercent(voucher.discount_value)}%` : this.money(voucher.discount_value);
    },
    remainingText(voucher) {
      if (voucher.total_quantity === null || voucher.total_quantity === undefined) {
        return 'Không giới hạn';
      }

      return Number(voucher.remaining_quantity ?? Math.max(Number(voucher.total_quantity || 0) - Number(voucher.used_quantity || 0), 0));
    },
    usageText(voucher) {
      return `${Number(voucher.used_quantity || 0)} lượt`;
    },
    normalizeDiscountFields() {
      if (this.form.discount_type === 'percent') {
        const percent = this.decimalInputValue(this.form.discount_value);
        if (Number.isFinite(percent)) {
          this.form.discount_value = Math.min(Math.max(Number(percent.toFixed(2)), 0.01), 100);
        }

        if (this.form.max_discount_amount === null || this.form.max_discount_amount === '') {
          this.form.max_discount_amount = null;
        } else {
          const maxDiscountAmount = this.toVndInteger(this.form.max_discount_amount);
          if (Number.isInteger(maxDiscountAmount)) {
            this.form.max_discount_amount = maxDiscountAmount;
          }
        }
      } else {
        const discountValue = this.toVndInteger(this.form.discount_value);
        if (Number.isInteger(discountValue)) {
          this.form.discount_value = Math.max(discountValue, 1);
        }
        this.form.max_discount_amount = null;
      }

      const minOrderAmount = this.toVndInteger(this.form.min_order_amount);
      if (Number.isInteger(minOrderAmount)) {
        this.form.min_order_amount = minOrderAmount;
      }
    },
    toVndInteger(value) {
      const parsed = this.vndIntegerInputValue(value);
      return Number.isInteger(parsed) ? Math.max(parsed, 0) : NaN;
    },
    formatPercent(value) {
      return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(Number(value || 0));
    },
    resetScopeId() {
      this.form.scopes[0].scope_id = null;
      this.ensureScopeValue();
    },
    ensureScopeValue() {
      const scope = this.form.scopes[0];
      if (scope.scope_type === 'venue_cluster') {
        scope.scope_id = null;
        return;
      }

      const optionMap = {
        court_type: 'court_types',
        membership_tier: 'membership_tiers',
        booking_type: 'booking_types',
      };
      const options = this.scopeOptions[optionMap[scope.scope_type]] || [];
      if (!scope.scope_id && options.length) {
        scope.scope_id = String(options[0].id);
      }
    },
    scopeOptionName(type, id) {
      const optionMap = {
        court_type: 'court_types',
        membership_tier: 'membership_tiers',
        booking_type: 'booking_types',
      };
      const item = (this.scopeOptions[optionMap[type]] || []).find((option) => String(option.id) === String(id));
      return item?.name || id || '';
    },
    scopeTypeName(type) {
      return {
        venue_cluster: 'toàn cụm sân',
        court_type: 'loại sân',
        booking_type: 'hình thức booking',
        membership_tier: 'hạng thành viên sân',
      }[type] || 'phạm vi đã chọn';
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
    },
    date(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    inputDate(value) {
      if (!value) return '';
      const date = new Date(value);
      return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    },
    handleScroll() {
      this.showScrollTop = window.scrollY > 250;
    },
  },
  computed: {
    discountValueLabel() {
      return this.form.discount_type === 'percent' ? 'Giảm bao nhiêu %' : 'Giảm bao nhiêu tiền';
    },
    discountValueUnit() {
      return this.form.discount_type === 'percent' ? '%' : 'VNĐ';
    },
    scopeHint() {
      const scope = this.form.scopes[0] || { scope_type: 'venue_cluster', scope_id: null };
      if (scope.scope_type === 'venue_cluster') {
        return 'Voucher áp dụng cho toàn bộ booking trong cụm sân đang chọn.';
      }

      const name = this.scopeOptionName(scope.scope_type, scope.scope_id);
      return `Voucher chỉ áp dụng cho ${this.scopeTypeName(scope.scope_type)}${name ? `: ${name}` : ''}.`;
    },
  },
};
</script>

<style scoped>
.cluster-profile-surface.standalone {
  width: 100%;
  min-width: 0;
  background: transparent;
  display: flex;
  flex-direction: column;
  gap: 16px;
  border-radius: 0;
}

/* Single unified main surface */
.profile-section-card.vouchers-main-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 10px;
  background: var(--admin-surface, #ffffff);
  border: 1px solid var(--admin-border, #e2e8f0);
  border-radius: 0;
  box-shadow: none;
}

.alert {
  border-radius: 8px;
  padding: 14px 16px;
  font-weight: 500;
  font-size: 13px;
}

.alert.error {
  background: var(--admin-danger-soft, rgba(239, 68, 68, 0.08));
  color: var(--admin-danger, #ef4444);
}

.alert.success {
  background: var(--admin-success-soft, rgba(16, 185, 129, 0.08));
  color: var(--admin-primary, #22a653);
}

/* Services Table Section (matching ServicesTable.vue) */
.services-table-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* State Cards */
.table-state-card {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 8px;
  padding: 36px 20px;
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px dashed var(--admin-border, #cfded1);
  border-radius: 8px;
  color: var(--admin-muted, #2f3d34);
  font-size: 13.5px;
  font-weight: 400;
  text-align: center;
}

.spinner-sm {
  width: 18px;
  height: 18px;
  border: 2px solid var(--admin-border, #cfded1);
  border-top-color: var(--admin-primary, #22a653);
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Services Table Wrapper & Data Table */
.services-table-wrapper {
  overflow-x: auto;
  border: none;
  border-radius: 10px;
}

.services-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  text-align: left;
}

.services-data-table th {
  background: var(--admin-bg-soft, #f7fbf5);
  color: var(--admin-text, #101c15);
  font-weight: 400;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 12px 14px;
  border-bottom: none;
}

.services-data-table td {
  padding: 12px 14px;
  border-bottom: none;
  color: var(--admin-text, #101c15);
  font-weight: 400;
  vertical-align: middle;
}

.services-data-table tbody tr {
  transition: background-color 0.12s ease;
}

.services-data-table tbody tr:hover {
  background: var(--admin-hover, #edf7ed);
}

.code {
  color: var(--admin-primary, #22a653);
  font-weight: 500;
}

.money {
  font-weight: 400;
  white-space: nowrap;
}

.cell-sub {
  display: block;
  margin-top: 3px;
  color: var(--admin-muted, #64748b);
  font-size: 12px;
}

/* Status Pills */
.status-pill {
  display: inline-flex;
  border-radius: 999px;
  padding: 3px 9px;
  font-size: 11px;
  font-weight: 400;
  white-space: nowrap;
}

.status-pill.active {
  background: var(--admin-success-soft, rgba(16, 185, 129, 0.08));
  color: var(--admin-primary, #22a653);
}

.status-pill.inactive,
.status-pill.expired {
  background: var(--admin-danger-soft, rgba(239, 68, 68, 0.08));
  color: var(--admin-danger, #ef4444);
}

.status-pill.draft {
  background: var(--admin-surface-muted, #f1f5f9);
  color: var(--admin-muted, #64748b);
}

.action-col {
  width: 1%;
  min-width: 90px;
  text-align: right;
}

/* Floating Add Button */
.floating-add-container {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 400;
}

.btn-float-add {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 44px;
  padding: 0 18px;
  border: none;
  border-radius: 999px;
  background: var(--admin-primary, #22a653);
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 500;
  box-shadow: 0 4px 16px rgba(34, 166, 83, 0.35);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-float-add:hover {
  background: var(--admin-primary-dark, #15733a);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(34, 166, 83, 0.45);
}

/* Modal Styling */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.58);
  display: grid;
  place-items: center;
  z-index: 500;
  padding: 20px;
}

.modal {
  width: min(760px, calc(100vw - 32px));
  padding: 22px;
  display: grid;
  gap: 16px;
  background: #ffffff;
  border: 1px solid var(--admin-border, #e2e8f0);
  border-radius: 12px;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22);
}

.modal h3 {
  margin: 0;
  font-size: 17px;
  font-weight: 500;
  color: var(--admin-text, #101c15);
}

.grid,
.scope-editor {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

label {
  display: grid;
  gap: 6px;
  font-size: 13px;
  font-weight: 400;
  color: var(--admin-text, #101c15);
}

.scope-hint {
  grid-column: 1 / -1;
  margin: 0;
  color: var(--admin-muted, #64748b);
  font-size: 12.5px;
}

input,
select,
textarea {
  border: 1px solid var(--admin-border, #cbd5e1);
  border-radius: 6px;
  padding: 9px 12px;
  font: inherit;
  font-size: 13px;
  background: #ffffff;
  color: var(--admin-text, #101c15);
}

input:focus,
select:focus,
textarea:focus {
  outline: 0;
  border-color: var(--admin-primary, #22a653);
}

.suffix-field {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  border: 1px solid var(--admin-border, #cbd5e1);
  border-radius: 6px;
  background: #ffffff;
  overflow: hidden;
}

.suffix-field input {
  border: 0;
  border-radius: 0;
  min-width: 0;
}

.suffix-field input:disabled {
  background: var(--admin-hover, #f8fafc);
  color: var(--admin-muted, #94a3b8);
}

.suffix-field span {
  padding: 0 12px;
  color: var(--admin-muted, #64748b);
  font-size: 12px;
  white-space: nowrap;
}

footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn {
  height: 36px;
  padding: 0 16px;
  border: 0;
  border-radius: 6px;
  font: inherit;
  font-size: 13px;
  font-weight: 400;
  cursor: pointer;
}

.btn.primary {
  background: var(--admin-primary, #22a653);
  color: #ffffff;
}

.btn.secondary {
  background: var(--admin-hover, #f8fafc);
  border: 1px solid var(--admin-border, #e2e8f0);
  color: var(--admin-text, #101c15);
}

.modal .field-error {
  display: block;
  width: 100%;
  border-left: 3px solid #dc2626;
  border-radius: 6px;
  background: #fef2f2;
  color: #b91c1c !important;
  font-size: 12px;
  font-weight: 400;
  line-height: 1.35;
  padding: 6px 8px;
}

@media (max-width: 720px) {
  .grid,
  .scope-editor {
    grid-template-columns: 1fr;
  }
}
</style>
