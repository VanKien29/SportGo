<template>
  <section class="pf-page">
    <header class="page-head">
      <div>
        <p class="eyebrow">Phí duy trì nền tảng</p>
        <h2>Cấu hình bậc phí nền tảng</h2>
        <p>Quản lý bậc phí dựa trên số lượng sân con và chu kỳ đóng phí.</p>
      </div>
      <div class="head-actions">
        <button class="btn secondary icon-text" type="button" @click="checkCoverage">
          <AppIcon name="check" size="18" />
          <span>Kiểm tra khoảng bậc phí</span>
        </button>
        <button class="btn primary icon-text" type="button" @click="openCreate">
          <AppIcon name="plus" size="18" />
          <span>Thêm bậc phí</span>
        </button>
      </div>
    </header>

    <div class="notice-card">
      Phí nền tảng tính theo số lượng sân con và chu kỳ đóng phí, không tính theo phần trăm doanh thu booking.
    </div>

    <div class="info-grid">
      <article v-for="item in businessNotes" :key="item" class="info-card">{{ item }}</article>
    </div>

    <div v-if="toast" class="toast" :class="toastType">{{ toast }}</div>

    <section class="panel filter-panel">
      <input v-model.trim="keyword" placeholder="Tìm theo tên bậc phí" />
      <select v-model="statusFilter">
        <option value="">Tất cả trạng thái</option>
        <option value="active">Đang dùng</option>
        <option value="inactive">Ngưng dùng</option>
      </select>
      <button class="btn secondary icon-text" type="button" @click="resetStore">
        <AppIcon name="refresh" size="18" />
        <span>Khôi phục dữ liệu mẫu</span>
      </button>
    </section>

    <section class="panel">
      <div class="panel-title">
        <strong>Danh sách bậc phí</strong>
        <span>{{ filteredTiers.length }} bậc phí</span>
      </div>
      <div v-if="filteredTiers.length === 0" class="empty">Chưa có bậc phí. Hãy tạo bậc phí đầu tiên.</div>
      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Tên bậc</th>
              <th>Khoảng số sân</th>
              <th>Giá / sân / tháng</th>
              <th>1 tháng</th>
              <th>3 tháng</th>
              <th>6 tháng</th>
              <th>9 tháng</th>
              <th>12 tháng</th>
              <th>Trạng thái</th>
              <th>Ledger dùng</th>
              <th>Cập nhật</th>
              <th class="actions-header">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tier in filteredTiers" :key="tier.id">
              <td>
                <strong>{{ tier.name }}</strong>
                <small>{{ tier.note || 'Không có ghi chú' }}</small>
              </td>
              <td>{{ rangeLabel(tier) }}</td>
              <td>{{ money(tier.price_per_court_month) }}</td>
              <td>{{ percent(tier.discount_1_month) }}</td>
              <td>{{ percent(tier.discount_3_months) }}</td>
              <td>{{ percent(tier.discount_6_months) }}</td>
              <td>{{ percent(tier.discount_9_months) }}</td>
              <td>{{ percent(tier.discount_12_months) }}</td>
              <td>
                <span
                  class="status-dot"
                  :class="{ inactive: !tier.is_active }"
                  :title="tier.is_active ? 'Đang dùng' : 'Ngưng dùng'"
                  :aria-label="tier.is_active ? 'Đang dùng' : 'Ngưng dùng'"
                ></span>
              </td>
              <td>{{ usageCount(tier.id) }}</td>
              <td>{{ date(tier.updated_at) }}</td>
              <td>
                <div class="actions">
                  <button class="icon-btn" type="button" title="Xem chi tiết" aria-label="Xem chi tiết" @click="viewTier(tier)">
                    <AppIcon name="eye" size="18" />
                  </button>
                  <button class="icon-btn" type="button" title="Sửa bậc phí" aria-label="Sửa bậc phí" @click="openEdit(tier)">
                    <AppIcon name="pencil" size="18" />
                  </button>
                  <button
                    class="icon-btn"
                    type="button"
                    :title="tier.is_active ? 'Tắt trạng thái' : 'Bật trạng thái'"
                    :aria-label="tier.is_active ? 'Tắt trạng thái' : 'Bật trạng thái'"
                    @click="toggleTier(tier)"
                  >
                    <AppIcon :name="tier.is_active ? 'archive' : 'refresh'" size="18" />
                  </button>
                  <button class="icon-btn" type="button" title="Nhân bản bậc phí" aria-label="Nhân bản bậc phí" @click="cloneTier(tier)">
                    <AppIcon name="copy" size="18" />
                  </button>
                  <button class="icon-btn danger" type="button" title="Ngừng dùng" aria-label="Ngừng dùng" @click="removeTier(tier)">
                    <AppIcon name="trash" size="18" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel preview-panel">
      <div class="panel-title">
        <strong>Xem trước tính phí</strong>
        <span>Sử dụng đúng hàm calculatePlatformFee()</span>
      </div>
      <div class="preview-form">
        <label>
          Chọn cụm sân
          <select v-model="preview.venue_cluster_id" @change="syncPreviewCourtCount">
            <option value="">Nhập số sân giả lập</option>
            <option v-for="venue in venues" :key="venue.id" :value="venue.id">
              {{ venue.name }} - {{ venue.court_count }} sân
            </option>
          </select>
        </label>
        <label>
          Số sân giả lập
          <input v-model.number="preview.court_count" type="number" min="1" />
        </label>
        <label>
          Kỳ đóng
          <select v-model.number="preview.period_months">
            <option v-for="month in periods" :key="month" :value="month">{{ month }} tháng</option>
          </select>
        </label>
        <button class="btn primary icon-text" type="button" @click="runPreview">
          <AppIcon name="eye" size="18" />
          <span>Tính thử</span>
        </button>
      </div>

      <div v-if="previewError" class="alert error">{{ previewError }}</div>
      <div v-if="previewResult" class="preview-result">
        <div><span>Số sân</span><strong>{{ previewResult.court_count }}</strong></div>
        <div><span>Bậc phí</span><strong>{{ previewResult.tier.name }}</strong></div>
        <div><span>Giá/sân/tháng</span><strong>{{ money(previewResult.tier.price_per_court_month) }}</strong></div>
        <div><span>Số tháng</span><strong>{{ previewResult.period_months }}</strong></div>
        <div><span>Giảm giá</span><strong>{{ percent(previewResult.discount_percent) }}</strong></div>
        <div><span>Tổng trước giảm</span><strong>{{ money(previewResult.base_amount) }}</strong></div>
        <div><span>Số tiền giảm</span><strong>{{ money(previewResult.discount_amount) }}</strong></div>
        <div><span>Tổng phải đóng</span><strong>{{ money(previewResult.amount_due) }}</strong></div>
      </div>
      <div v-for="warning in previewWarnings" :key="warning" class="alert warning">{{ warning }}</div>
    </section>

    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <form class="modal" @submit.prevent="saveTier">
        <header class="modal-head">
          <h3>{{ editingId ? 'Sửa bậc phí nền tảng' : 'Thêm bậc phí nền tảng' }}</h3>
          <button class="icon-close" type="button" title="Đóng" aria-label="Đóng" @click="closeModal">
            <AppIcon name="x" size="18" />
          </button>
        </header>

        <div class="form-grid">
          <label>
            Tên bậc phí *
            <input v-model.trim="form.name" />
            <small v-if="fieldError('name')" class="field-error">{{ fieldError('name') }}</small>
          </label>
          <label>
            Giá / sân / tháng *
            <input v-model.number="form.price_per_court_month" type="number" min="1" />
            <small v-if="fieldError('price_per_court_month')" class="field-error">{{ fieldError('price_per_court_month') }}</small>
          </label>
          <label>
            Số sân tối thiểu *
            <input v-model.number="form.min_courts" type="number" min="1" step="1" />
            <small v-if="fieldError('min_courts')" class="field-error">{{ fieldError('min_courts') }}</small>
          </label>
          <label>
            Số sân tối đa
            <input v-model="form.max_courts" type="number" min="1" step="1" placeholder="Bỏ trống = không giới hạn" />
            <small v-if="fieldError('max_courts')" class="field-error">{{ fieldError('max_courts') }}</small>
          </label>
          <label v-for="field in discountFields" :key="field.key">
            {{ field.label }}
            <input v-model.number="form[field.key]" type="number" min="0" max="100" step="0.01" />
            <small v-if="fieldError(field.key)" class="field-error">{{ fieldError(field.key) }}</small>
          </label>
          <label class="check-row">
            <input v-model="form.is_active" type="checkbox" />
            <span>Đang dùng</span>
          </label>
          <label class="full">
            Ghi chú nội bộ
            <textarea v-model.trim="form.note" rows="3"></textarea>
          </label>
        </div>

        <div v-if="formErrors._coverage" class="alert error">
          <div v-for="message in formErrors._coverage" :key="message">{{ message }}</div>
        </div>

        <footer class="modal-actions">
          <button class="btn secondary" type="button" @click="closeModal">Hủy</button>
          <button class="btn primary icon-text" type="submit">
            <AppIcon name="check" size="18" />
            <span>Lưu bậc phí</span>
          </button>
        </footer>
      </form>
    </div>

    <div v-if="viewingTier" class="modal-backdrop" @click.self="viewingTier = null">
      <div class="modal detail-modal">
        <header class="modal-head">
          <h3>Chi tiết bậc phí</h3>
          <button class="icon-close" type="button" title="Đóng" aria-label="Đóng" @click="viewingTier = null">
            <AppIcon name="x" size="18" />
          </button>
        </header>
        <div class="detail-grid">
          <div><span>Tên bậc</span><strong>{{ viewingTier.name }}</strong></div>
          <div><span>Khoảng sân</span><strong>{{ rangeLabel(viewingTier) }}</strong></div>
          <div><span>Giá</span><strong>{{ money(viewingTier.price_per_court_month) }}</strong></div>
          <div><span>Ledger đang dùng</span><strong>{{ usageCount(viewingTier.id) }}</strong></div>
          <div class="full"><span>Ghi chú</span><strong>{{ viewingTier.note || '-' }}</strong></div>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import { platformFeeStore } from '../../stores/platformFee.store.js';
