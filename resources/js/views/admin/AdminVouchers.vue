<template>
  <section class="page">
    <header class="page-head">
      <div>
        <p class="eyebrow">Voucher hệ thống</p>
        <h2>Quản lý voucher hệ thống</h2>
        <p>Voucher do nền tảng phát hành. Nền tảng chịu phần giảm giá, không trộn với voucher của sân.</p>
      </div>
      <button class="btn primary" type="button" @click="openForm()">
        <AppIcon name="plus" size="16" />
        Tạo voucher
      </button>
    </header>

    <section class="stat-grid" aria-label="Tổng quan voucher hệ thống">
      <article class="stat-card">
        <strong>{{ summary.total || 0 }}</strong>
        <span>Tổng voucher</span>
      </article>
      <article class="stat-card success">
        <strong>{{ summary.active || 0 }}</strong>
        <span>Đang hoạt động</span>
      </article>
      <article class="stat-card warning">
        <strong>{{ summary.expiring_soon || 0 }}</strong>
        <span>Sắp hết hạn</span>
      </article>
      <article class="stat-card danger">
        <strong>{{ summary.used_up || 0 }}</strong>
        <span>Đã dùng hết</span>
      </article>
      <article class="stat-card muted">
        <strong>{{ summary.inactive || 0 }}</strong>
        <span>Đã tắt</span>
      </article>
    </section>

    <section class="filters" @submit.prevent>
      <label class="search-box">
        <AppIcon name="search" size="17" />
        <input v-model.trim="filters.keyword" placeholder="Tìm mã hoặc tên voucher" @keyup.enter="load(1)" />
      </label>
      <select v-model="filters.status" @change="load(1)">
        <option value="">Tất cả trạng thái</option>
        <option value="draft">Bản nháp</option>
        <option value="active">Đang áp dụng</option>
        <option value="inactive">Đã tắt</option>
        <option value="expired">Hết hạn</option>
      </select>
      <select v-model="filters.discount_type" @change="load(1)">
        <option value="">Tất cả loại giảm</option>
        <option value="percent">Phần trăm</option>
        <option value="fixed">Số tiền</option>
      </select>
      <button class="btn secondary" type="button" @click="resetFilters">Xóa lọc</button>
    </section>

    <div class="notice">Voucher hệ thống - nền tảng chịu phần giảm giá.</div>
    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <section class="table-card">
      <div v-if="loading" class="state">Đang tải voucher hệ thống...</div>
      <div v-else-if="vouchers.length === 0" class="state">Chưa có voucher hệ thống phù hợp.</div>
      <table v-else>
        <thead>
          <tr>
            <th>Mã voucher</th>
            <th>Tên voucher</th>
            <th>Loại giảm</th>
            <th>Giá trị</th>
            <th>Hiệu lực</th>
            <th>Số lượng sử dụng</th>
            <th>Trạng thái</th>
            <th>Phạm vi</th>
            <th class="actions-col">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="voucher in vouchers" :key="voucher.id">
            <td>
              <strong>{{ voucher.code }}</strong>
            </td>
            <td>
              <strong>{{ voucher.name }}</strong>
              <span>{{ voucher.funding_label }}</span>
            </td>
            <td>{{ voucher.discount_type_label }}</td>
            <td>{{ voucher.discount_label }}</td>
            <td>{{ formatDate(voucher.valid_from) }} - {{ formatDate(voucher.valid_to) }}</td>
            <td>
              <strong>{{ voucher.used_quantity || 0 }} / {{ voucher.total_quantity || 'Không giới hạn' }}</strong>
              <div v-if="voucher.total_quantity" class="usage-bar" aria-label="Tỷ lệ đã dùng">
                <span :style="{ width: `${voucher.usage_percent || 0}%` }"></span>
              </div>
              <small v-if="voucher.total_quantity">{{ voucher.remaining_quantity }} còn lại</small>
            </td>
            <td>
              <span class="badge" :class="voucher.status_tone">{{ voucher.status_label }}</span>
            </td>
            <td>{{ voucher.scope_label }}</td>
            <td class="actions">
              <button class="icon-btn" type="button" title="Xem chi tiết" @click="goDetail(voucher)">
                <AppIcon name="eye" size="16" />
              </button>
              <button class="icon-btn" type="button" title="Sửa voucher" @click="openForm(voucher)">
                <AppIcon name="pencil" size="16" />
              </button>
              <button
                v-if="voucher.actions_allowed?.deactivate"
                class="icon-btn danger"
                type="button"
                title="Tắt voucher"
                @click="openDeactivate(voucher)"
              >
                <AppIcon name="power" size="16" />
              </button>
              <button
                v-else-if="voucher.actions_allowed?.activate"
                class="icon-btn success"
                type="button"
                title="Kích hoạt voucher"
                @click="activate(voucher)"
              >
                <AppIcon name="circleCheck" size="16" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <footer v-if="meta.total > meta.per_page" class="pagination">
      <button class="btn secondary" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">Trước</button>
      <span>Trang {{ meta.current_page }} / {{ meta.last_page }} - {{ meta.total }} voucher</span>
      <button class="btn secondary" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Sau</button>
    </footer>

    <div v-if="formModal.show" class="modal-backdrop" @click.self="closeForm">
      <form class="modal wide" @submit.prevent="save">
        <header class="modal-head">
          <div>
            <p class="eyebrow">{{ form.id ? 'Cập nhật' : 'Tạo mới' }}</p>
            <h3>{{ form.id ? 'Sửa voucher hệ thống' : 'Tạo voucher hệ thống' }}</h3>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="closeForm"><AppIcon name="x" size="18" /></button>
        </header>

        <div class="form-grid">
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
              <option value="expired">Hết hạn</option>
            </select>
          </label>
        </div>
        <label>Mô tả<textarea v-model.trim="form.description" rows="3"></textarea></label>

        <p class="helper">Phạm vi mặc định là toàn hệ thống. Nếu cần giới hạn theo cụm sân/loại sân, nhập scope trong bước backend tiếp theo để tránh chọn sai phạm vi.</p>

        <footer class="modal-actions">
          <button class="btn secondary" type="button" @click="closeForm">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">
            <AppIcon name="check" size="16" />
            {{ saving ? 'Đang lưu...' : 'Lưu voucher' }}
          </button>
        </footer>
      </form>
    </div>

    <div v-if="deactivateModal.show" class="modal-backdrop" @click.self="closeDeactivate">
      <form class="modal" @submit.prevent="deactivate">
        <header class="modal-head">
          <div>
            <p class="eyebrow">Xác nhận thao tác</p>
            <h3>Tắt voucher {{ deactivateModal.voucher?.code }}</h3>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="closeDeactivate"><AppIcon name="x" size="18" /></button>
        </header>
        <p>Voucher hệ thống sau khi tắt sẽ không còn được áp dụng cho booking mới.</p>
        <label>Lý do tắt voucher<textarea v-model.trim="deactivateModal.reason" rows="3" required placeholder="Ví dụ: hết chương trình khuyến mãi, cần rà soát đối soát..." /></label>
        <footer class="modal-actions">
          <button class="btn secondary" type="button" @click="closeDeactivate">Hủy</button>
          <button class="btn danger" type="submit" :disabled="saving">Tắt voucher</button>
        </footer>
      </form>
    </div>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminVoucherService } from '../../services/adminVoucherService.js';

