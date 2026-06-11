<template>
  <section class="settings-page">
    <header class="page-head">
      <div>
        <p class="eyebrow">QUY ĐỊNH BOOKING</p>
        <h2>Cấu hình đặt sân</h2>
        <p>Thiết lập thời lượng, thời gian giữ chỗ, nhắc lịch và hình thức thanh toán.</p>
      </div>
      <label class="cluster-select">
        <span>Cụm sân</span>
        <select v-model="selectedClusterId" :disabled="loading || !clusters.length">
          <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">{{ cluster.name }}</option>
        </select>
      </label>
    </header>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="notice" class="alert success">{{ notice }}</div>
    <div v-if="loading" class="state-card">Đang tải cấu hình đặt sân...</div>
    <div v-else-if="!selectedClusterId" class="state-card">Chưa có cụm sân để cấu hình.</div>

    <form v-else class="settings-form" @submit.prevent="save">
      <article class="setting-card">
        <header class="card-head">
          <div>
            <h3>Thời lượng một booking</h3>
          </div>
        </header>

        <div class="field-grid">
          <label>
            Thời lượng tối thiểu
            <div class="input-unit">
              <input v-model.number="form.min_duration_minutes" type="number" min="30" step="30" required>
              <span>phút</span>
            </div>
            <small>Khách không thể đặt ít hơn thời lượng này.</small>
          </label>
          <label>
            Thời lượng tối đa
            <div class="input-unit">
              <input v-model.number="form.max_duration_minutes" type="number" min="30" step="30" placeholder="Không giới hạn">
              <span>phút</span>
            </div>
            <small>Để trống nếu không giới hạn thời lượng tối đa.</small>
          </label>
        </div>
      </article>

      <article class="setting-card">
        <header class="card-head">
          <div>
            <h3>Giữ chỗ và nhắc lịch</h3>
          </div>
        </header>

        <div class="field-grid">
          <label>
            Thời gian giữ chỗ
            <div class="input-unit">
              <input v-model.number="form.slot_hold_minutes" type="number" min="5" max="120" required>
              <span>phút</span>
            </div>
            <small>Booking chờ thanh toán sẽ hết hạn sau khoảng này.</small>
          </label>
          <label>
            Nhắc khách trước giờ chơi
            <div class="input-unit">
              <input v-model.number="form.reminder_before_minutes" type="number" min="0" max="10080" required>
              <span>phút</span>
            </div>
            <small>Nhập 0 nếu không muốn gửi nhắc lịch.</small>
          </label>
        </div>
      </article>

      <article class="setting-card">
        <header class="card-head">
          <div>
            <h3>Hình thức thanh toán</h3>
          </div>
        </header>

        <div class="payment-list">
          <label class="payment-option" :class="{ enabled: form.allow_full_payment }">
            <input v-model="form.allow_full_payment" type="checkbox">
            <span>
              <strong>Thanh toán đủ</strong>
              <small>Khách thanh toán trực tuyến 100% giá trị booking.</small>
            </span>
          </label>

          <label class="payment-option" :class="{ enabled: form.allow_deposit }">
            <input v-model="form.allow_deposit" type="checkbox">
            <span>
              <strong>Đặt cọc</strong>
              <small>Khách trả trước một phần và thanh toán phần còn lại tại sân.</small>
            </span>
            <div v-if="form.allow_deposit" class="deposit-field" @click.stop>
              <input v-model.number="form.deposit_percent" type="number" min="1" max="100" required>
              <span>%</span>
            </div>
          </label>

          <label class="payment-option" :class="{ enabled: form.allow_no_prepay }">
            <input v-model="form.allow_no_prepay" type="checkbox">
            <span>
              <strong>Trả sau tại sân</strong>
              <small>Khách không cần thanh toán trước khi gửi yêu cầu đặt sân.</small>
            </span>
          </label>
        </div>

        <p v-if="validationMessage" class="validation-note">{{ validationMessage }}</p>
      </article>

      <footer class="save-bar">
        <div>
          <strong>{{ selectedCluster?.name }}</strong>
          <span>Cấu hình mới áp dụng cho các booking tạo sau khi lưu.</span>
        </div>
        <button class="primary-btn" type="submit" :disabled="saving || Boolean(validationMessage)">
          {{ saving ? 'Đang lưu...' : 'Lưu cấu hình đặt sân' }}
        </button>
      </footer>
    </form>
  </section>
</template>

<script>
import { ownerBookingConfigService } from '../../services/ownerBookingConfigs.js';

