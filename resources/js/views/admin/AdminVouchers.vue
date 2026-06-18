<template>
  <section class="page">
    <header class="page-head">
      <div>
        <h2>Voucher hệ thống</h2>
        <p>Voucher do nền tảng phát hành. Nền tảng chịu phần giảm giá.</p>
      </div>
      <button class="btn primary" type="button" title="Tạo voucher hệ thống" @click="openForm()">
        <AppIcon name="plus" size="17" />
        <span>Tạo voucher</span>
      </button>
    </header>

    <section class="filters">
      <input v-model.trim="filters.keyword" placeholder="Tìm mã hoặc tên voucher" @keyup.enter="load" />
      <select v-model="filters.status" @change="load">
        <option value="">Tất cả trạng thái</option>
        <option value="draft">Bản nháp</option>
        <option value="active">Đang áp dụng</option>
        <option value="inactive">Đã tắt</option>
        <option value="expired">Hết hạn</option>
      </select>
      <select v-model="filters.discount_type" @change="load">
        <option value="">Tất cả loại giảm</option>
        <option value="percent">Phần trăm</option>
        <option value="fixed">Số tiền</option>
      </select>
      <ActionIconButton icon="filter" label="Lọc danh sách" @click="load" />
    </section>

    <div class="notice">Voucher hệ thống - nền tảng chịu phần giảm giá.</div>
    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <section class="table-card">
      <div v-if="loading" class="state">Đang tải voucher hệ thống...</div>
      <div v-else-if="vouchers.length === 0" class="state">Chưa có voucher hệ thống.</div>
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
            <th class="actions-col">Thao tác</th>
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
            <td class="actions-col">
              <TableActionGroup>
                <ActionIconButton icon="pencil" label="Sửa voucher" @click="openForm(voucher)" />
                <ActionIconButton icon="power" label="Tắt voucher" variant="danger" @click="turnOff(voucher)" />
              </TableActionGroup>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <div v-if="showModal" class="modal-backdrop" @click.self="closeForm">
      <form class="modal" @submit.prevent="save">
        <h3>{{ form.id ? 'Sửa voucher hệ thống' : 'Tạo voucher hệ thống' }}</h3>
        <div class="grid">
          <label>Mã voucher<input v-model.trim="form.code" required /></label>
          <label>Tên voucher<input v-model.trim="form.name" required /></label>
          <label>Loại giảm
            <select v-model="form.discount_type">
              <option value="percent">Phần trăm</option>
              <option value="fixed">Số tiền</option>
            </select>
          </label>
          <label>Giá trị giảm<input v-model.number="form.discount_value" type="number" min="0.01" step="0.01" required /></label>
          <label>Giảm tối đa<input v-model.number="form.max_discount_amount" type="number" min="0" step="1000" /></label>
          <label>Đơn tối thiểu<input v-model.number="form.min_order_amount" type="number" min="0" step="1000" /></label>
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
import { adminVoucherService } from '../../services/adminVoucherService.js';

export default {
  name: 'AdminVouchers',
  components: { ActionIconButton, AppIcon, TableActionGroup },
  data() {
    return {
      filters: { keyword: '', status: '', discount_type: '' },
      vouchers: [],
      loading: false,
      saving: false,
      showModal: false,
      error: '',
      success: '',
      form: this.emptyForm(),
    };
  },
  mounted() {
    this.load();
  },
  methods: {
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
        scopes: [{ scope_type: 'all', scope_id: null }],
      };
    },
    async load() {
      this.loading = true;
      try {
        const response = await adminVoucherService.list(this.filters);
        this.vouchers = response.data || [];
      } catch (error) {
        this.error = error.message || 'Không thể tải voucher hệ thống.';
      } finally {
        this.loading = false;
      }
    },
    openForm(voucher = null) {
      this.form = voucher ? { ...voucher, valid_from: this.inputDate(voucher.valid_from), valid_to: this.inputDate(voucher.valid_to) } : this.emptyForm();
      this.showModal = true;
    },
    closeForm() {
      this.showModal = false;
    },
    async save() {
      this.saving = true;
      try {
        const response = this.form.id
          ? await adminVoucherService.update(this.form.id, this.form)
          : await adminVoucherService.create(this.form);
        this.success = response.message;
        this.closeForm();
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể lưu voucher hệ thống.';
      } finally {
        this.saving = false;
      }
    },
    async turnOff(voucher) {
      if (!confirm(`Tắt voucher hệ thống ${voucher.code}?`)) return;
      const response = await adminVoucherService.deactivate(voucher.id, 'Admin tắt voucher hệ thống.');
      this.success = response.message;
      await this.load();
    },
    discountText(voucher) {
      return voucher.discount_type === 'percent' ? `${Number(voucher.discount_value)}%` : this.money(voucher.discount_value);
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
  },
};
</script>

<style scoped>
.page{display:grid;gap:16px}.page-head,.filters{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.page-head h2{margin:0 0 6px}.page-head p{margin:0;color:#64748b}.filters{justify-content:flex-start;align-items:center}.filters input,.filters select{border:1px solid #dbe3ef;border-radius:8px;padding:10px;font:inherit}.notice{background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;border-radius:10px;padding:12px;font-weight:800}.table-card,.modal{background:#fff;border:1px solid #e2e8f0;border-radius:12px}.table-card{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1040px}th,td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left}.state{padding:24px;color:#64748b}.btn,.mini-btn{border:0;border-radius:8px;font-weight:800;cursor:pointer}.btn{padding:10px 14px}.mini-btn{padding:7px 10px;margin-right:6px;background:#f1f5f9}.primary{background:#16a34a;color:#fff}.secondary{background:#f1f5f9;color:#0f172a}.danger{background:#fee2e2;color:#b91c1c}.badge{border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e2e8f0}.badge.active{background:#dcfce7;color:#166534}.badge.inactive,.badge.expired{background:#fee2e2;color:#b91c1c}.badge.draft{background:#f1f5f9;color:#475569}.alert{padding:12px;border-radius:10px;font-weight:700}.error{background:#fee2e2;color:#b91c1c}.success{background:#dcfce7;color:#166534}.modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.56);display:grid;place-items:center;z-index:500;padding:20px}.modal{width:min(760px,calc(100vw - 32px));padding:22px;display:grid;gap:16px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}label{display:grid;gap:6px;font-weight:800}input,select,textarea{border:1px solid #dbe3ef;border-radius:8px;padding:10px;font:inherit}footer{display:flex;justify-content:flex-end;gap:10px}@media(max-width:720px){.grid,.filters{grid-template-columns:1fr;flex-direction:column;align-items:stretch}.page-head{flex-direction:column}}
</style>