export default {
  name: 'AdminVouchers',
  components: { AppIcon },
  data() {
    return {
      filters: { keyword: '', status: '', discount_type: '', per_page: 12 },
      vouchers: [],
      summary: { total: 0, active: 0, expiring_soon: 0, used_up: 0, inactive: 0 },
      meta: { current_page: 1, last_page: 1, per_page: 12, total: 0 },
      loading: false,
      saving: false,
      error: '',
      success: '',
      formModal: { show: false },
      deactivateModal: { show: false, voucher: null, reason: '' },
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
    async load(page = this.meta.current_page || 1) {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminVoucherService.list({ ...this.filters, page });
        this.vouchers = response.data || [];
        this.summary = response.summary || this.summary;
        this.meta = response.meta || this.meta;
      } catch (error) {
        this.error = error.message || 'Không thể tải voucher hệ thống.';
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = { keyword: '', status: '', discount_type: '', per_page: 12 };
      this.load(1);
    },
    goDetail(voucher) {
      this.$router.push({ name: 'admin-voucher-detail', params: { id: voucher.id } });
    },
    openForm(voucher = null) {
      this.form = voucher ? {
        ...this.emptyForm(),
        ...voucher,
        valid_from: this.inputDate(voucher.valid_from),
        valid_to: this.inputDate(voucher.valid_to),
      } : this.emptyForm();
      this.formModal.show = true;
    },
    closeForm() {
      this.formModal.show = false;
    },
    async save() {
      this.saving = true;
      this.error = '';
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
    openDeactivate(voucher) {
      this.deactivateModal = { show: true, voucher, reason: '' };
    },
    closeDeactivate() {
      this.deactivateModal = { show: false, voucher: null, reason: '' };
    },
    async deactivate() {
      if (!this.deactivateModal.voucher) return;
      this.saving = true;
      try {
        const response = await adminVoucherService.deactivate(this.deactivateModal.voucher.id, this.deactivateModal.reason);
        this.success = response.message;
        this.closeDeactivate();
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể tắt voucher.';
      } finally {
        this.saving = false;
      }
    },
    async activate(voucher) {
      this.saving = true;
      try {
        const response = await adminVoucherService.activate(voucher.id);
        this.success = response.message;
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể kích hoạt voucher.';
      } finally {
        this.saving = false;
      }
    },
    formatDate(value) {
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
.page { display: grid; gap: 16px; }
.page-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
.page-head h2 { margin: 0 0 6px; font-size: 24px; }
.page-head p { margin: 0; color: #64748b; }
.eyebrow { margin: 0 0 4px; color: #16a34a; font-size: 12px; text-transform: uppercase; font-weight: 800; }
.filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.stat-grid { display: grid; grid-template-columns: repeat(5, minmax(140px, 1fr)); gap: 12px; }
.stat-card { min-height: 92px; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; background: linear-gradient(135deg, #fff 0%, #f8fafc 100%); display: grid; align-content: center; gap: 4px; }
.stat-card strong { font-size: 28px; line-height: 1; color: #0f172a; }
.stat-card span { color: #64748b; font-weight: 800; }
.stat-card.success { border-color: #bbf7d0; background: #f0fdf4; }
.stat-card.warning { border-color: #fde68a; background: #fffbeb; }
.stat-card.danger { border-color: #fecaca; background: #fef2f2; }
.stat-card.muted { border-color: #dbe3ef; background: #f8fafc; }
.search-box { display: flex; align-items: center; gap: 8px; min-width: 280px; border: 1px solid #dbe3ef; border-radius: 10px; padding: 0 10px; background: #fff; }
.search-box input { border: 0; outline: 0; padding: 10px 0; width: 100%; }
.filters select, input, textarea { border: 1px solid #dbe3ef; border-radius: 10px; padding: 10px; font: inherit; }
.notice { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; border-radius: 10px; padding: 12px; font-weight: 700; }
.alert { padding: 12px; border-radius: 10px; font-weight: 700; }
.alert.error { background: #fee2e2; color: #b91c1c; }
.alert.success { background: #dcfce7; color: #166534; }
.table-card, .modal { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
.table-card { overflow: auto; }
table { width: 100%; border-collapse: collapse; min-width: 1040px; }
th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
td span { display: block; color: #64748b; font-size: 12px; margin-top: 3px; }
td small { display: block; margin-top: 4px; color: #64748b; font-weight: 700; }
.usage-bar { width: 140px; height: 7px; border-radius: 999px; background: #e2e8f0; overflow: hidden; margin-top: 7px; }
.usage-bar span { display: block; height: 100%; border-radius: inherit; background: #16a34a; margin: 0; }
.actions-col { width: 136px; }
.actions { display: flex; gap: 6px; }
.state { padding: 24px; color: #64748b; }
.btn, .icon-btn { border: 0; border-radius: 10px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
.btn { padding: 10px 14px; }
.icon-btn { width: 34px; height: 34px; background: #f1f5f9; color: #0f172a; }
.primary { background: #16a34a; color: #fff; }
.secondary { background: #f1f5f9; color: #0f172a; }
.danger { background: #fee2e2; color: #b91c1c; }
.icon-btn.success { background: #dcfce7; color: #166534; }
.badge { display: inline-flex; border-radius: 999px; padding: 5px 9px; font-size: 12px; font-weight: 800; background: #e2e8f0; }
.badge.success { background: #dcfce7; color: #166534; }
.badge.danger { background: #fee2e2; color: #b91c1c; }
.badge.warning { background: #fef3c7; color: #92400e; }
.badge.neutral { background: #f1f5f9; color: #475569; }
.pagination { display: flex; justify-content: flex-end; align-items: center; gap: 12px; color: #64748b; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,.56); display: grid; place-items: center; z-index: 500; padding: 20px; }
.modal { width: min(560px, calc(100vw - 32px)); padding: 22px; display: grid; gap: 16px; }
.modal.wide { width: min(820px, calc(100vw - 32px)); }
.modal-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
.modal h3 { margin: 0; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
label { display: grid; gap: 6px; font-weight: 800; }
.helper { margin: 0; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; }
button:disabled { opacity: .55; cursor: not-allowed; }
@media (max-width: 760px) {
  .page-head, .filters { flex-direction: column; align-items: stretch; }
  .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .search-box { min-width: 0; }
  .form-grid { grid-template-columns: 1fr; }
}
</style>
