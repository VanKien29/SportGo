<template>
  <section class="page">
    <!-- Floating Add Button -->
    <div class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
      <button class="btn-float-add" type="button" @click="openForm()" title="Tạo voucher">
        <AppIcon name="plus" size="20" />
        <span class="btn-float-text">Tạo voucher</span>
      </button>
    </div>

    <div class="notice">Voucher này là voucher của sân. Phần giảm giá do chủ sân chịu.</div>
    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <section class="table-card">
      <div v-if="loading" class="state">Đang tải voucher...</div>
      <div v-else-if="vouchers.length === 0" class="state">Chưa có voucher của sân.</div>
      <table v-else>
        <thead>
          <tr>
            <th>Mã</th>
            <th>Tên</th>
            <th>Loại giảm</th>
            <th>Giá trị</th>
            <th>Đơn tối thiểu</th>
            <th>Số lượng</th>
            <th>Đã dùng</th>
            <th>Hiệu lực</th>
            <th>Trạng thái</th>
            <th>Phạm vi</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="voucher in vouchers" :key="voucher.id">
            <td><strong>{{ voucher.code }}</strong></td>
            <td>{{ voucher.name }}</td>
            <td>{{ voucher.type_label }}</td>
            <td>{{ discountText(voucher) }}</td>
            <td>{{ money(voucher.min_order_amount) }}</td>
            <td>{{ voucher.total_quantity || 'Không giới hạn' }}</td>
            <td>{{ voucher.used_quantity }}</td>
            <td>{{ date(voucher.valid_from) }} - {{ date(voucher.valid_to) }}</td>
            <td><span class="badge" :class="voucher.status">{{ voucher.status_label }}</span></td>
            <td>{{ voucher.scope_label || 'Toàn cụm sân' }}</td>
            <td>
              <TableActionGroup>
                <ActionIconButton icon="pencil" label="Sửa voucher" @click="openForm(voucher)" />
                <ActionIconButton icon="power" label="Tắt voucher" variant="danger" @click="turnOff(voucher)" />
              </TableActionGroup>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <div v-if="showModal" class="modal-backdrop" @mousedown="handleBackdropMousedown" @click="handleBackdropClick($event, closeForm)">
      <form class="modal" @submit.prevent="save" @mousedown.stop>
        <h3>{{ form.id ? 'Sửa voucher sân' : 'Tạo voucher sân' }}</h3>
        <div class="grid">
          <label>Mã voucher<input v-model.trim="form.code" required /></label>
          <label>Tên voucher<input v-model.trim="form.name" required /></label>
          <label>Loại giảm
            <select v-model="form.discount_type" @change="normalizeDiscountFields">
              <option value="percent">Phần trăm</option>
              <option value="fixed">Số tiền</option>
            </select>
          </label>
          <label>Giá trị giảm<input v-model.number="form.discount_value" type="number" min="0.01" :max="form.discount_type === 'percent' ? 100 : null" :step="form.discount_type === 'percent' ? 0.01 : 1000" required @change="normalizeDiscountFields" /></label>
          <label>Giảm tối đa<input v-model.number="form.max_discount_amount" type="number" min="0" step="1000" :disabled="form.discount_type === 'fixed'" @change="normalizeDiscountFields" /></label>
          <label>Đơn tối thiểu<input v-model.number="form.min_order_amount" type="number" min="0" step="1000" @change="normalizeDiscountFields" /></label>
          <label>Tổng số lượng<input v-model.number="form.total_quantity" type="number" min="1" /></label>
          <label>Giới hạn mỗi khách<input v-model.number="form.per_user_limit" type="number" min="1" /></label>
          <label>Bắt đầu<input v-model="form.valid_from" type="datetime-local" required /></label>
          <label>Kết thúc<input v-model="form.valid_to" type="datetime-local" required /></label>
          <label>Trạng thái
            <select v-model="form.status">
              <option value="draft">Bản nháp</option>
              <option value="active">Đang áp dụng</option>
              <option value="inactive">Đã tắt</option>
            </select>
          </label>
        </div>
        <label>Mô tả<textarea v-model.trim="form.description" rows="3"></textarea></label>
        <div class="scope-editor">
          <label>Phạm vi
            <select v-model="form.scopes[0].scope_type" @change="resetScopeId">
              <option value="venue_cluster">Toàn cụm sân</option>
              <option value="court_type">Loại sân</option>
              <option value="booking_type">Hình thức booking</option>
              <option value="membership_tier">Hạng thành viên sân</option>
            </select>
          </label>
          <label v-if="form.scopes[0].scope_type === 'court_type'">Loại sân áp dụng
            <select v-model="form.scopes[0].scope_id" required>
              <option v-for="item in scopeOptions.court_types" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
            </select>
          </label>
          <label v-else-if="form.scopes[0].scope_type === 'membership_tier'">Hạng sân áp dụng
            <select v-model="form.scopes[0].scope_id" required>
              <option v-for="item in scopeOptions.membership_tiers" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </label>
          <label v-else-if="form.scopes[0].scope_type === 'booking_type'">Loại booking
            <select v-model="form.scopes[0].scope_id" required>
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
  </section>
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
        description: '',
        discount_type: 'percent',
        discount_value: 10,
        max_discount_amount: null,
        min_order_amount: 0,
        total_quantity: 100,
        per_user_limit: 1,
        valid_from: '',
        valid_to: '',
        status: 'draft',
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
        scopes: [{
          scope_type: scope.scope_type || 'venue_cluster',
          scope_id: scope.scope_id !== null && scope.scope_id !== undefined ? String(scope.scope_id) : null,
        }],
      };
    },
    closeForm() {
      this.showModal = false;
    },
    async save() {
      this.saving = true;
      try {
        this.normalizeDiscountFields();
        const response = this.form.id
          ? await ownerVoucherService.update(this.form.id, this.form)
          : await ownerVoucherService.create(this.form);
        this.success = response.message;
        this.closeForm();
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể lưu voucher.';
      } finally {
        this.saving = false;
      }
    },
    async turnOff(voucher) {
      if (!confirm(`Tắt voucher ${voucher.code}?`)) return;
      const response = await ownerVoucherService.deactivate(voucher.id, 'Chủ sân tắt voucher.');
      this.success = response.message;
      await this.load();
    },
    discountText(voucher) {
      if (voucher.discount_label) return voucher.discount_label;
      return voucher.discount_type === 'percent' ? `${this.formatPercent(voucher.discount_value)}%` : this.money(voucher.discount_value);
    },
    normalizeDiscountFields() {
      if (this.form.discount_type === 'percent') {
        const percent = Number(this.form.discount_value || 0);
        this.form.discount_value = Math.min(Math.max(Number(percent.toFixed(2)), 0.01), 100);
        this.form.max_discount_amount = this.form.max_discount_amount === null || this.form.max_discount_amount === ''
          ? null
          : this.toVndInteger(this.form.max_discount_amount);
      } else {
        this.form.discount_value = Math.max(this.toVndInteger(this.form.discount_value), 1);
        this.form.max_discount_amount = null;
      }

      this.form.min_order_amount = this.toVndInteger(this.form.min_order_amount);
    },
    toVndInteger(value) {
      return Math.max(Math.round(Number(value || 0)), 0);
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
.page{display:grid;gap:16px}.notice{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:10px;padding:12px;font-weight:800}.table-card,.modal{background:#fff;border:1px solid #e2e8f0;border-radius:12px}.table-card{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1120px}th,td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left}.state{padding:24px;color:#64748b}.btn,.mini-btn{border:0;border-radius:8px;font-weight:800;cursor:pointer}.btn{padding:10px 14px}.mini-btn{padding:7px 10px;margin-right:6px;background:#f1f5f9}.primary{background:#16a34a;color:#fff}.secondary{background:#f1f5f9;color:#0f172a}.danger{background:#fee2e2;color:#b91c1c}.badge{border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e2e8f0}.badge.active{background:#dcfce7;color:#166534}.badge.inactive,.badge.expired{background:#fee2e2;color:#b91c1c}.badge.draft{background:#f1f5f9;color:#475569}.alert{padding:12px;border-radius:10px;font-weight:700}.error{background:#fee2e2;color:#b91c1c}.success{background:#dcfce7;color:#166534}.modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.56);display:grid;place-items:center;z-index:500;padding:20px}.modal{width:min(760px,calc(100vw - 32px));padding:22px;display:grid;gap:16px}.grid,.scope-editor{display:grid;grid-template-columns:1fr 1fr;gap:12px}label{display:grid;gap:6px;font-weight:800}.scope-hint{grid-column:1/-1;margin:0;color:#64748b;font-size:13px;font-weight:700}input,select,textarea{border:1px solid #dbe3ef;border-radius:8px;padding:10px;font:inherit}footer{display:flex;justify-content:flex-end;gap:10px}@media(max-width:720px){.grid,.scope-editor{grid-template-columns:1fr}}
</style>