import AppIcon from '../../components/AppIcon.vue';
import {
  calculatePlatformFee,
  createTier,
  deactivateTier,
  deleteTier,
  findTierForCourtCount,
  getTierUsageCount,
  getTiers,
  reactivateTier,
  updateTier,
  validateTierCoverage,
} from '../../services/platformFeeTier.service.js';

const defaultForm = () => ({
  name: '',
  min_courts: 1,
  max_courts: '',
  price_per_court_month: 50000,
  discount_1_month: 0,
  discount_3_months: 0,
  discount_6_months: 0,
  discount_9_months: 0,
  discount_12_months: 0,
  is_active: true,
  note: '',
});

export default {
  name: 'AdminPlatformFeeTiers',
  components: { AppIcon },
  data() {
    return {
      tiers: [],
      venues: platformFeeStore.state.venues,
      keyword: '',
      statusFilter: '',
      showModal: false,
      editingId: null,
      viewingTier: null,
      form: defaultForm(),
      formErrors: {},
      preview: { venue_cluster_id: '', court_count: 3, period_months: 3 },
      previewResult: null,
      previewError: '',
      previewWarnings: [],
      toast: '',
      toastType: 'success',
      periods: [1, 3, 6, 9, 12],
      businessNotes: [
        'Phí tính theo số sân con',
        'Không tính theo doanh thu booking',
        'Ledger đã tạo giữ nguyên snapshot',
        'Quá hạn có thể khóa cụm sân',
        'Email nhắc phí gửi theo ngày đến hạn',
      ],
      discountFields: [
        { key: 'discount_1_month', label: 'Giảm kỳ 1 tháng (%)' },
        { key: 'discount_3_months', label: 'Giảm kỳ 3 tháng (%)' },
        { key: 'discount_6_months', label: 'Giảm kỳ 6 tháng (%)' },
        { key: 'discount_9_months', label: 'Giảm kỳ 9 tháng (%)' },
        { key: 'discount_12_months', label: 'Giảm kỳ 12 tháng (%)' },
      ],
    };
  },
  computed: {
    filteredTiers() {
      return this.tiers.filter((tier) => {
        const matchKeyword = !this.keyword || tier.name.toLowerCase().includes(this.keyword.toLowerCase());
        const matchStatus = !this.statusFilter || (this.statusFilter === 'active' ? tier.is_active : !tier.is_active);
        return matchKeyword && matchStatus;
      });
    },
  },
  mounted() {
    this.loadTiers();
    this.runPreview();
  },
  methods: {
    loadTiers() {
      this.tiers = getTiers();
    },
    openCreate() {
      this.editingId = null;
      this.form = defaultForm();
      this.formErrors = {};
      this.showModal = true;
    },
    openEdit(tier) {
      this.editingId = tier.id;
      this.form = { ...tier, max_courts: tier.max_courts ?? '' };
      this.formErrors = {};
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
      this.formErrors = {};
    },
    async saveTier() {
      try {
        if (this.editingId) await updateTier(this.editingId, this.form);
        else await createTier(this.form);
        this.showMessage('Đã lưu bậc phí.');
        this.closeModal();
        this.loadTiers();
        this.runPreview();
      } catch (error) {
        this.formErrors = error.validation?.errors || { _coverage: [error.message] };
        this.showMessage(error.message, 'error');
      }
    },
    async toggleTier(tier) {
      try {
        if (tier.is_active) await deactivateTier(tier.id, 'Admin tắt trạng thái');
        else await reactivateTier(tier.id);
        this.showMessage('Đã cập nhật trạng thái bậc phí.');
        this.loadTiers();
      } catch (error) {
        this.showMessage(error.message, 'error');
      }
    },
    async cloneTier(tier) {
      try {
        await createTier({
          ...tier,
          name: `${tier.name} bản sao`,
          is_active: false,
          max_courts: tier.max_courts ?? '',
        });
        this.showMessage('Đã nhân bản bậc phí ở trạng thái ngưng dùng.');
        this.loadTiers();
      } catch (error) {
        this.showMessage(error.message, 'error');
      }
    },
    async removeTier(tier) {
      const reason = prompt('Nhập lý do ngừng dùng bậc phí:');
      if (!reason) return;
      try {
        await deleteTier(tier.id);
        this.showMessage('Đã ngừng dùng bậc phí.');
        this.loadTiers();
      } catch (error) {
        this.showMessage(error.message, 'error');
      }
    },
    viewTier(tier) {
      this.viewingTier = tier;
    },
    checkCoverage() {
      const result = validateTierCoverage(this.tiers);
      this.showMessage(result.isValid ? 'Khoảng bậc phí hợp lệ.' : result.errors.join(' '), result.isValid ? 'success' : 'error');
    },
    syncPreviewCourtCount() {
      const venue = this.venues.find((item) => item.id === this.preview.venue_cluster_id);
      if (venue) this.preview.court_count = venue.court_count;
      this.runPreview();
    },
    runPreview() {
      this.previewError = '';
      this.previewResult = null;
      this.previewWarnings = [];
      const coverage = validateTierCoverage(this.tiers);
      if (!coverage.isValid) {
        this.previewError = 'Cấu hình bậc phí hiện chưa hợp lệ, vui lòng sửa trước khi tạo kỳ phí.';
        return;
      }
      const found = findTierForCourtCount(this.preview.court_count);
      if (!found.tier) {
        this.previewError = 'Chưa có bậc phí phù hợp cho cụm sân này.';
        return;
      }
      this.previewResult = calculatePlatformFee({
        court_count: this.preview.court_count,
        period_months: this.preview.period_months,
        tier: found.tier,
      });
      this.previewWarnings = this.previewResult.warnings;
    },
    resetStore() {
      if (!confirm('Khôi phục dữ liệu mẫu phí nền tảng?')) return;
      platformFeeStore.reset();
      this.venues = platformFeeStore.state.venues;
      this.loadTiers();
      this.runPreview();
      this.showMessage('Đã khôi phục dữ liệu mẫu.');
    },
    fieldError(field) {
      return this.formErrors[field]?.[0] || '';
    },
    usageCount(id) {
      return getTierUsageCount(id);
    },
    rangeLabel(tier) {
      return tier.max_courts === null || tier.max_courts === ''
        ? `Từ ${tier.min_courts} sân trở lên`
        : `${tier.min_courts} - ${tier.max_courts} sân`;
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
    },
    percent(value) {
      return `${Number(value || 0).toLocaleString('vi-VN')}%`;
    },
    date(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    showMessage(message, type = 'success') {
      this.toast = message;
      this.toastType = type;
      setTimeout(() => { this.toast = ''; }, 3500);
    },
  },
};
</script>

<style scoped>
.pf-page { display: flex; flex-direction: column; gap: 18px; }
.page-head, .head-actions, .panel-title, .filter-panel, .actions, .preview-form, .modal-head, .modal-actions, .icon-text { display: flex; gap: 12px; }
.page-head { justify-content: space-between; align-items: flex-start; }
.head-actions, .modal-actions { align-items: center; }
.eyebrow { margin: 0 0 4px; color: #16a34a; font-size: 12px; font-weight: 900; text-transform: uppercase; }
h2, h3, p { margin: 0; }
.panel, .notice-card, .info-card, .modal { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; }
.panel { padding: 16px; }
.notice-card { padding: 14px 16px; background: #fff7ed; color: #9a3412; font-weight: 800; }
.info-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; }
.info-card { padding: 12px; color: #334155; font-weight: 800; }
.filter-panel { align-items: center; }
input, select, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font: inherit; }
.filter-panel input { max-width: 360px; }
.filter-panel select { max-width: 220px; }
.panel-title { justify-content: space-between; align-items: center; margin-bottom: 12px; }
.panel-title span, small { color: #64748b; }
.table-wrap { overflow-x: auto; }
table { width: 100%; min-width: 1180px; border-collapse: collapse; }
th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
th { background: #f8fafc; color: #475569; font-size: 12px; text-transform: uppercase; }
.actions-header { text-align: center; }
td strong, td small { display: block; }
.status-dot {
  display: inline-grid;
  width: 14px;
  height: 14px;
  border-radius: 999px;
  background: #10b981;
  box-shadow: 0 0 0 3px #d1fae5;
}
.status-dot.inactive {
  background: #94a3b8;
  box-shadow: 0 0 0 3px #e2e8f0;
}
.actions { flex-wrap: wrap; justify-content: center; min-width: 176px; }
.icon-btn, .icon-close {
  display: inline-grid;
  place-items: center;
  border: 1px solid #dbe3ea;
  border-radius: 8px;
  background: #f8fafc;
  color: #334155;
  cursor: pointer;
}
.icon-btn {
  width: 34px;
  height: 34px;
}
.icon-btn:hover:not(:disabled) { background: #eef2f7; }
.icon-btn.danger { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
.icon-btn:disabled { cursor: not-allowed; opacity: .45; }
.icon-close {
  width: 32px;
  height: 32px;
}
.btn { border: 0; border-radius: 8px; padding: 10px 14px; font-weight: 900; cursor: pointer; }
.btn.primary { background: #16a34a; color: #fff; }
.btn.secondary { background: #e2e8f0; color: #334155; }
.icon-text { align-items: center; justify-content: center; }
.preview-form { display: grid; grid-template-columns: 1.5fr 1fr 1fr auto; align-items: end; }
label { display: flex; flex-direction: column; gap: 6px; font-weight: 800; color: #334155; }
.preview-result, .detail-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
.preview-result div, .detail-grid div { background: #f8fafc; border-radius: 8px; padding: 12px; }
.preview-result span, .detail-grid span { display: block; color: #64748b; font-size: 12px; }
.alert { border-radius: 8px; padding: 10px 12px; margin-top: 10px; font-weight: 800; }
.alert.error, .toast.error { background: #fef2f2; color: #991b1b; }
.alert.warning { background: #fef3c7; color: #92400e; }
.toast.success { background: #ecfdf5; color: #047857; }
.toast { border-radius: 8px; padding: 11px 13px; font-weight: 800; }
.empty { padding: 36px; text-align: center; color: #64748b; }
.modal-backdrop { position: fixed; inset: 0; z-index: 900; display: grid; place-items: center; padding: 20px; background: rgba(15,23,42,.55); }
.modal { width: min(840px, calc(100vw - 32px)); max-height: calc(100vh - 40px); overflow: auto; }
.modal-head { justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #e2e8f0; }
.modal-head button { border: 0; background: transparent; font-weight: 900; cursor: pointer; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; padding: 18px 22px; }
.full { grid-column: 1 / -1; }
.check-row { flex-direction: row; align-items: center; }
.check-row input { width: auto; }
.field-error { color: #b91c1c; font-weight: 800; }
.modal-actions { justify-content: flex-end; padding: 16px 22px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
@media (max-width: 900px) {
  .page-head { flex-direction: column; }
  .info-grid, .preview-result, .detail-grid { grid-template-columns: 1fr 1fr; }
  .preview-form, .form-grid { grid-template-columns: 1fr; }
}
</style>
