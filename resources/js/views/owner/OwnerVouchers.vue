<template>
  <section class="page">
    <header class="page-head hero-card">
      <div>
        <p class="eyebrow">Voucher sân</p>
        <h2>Voucher của cụm sân</h2>
        <p>Voucher riêng của sân. Phần giảm giá do chủ sân chịu.</p>
      </div>
      <button class="btn primary" type="button" @click="openForm()">
        <AppIcon name="plus" size="16" />
        Tạo voucher
      </button>
    </header>

    <section class="stat-grid">
      <article class="stat-card">
        <strong>{{ summary.total || 0 }}</strong>
        <span>Tổng voucher</span>
      </article>
      <article class="stat-card success">
        <strong>{{ summary.active || 0 }}</strong>
        <span>Đang áp dụng</span>
      </article>
      <article class="stat-card warning">
        <strong>{{ summary.expiring_soon || 0 }}</strong>
        <span>Sắp hết hạn</span>
      </article>
      <article class="stat-card danger">
        <strong>{{ (summary.used_up || 0) + (summary.inactive || 0) }}</strong>
        <span>Đã dùng hết/đã tắt</span>
      </article>
    </section>

    <section class="filters">
      <label class="search-box">
        <AppIcon name="search" size="17" />
        <input v-model.trim="filters.keyword" placeholder="Tìm mã hoặc tên voucher" @input="scheduleSearch" />
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

    <div class="notice">
      <AppIcon name="tag" size="16" />
      Voucher sân - chủ sân chịu phần giảm giá.
    </div>
    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <section class="table-card">
      <div v-if="loading" class="state">
        <span class="spinner"></span>
        Đang tải voucher...
      </div>
      <div v-else-if="vouchers.length === 0" class="state">Chưa có voucher của sân.</div>
      <table v-else>
        <thead>
          <tr>
            <th>Voucher</th>
            <th>Giá trị giảm</th>
            <th>Hiệu lực</th>
            <th>Số lượng</th>
            <th>Phạm vi</th>
            <th>Trạng thái</th>
            <th class="actions-col">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="voucher in vouchers" :key="voucher.id">
            <td>
              <strong>{{ voucher.code }}</strong>
              <span>{{ voucher.name }}</span>
            </td>
            <td>
              <strong>{{ voucher.discount_label || discountText(voucher) }}</strong>
              <span>Đơn tối thiểu: {{ money(voucher.min_order_amount) }}</span>
            </td>
            <td>{{ date(voucher.valid_from) }} - {{ date(voucher.valid_to) }}</td>
            <td>
              <div class="usage-line">
                <span>{{ voucher.used_quantity || 0 }} / {{ voucher.total_quantity || '∞' }}</span>
                <div class="progress"><i :style="{ width: `${voucher.usage_percent || 0}%` }"></i></div>
              </div>
            </td>
            <td>{{ voucher.scope_label || 'Toàn cụm sân' }}</td>
            <td><span class="badge" :class="voucher.status_tone || voucher.status">{{ voucher.status_label }}</span></td>
            <td class="actions">
              <button class="icon-btn" type="button" title="Xem chi tiết" @click="openDetail(voucher)">
                <AppIcon name="eye" size="16" />
              </button>
              <button class="icon-btn" type="button" title="Sửa voucher" @click="openForm(voucher)">
                <AppIcon name="pencil" size="16" />
              </button>
              <button class="icon-btn danger" type="button" title="Tắt voucher" @click="openDeactivate(voucher)">
                <AppIcon name="power" size="16" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <footer v-if="meta.total > meta.per_page" class="pagination">
      <button class="btn secondary" type="button" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">Trước</button>
      <span>Trang {{ meta.current_page }} / {{ meta.last_page }} - {{ meta.total }} voucher</span>
      <button class="btn secondary" type="button" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Sau</button>
    </footer>

    <div v-if="showModal" class="modal-backdrop" @click.self="closeForm">
      <form class="modal wide" @submit.prevent="save">
        <header class="modal-head">
          <div>
            <p class="eyebrow">{{ form.id ? 'Cập nhật voucher' : 'Tạo voucher' }}</p>
            <h3>{{ form.id ? 'Sửa voucher sân' : 'Tạo voucher sân' }}</h3>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="closeForm">
            <AppIcon name="x" size="16" />
          </button>
        </header>

        <div class="form-grid">
          <label>Mã voucher<input v-model.trim="form.code" required /></label>
          <label>Tên voucher<input v-model.trim="form.name" required /></label>
          <label>
            Loại giảm
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
          <label>
            Trạng thái
            <select v-model="form.status">
              <option value="draft">Bản nháp</option>
              <option value="active">Đang áp dụng</option>
              <option value="inactive">Đã tắt</option>
            </select>
          </label>
        </div>
        <label>Mô tả<textarea v-model.trim="form.description" rows="3" /></label>
        <p class="helper">Phạm vi mặc định là cụm sân đang chọn. Không tạo voucher hệ thống tại màn này.</p>
        <footer>
          <button class="btn secondary" type="button" @click="closeForm">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">
            <AppIcon name="check" size="16" />
            {{ saving ? 'Đang lưu...' : 'Lưu voucher' }}
          </button>
        </footer>
      </form>
    </div>

    <div v-if="detailModal.show" class="modal-backdrop" @click.self="closeDetail">
      <section class="modal wide">
        <header class="modal-head">
          <div>
            <p class="eyebrow">Chi tiết voucher sân</p>
            <h3>{{ detailVoucher?.code }} - {{ detailVoucher?.name }}</h3>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="closeDetail">
            <AppIcon name="x" size="16" />
          </button>
        </header>

        <div class="detail-grid">
          <article class="detail-card">
            <span>Điều kiện áp dụng</span>
            <strong>{{ detailVoucher?.discount_label || discountText(detailVoucher || {}) }}</strong>
            <small>Đơn tối thiểu {{ money(detailVoucher?.min_order_amount) }}, giảm tối đa {{ money(detailVoucher?.max_discount_amount) }}</small>
          </article>
          <article class="detail-card">
            <span>Số lượng</span>
            <strong>{{ detailVoucher?.used_quantity || 0 }} / {{ detailVoucher?.total_quantity || 'Không giới hạn' }}</strong>
            <div class="progress"><i :style="{ width: `${detailVoucher?.usage_percent || 0}%` }"></i></div>
          </article>
          <article class="detail-card">
            <span>Phạm vi</span>
            <strong>{{ detailVoucher?.scope_label || 'Toàn cụm sân' }}</strong>
            <small>Chỉ áp dụng trong cụm sân đang chọn.</small>
          </article>
        </div>

        <nav class="tabs">
          <button type="button" :class="{ active: detailTab === 'usage' }" @click="detailTab = 'usage'">Lịch sử sử dụng</button>
          <button type="button" :class="{ active: detailTab === 'audit' }" @click="detailTab = 'audit'">Lịch sử thay đổi</button>
        </nav>

        <div v-if="detailLoading" class="state">Đang tải chi tiết...</div>
        <div v-else-if="detailTab === 'usage'" class="list-box">
          <article v-for="usage in detailData.usages" :key="usage.id">
            <strong>{{ usage.user_name }}</strong>
            <span>Đã giảm {{ money(usage.discount_amount) }}</span>
            <small>{{ dateTime(usage.created_at) }}</small>
          </article>
          <p v-if="!detailData.usages?.length" class="muted">Chưa có lượt sử dụng.</p>
        </div>
        <div v-else class="list-box">
          <article v-for="log in detailData.audit_logs" :key="log.id">
            <strong>{{ log.action_label }}</strong>
            <small>{{ dateTime(log.created_at) }}</small>
            <details>
              <summary>Xem dữ liệu kỹ thuật</summary>
              <pre>{{ formatJson({ old: log.technical_old_values, new: log.technical_new_values }) }}</pre>
            </details>
          </article>
          <p v-if="!detailData.audit_logs?.length" class="muted">Chưa có lịch sử thay đổi.</p>
        </div>
      </section>
    </div>

    <div v-if="deactivateModal.show" class="modal-backdrop" @click.self="closeDeactivate">
      <form class="modal small" @submit.prevent="turnOff">
        <header class="modal-head">
          <div>
            <p class="eyebrow">Tắt voucher</p>
            <h3>{{ deactivateModal.voucher?.code }}</h3>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="closeDeactivate">
            <AppIcon name="x" size="16" />
          </button>
        </header>
        <label>
          Lý do
          <textarea v-model.trim="deactivateModal.reason" rows="3" required />
        </label>
        <footer>
          <button class="btn secondary" type="button" @click="closeDeactivate">Hủy</button>
          <button class="btn danger" type="submit" :disabled="saving">Tắt voucher</button>
        </footer>
      </form>
    </div>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { ownerVoucherService } from '../../services/ownerVoucherService.js';