export default {
  name: 'OwnerBookingSettings',
  data() {
    return {
      clusters: [],
      selectedClusterId: localStorage.getItem('selected_cluster') || '',
      loading: true,
      saving: false,
      error: '',
      notice: '',
      form: this.defaultForm(),
    };
  },
  computed: {
    selectedCluster() {
      return this.clusters.find((cluster) => cluster.id === this.selectedClusterId) || null;
    },
    validationMessage() {
      if (this.form.min_duration_minutes % 30 !== 0) return 'Thời lượng tối thiểu phải chia hết cho 30 phút.';
      if (this.form.max_duration_minutes && this.form.max_duration_minutes % 30 !== 0) return 'Thời lượng tối đa phải chia hết cho 30 phút.';
      if (this.form.max_duration_minutes && this.form.max_duration_minutes < this.form.min_duration_minutes) return 'Thời lượng tối đa không được nhỏ hơn tối thiểu.';
      if (!this.form.allow_full_payment && !this.form.allow_deposit && !this.form.allow_no_prepay) return 'Phải bật ít nhất một hình thức thanh toán.';
      if (this.form.allow_deposit && (!this.form.deposit_percent || this.form.deposit_percent < 1 || this.form.deposit_percent > 100)) return 'Phần trăm cọc phải từ 1 đến 100.';
      return '';
    },
  },
  watch: {
    selectedClusterId(value) {
      if (value) localStorage.setItem('selected_cluster', value);
      this.syncForm();
    },
  },
  async mounted() {
    window.addEventListener('owner-cluster-changed', this.handleClusterChanged);
    await this.load();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.handleClusterChanged);
  },
  methods: {
    defaultForm() {
      return {
        min_duration_minutes: 30,
        max_duration_minutes: null,
        slot_hold_minutes: 20,
        reminder_before_minutes: 30,
        allow_full_payment: true,
        allow_deposit: true,
        allow_no_prepay: true,
        deposit_percent: 30,
      };
    },
    async handleClusterChanged(event) {
      this.selectedClusterId = event.detail?.id || localStorage.getItem('selected_cluster') || '';
    },
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const response = await ownerBookingConfigService.list();
        this.clusters = response.data || [];
        if (!this.clusters.some((cluster) => cluster.id === this.selectedClusterId)) {
          this.selectedClusterId = this.clusters[0]?.id || '';
        }
        this.syncForm();
      } catch (error) {
        this.error = error.message || 'Không thể tải cấu hình đặt sân.';
      } finally {
        this.loading = false;
      }
    },
    syncForm() {
      const config = this.selectedCluster?.booking_config;
      this.form = config ? {
        min_duration_minutes: Number(config.min_duration_minutes),
        max_duration_minutes: config.max_duration_minutes === null ? null : Number(config.max_duration_minutes),
        slot_hold_minutes: Number(config.slot_hold_minutes),
        reminder_before_minutes: Number(config.reminder_before_minutes),
        allow_full_payment: Boolean(config.allow_full_payment),
        allow_deposit: Boolean(config.allow_deposit),
        allow_no_prepay: Boolean(config.allow_no_prepay),
        deposit_percent: Number(config.deposit_percent || 30),
      } : this.defaultForm();
      this.error = '';
      this.notice = '';
    },
    async save() {
      if (!this.selectedClusterId || this.validationMessage) return;
      this.saving = true;
      this.error = '';
      this.notice = '';
      try {
        const response = await ownerBookingConfigService.update(this.selectedClusterId, {
          ...this.form,
          max_duration_minutes: this.form.max_duration_minutes || null,
          deposit_percent: this.form.allow_deposit ? this.form.deposit_percent : null,
        });
        const cluster = this.clusters.find((item) => item.id === this.selectedClusterId);
        if (cluster) cluster.booking_config = response.data;
        this.notice = response.message;
      } catch (error) {
        this.error = error.message || 'Không thể lưu cấu hình đặt sân.';
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<style scoped>
.settings-page{display:grid;gap:14px;max-width:1120px}.page-head,.card-head,.save-bar{display:flex;justify-content:space-between;align-items:flex-start;gap:18px}.page-head h2,.card-head h3{margin:0;color:#0f172a}.page-head>div>p:last-child{margin:6px 0 0;color:#64748b}.card-head h3{font-size:16px}.eyebrow{margin:0 0 6px;color:#059669;font-size:11px;font-weight:900;letter-spacing:.1em}.cluster-select{min-width:280px;display:grid;gap:7px;color:#475569;font-size:12px;font-weight:850}.cluster-select select,.input-unit input,.deposit-field input{width:100%;height:42px;border:1px solid #cbd5e1;border-radius:9px;padding:0 11px;background:#fff;color:#0f172a;font:inherit}.alert,.state-card{padding:12px 14px;border-radius:10px;font-weight:800}.alert.error{background:#fee2e2;color:#991b1b}.alert.success{background:#dcfce7;color:#166534}.state-card{text-align:center;background:#fff;border:1px solid #e2e8f0;color:#64748b}.settings-form{display:grid;gap:12px}.setting-card,.save-bar{padding:16px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;box-shadow:0 6px 20px rgba(15,23,42,.04)}.card-head{justify-content:flex-start;padding-bottom:12px;border-bottom:1px solid #e2e8f0}.field-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:14px}.field-grid label{display:grid;gap:6px;color:#334155;font-size:13px;font-weight:850}.field-grid small{color:#64748b;font-weight:400;line-height:1.4}.input-unit,.deposit-field{position:relative}.input-unit input{padding-right:58px}.input-unit>span,.deposit-field>span{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#64748b;font-size:12px;font-weight:800}.payment-list{display:grid;gap:8px;margin-top:14px}.payment-option{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:12px;padding:12px 14px;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer}.payment-option.enabled{border-color:#6ee7b7;background:#ecfdf5}.payment-option>input{width:18px;height:18px;accent-color:#059669}.payment-option>span{display:grid;gap:3px}.payment-option strong{color:#0f172a}.payment-option small{color:#64748b}.deposit-field{width:92px}.deposit-field input{padding-right:30px}.validation-note{margin:12px 0 0;padding:10px;border-radius:9px;background:#fff1f2;color:#be123c;font-size:13px;font-weight:800}.save-bar{position:sticky;bottom:12px;align-items:center;border-color:#a7f3d0}.save-bar>div{display:grid;gap:4px}.save-bar span{color:#64748b;font-size:12px}.primary-btn{border:0;border-radius:9px;padding:11px 16px;background:#059669;color:#fff;font:inherit;font-weight:850;cursor:pointer}.primary-btn:disabled{opacity:.55;cursor:not-allowed}@media(max-width:720px){.page-head,.field-grid,.save-bar{display:grid;grid-template-columns:1fr}.cluster-select{min-width:0}.payment-option{grid-template-columns:auto 1fr}.deposit-field{grid-column:2}.primary-btn{width:100%}}
</style>