export default {
  name: 'OwnerVouchers',
  components: { AppIcon },
  data() {
    return {
      vouchers: [],
      summary: {},
      meta: { current_page: 1, last_page: 1, per_page: 12, total: 0 },
      filters: { keyword: '', status: '', discount_type: '', per_page: 12 },
      loading: false,
      saving: false,
      showModal: false,
      error: '',
      success: '',
      form: this.emptyForm(),
      searchTimer: null,
      detailModal: { show: false, voucher: null },
      detailData: { usages: [], audit_logs: [] },
      detailLoading: false,
      detailTab: 'usage',
      deactivateModal: { show: false, voucher: null, reason: '' },
    };
  },
  computed: {
    detailVoucher() {
      return this.detailData.voucher || this.detailModal.voucher;
    },
  },
  mounted() {
    window.addEventListener('owner-cluster-changed', this.loadCurrentCluster);
    this.load();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.loadCurrentCluster);
    clearTimeout(this.searchTimer);
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
      };
    },
    loadCurrentCluster() {
      this.load(1);
    },
    async load(page = this.meta.current_page || 1) {
      this.loading = true;
      this.error = '';
      try {
        const response = await ownerVoucherService.list({ ...this.filters, page });
        this.vouchers = response.data || [];
        this.summary = response.summary || {};
        this.meta = response.meta || this.meta;
      } catch (error) {
        this.error = error.message || 'Không thể tải voucher của sân.';
      } finally {
        this.loading = false;
      }
    },
    scheduleSearch() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => this.load(1), 300);
    },
    resetFilters() {
      this.filters = { keyword: '', status: '', discount_type: '', per_page: 12 };
      this.load(1);
    },
    openForm(voucher = null) {
      this.form = voucher ? { ...this.emptyForm(), ...voucher, valid_from: this.inputDate(voucher.valid_from), valid_to: this.inputDate(voucher.valid_to) } : this.emptyForm();
      this.showModal = true;
    },
    closeForm() {
      this.showModal = false;
    },
    async save() {
      this.saving = true;
      this.error = '';
      try {
        const response = this.form.id
          ? await ownerVoucherService.update(this.form.id, this.form)
          : await ownerVoucherService.create(this.form);
        this.success = response.message || 'Đã lưu voucher sân.';
        this.closeForm();
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể lưu voucher.';
      } finally {
        this.saving = false;
      }
    },
    async openDetail(voucher) {
      this.detailModal = { show: true, voucher };
      this.detailTab = 'usage';
      this.detailLoading = true;
      try {
        const response = await ownerVoucherService.get(voucher.id);
        this.detailData = response.data || { voucher };
      } catch (error) {
        this.error = error.message || 'Không thể tải chi tiết voucher.';
      } finally {
        this.detailLoading = false;
      }
    },
    closeDetail() {
      this.detailModal = { show: false, voucher: null };
      this.detailData = { usages: [], audit_logs: [] };
    },
    openDeactivate(voucher) {
      this.deactivateModal = { show: true, voucher, reason: 'Chủ sân tắt voucher.' };
    },
    closeDeactivate() {
      this.deactivateModal = { show: false, voucher: null, reason: '' };
    },
    async turnOff() {
      if (!this.deactivateModal.voucher) return;
      this.saving = true;
      try {
        const response = await ownerVoucherService.deactivate(this.deactivateModal.voucher.id, this.deactivateModal.reason);
        this.success = response.message || 'Đã tắt voucher sân.';
        this.closeDeactivate();
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể tắt voucher.';
      } finally {
        this.saving = false;
      }
    },
    discountText(voucher) {
      return voucher.discount_type === 'percent' ? `${Number(voucher.discount_value || 0)}%` : this.money(voucher.discount_value);
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(value || 0));
    },
    date(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    dateTime(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
    inputDate(value) {
      if (!value) return '';
      const date = new Date(value);
      return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    },
    formatJson(value) {
      return JSON.stringify(value || {}, null, 2);
    },
  },
};
</script>

<style scoped>
.page { display: grid; gap: 16px; }
.hero-card { background: linear-gradient(135deg, #07130d, #0f2418); color: #fff; border-radius: 16px; padding: 20px; }
.page-head { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
.page-head h2 { margin: 0 0 6px; }
.page-head p { margin: 0; color: #cbd5e1; }
.eyebrow { margin: 0 0 6px; color: #86efac; font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
.stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; display: grid; gap: 6px; }
.stat-card strong { font-size: 28px; color: #0f172a; }
.stat-card span { color: #64748b; font-weight: 800; }
.stat-card.success { border-color: #bbf7d0; background: #f0fdf4; }
.stat-card.warning { border-color: #fde68a; background: #fffbeb; }
.stat-card.danger { border-color: #fecaca; background: #fff7f7; }
.filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px; }
.search-box { flex: 1 1 320px; display: flex; align-items: center; gap: 8px; }
.search-box input, select, input, textarea { width: 100%; border: 1px solid #dbe3ef; border-radius: 10px; padding: 10px 12px; font: inherit; background: #fff; color: #0f172a; }
.filters select { max-width: 220px; }
.notice { display: flex; align-items: center; gap: 8px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; border-radius: 12px; padding: 12px; font-weight: 900; }
.table-card, .modal { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; }
.table-card { overflow: auto; }
table { width: 100%; border-collapse: collapse; min-width: 980px; }
th, td { padding: 13px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
th { background: #f8fafc; color: #334155; font-size: 12px; text-transform: uppercase; }
td span { display: block; color: #64748b; font-size: 13px; margin-top: 4px; }
.actions-col { width: 130px; }
.actions { display: flex; gap: 6px; }
.usage-line { display: grid; gap: 7px; min-width: 120px; }
.progress { height: 7px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
.progress i { display: block; height: 100%; background: #16a34a; border-radius: inherit; }
.btn, .icon-btn { border: 0; border-radius: 10px; font-weight: 900; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
.btn { padding: 10px 14px; }
.icon-btn { width: 36px; height: 36px; border: 1px solid #dbe3ef; background: #fff; color: #334155; }
.primary { background: #16a34a; color: #fff; }
.secondary { background: #f1f5f9; color: #0f172a; }
.danger, .icon-btn.danger { background: #fee2e2; color: #b91c1c; }
.badge { border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 900; white-space: nowrap; }
.badge.success, .badge.active { background: #dcfce7; color: #166534; }
.badge.danger, .badge.inactive, .badge.expired { background: #fee2e2; color: #991b1b; }
.badge.neutral, .badge.draft { background: #f1f5f9; color: #475569; }
.state { padding: 24px; text-align: center; color: #64748b; font-weight: 800; }
.alert { padding: 12px; border-radius: 10px; font-weight: 800; }
.alert.error { background: #fee2e2; color: #991b1b; }
.alert.success { background: #dcfce7; color: #166534; }
.pagination { display: flex; justify-content: flex-end; align-items: center; gap: 12px; color: #64748b; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .56); display: grid; place-items: center; z-index: 500; padding: 20px; }
.modal { width: min(560px, calc(100vw - 32px)); max-height: 92vh; overflow: auto; padding: 22px; display: grid; gap: 16px; }
.modal.wide { width: min(840px, calc(100vw - 32px)); }
.modal.small { width: min(520px, calc(100vw - 32px)); }
.modal-head, footer { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
.modal h3 { margin: 0; }
.form-grid, .detail-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
label { display: grid; gap: 6px; font-weight: 800; color: #334155; }
.helper { margin: 0; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; }
.detail-card { display: grid; gap: 7px; background: #f8fafc; border-radius: 12px; padding: 14px; }
.detail-card span, .detail-card small, .muted { color: #64748b; }
.tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.tabs button { border: 1px solid #dbe3ef; background: #fff; border-radius: 10px; padding: 9px 12px; font-weight: 900; cursor: pointer; }
.tabs button.active { border-color: #22c55e; background: #dcfce7; color: #166534; }
.list-box { display: grid; gap: 10px; }
.list-box article { display: grid; gap: 5px; background: #f8fafc; border-radius: 10px; padding: 12px; }
summary { cursor: pointer; font-weight: 900; color: #475569; }
pre { max-height: 260px; overflow: auto; background: #0f172a; color: #e2e8f0; border-radius: 8px; padding: 12px; }
.spinner { width: 18px; height: 18px; border: 2px solid #bbf7d0; border-top-color: #16a34a; border-radius: 50%; display: inline-block; margin-right: 8px; animation: spin .8s linear infinite; vertical-align: middle; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 920px) {
  .page-head, .modal-head, footer { display: grid; }
  .stat-grid, .form-grid, .detail-grid { grid-template-columns: 1fr; }
  .filters select { max-width: none; }
}
</style>
