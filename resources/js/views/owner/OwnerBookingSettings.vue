<template>
  <section class="settings-page">
    <header class="page-head">
      <div>
        <p class="eyebrow">QUY ĐỊNH BOOKING</p>
        <h2>Cấu hình đặt sân</h2>
      </div>
      <label class="cluster-select">
        <span>Cụm sân</span>
        <select v-model="selectedClusterId" :disabled="loading || !clusters.length">
          <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">
            {{ cluster.name }}
          </option>
        </select>
      </label>
    </header>


    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="notice" class="alert success">{{ notice }}</div>
    <div v-if="loading" class="state-card">Đang tải cấu hình đặt sân...</div>
    <div v-else-if="!selectedClusterId" class="state-card">Chưa có cụm sân để cấu hình.</div>

    <form v-else class="settings-form" @submit.prevent="save">
      <div v-if="validationMessages.length" class="validation-summary" role="alert">
        <strong>Vui lòng kiểm tra lại</strong>
        <ul>
          <li v-for="message in validationMessages" :key="message">{{ message }}</li>
        </ul>
      </div>

      <article class="setting-card">
        <header class="card-head">
          <h3>Giờ hoạt động cố định</h3>
        </header>
        <div class="fixed-hours">
          <label>
            <span>Giờ mở cửa</span>
            <input
              v-model.trim="form.fixed_open_time"
              type="text"
              inputmode="numeric"
              maxlength="5"
              placeholder="08:00"
              pattern="(?:[01]\d|2[0-3]):[0-5]\d"
            >
          </label>
          <span class="range-arrow">→</span>
          <label>
            <span>Giờ đóng cửa</span>
            <input
              v-model.trim="form.fixed_close_time"
              type="text"
              inputmode="numeric"
              maxlength="5"
              placeholder="22:00"
              pattern="(?:(?:[01]\d|2[0-3]):[0-5]\d|24:00)"
            >
          </label>
          <label>
            <span>Thời lượng 1 booking</span>
            <div class="input-unit">
              <input v-model.number="form.min_duration_minutes" type="number" min="30" max="120" step="30" required>
              <span>phút</span>
            </div>
          </label>
        </div>
      </article>

      <article class="setting-card">
        <header class="card-head split">
          <h3>Giờ hoạt động theo ngày</h3>
          <button class="secondary-btn" type="button" @click="addSpecialHours">+ Thêm khoảng ngày</button>
        </header>

        <div v-if="!form.special_operating_hours.length" class="empty-row">Chưa có lịch tùy chỉnh.</div>
        <div v-else class="special-list">
          <div v-for="(hours, index) in form.special_operating_hours" :key="hours._key" class="special-row">
            <label>
              <span>Từ ngày</span>
              <input v-model="hours.start_date" type="date" @change="hours._touched = true">
            </label>
            <label>
              <span>Đến ngày</span>
              <input v-model="hours.end_date" type="date" :min="hours.start_date" @change="hours._touched = true">
            </label>
            <label>
              <span>Giờ mở</span>
              <select v-model="hours.open_time">
                <option v-for="time in openTimeOptions" :key="time" :value="time">{{ time }}</option>
              </select>
            </label>
            <label>
              <span>Giờ đóng</span>
              <select v-model="hours.close_time">
                <option v-for="time in closeTimeOptions" :key="time" :value="time">{{ time }}</option>
              </select>
            </label>
            <button class="remove-btn" type="button" :aria-label="`Xóa khoảng ngày ${index + 1}`" @click="removeSpecialHours(index)">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle;">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>
        </div>
      </article>

      <div class="two-column">
        <article class="setting-card">
          <header class="card-head"><h3>Đặt trước & giới hạn booking</h3></header>
          <div class="compact-fields">
            <label>
              <span>Đặt trước tối thiểu</span>
              <div class="input-unit">
                <input v-model.number="form.min_advance_booking_minutes" type="number" min="30" step="1" required>
                <span>phút</span>
              </div>
            </label>
            <label>
              <span>Thời lượng tối đa</span>
              <div class="input-unit">
                <input v-model.number="form.max_duration_minutes" type="number" min="30" max="1440" step="30" placeholder="Không giới hạn">
                <span>phút</span>
              </div>
            </label>
          </div>
        </article>

        <article class="setting-card">
          <header class="card-head"><h3>Giữ chỗ & nhắc lịch</h3></header>
          <div class="compact-fields">
            <label>
              <span>Thời gian giữ chỗ</span>
              <div class="input-unit">
                <input v-model.number="form.slot_hold_minutes" type="number" min="5" max="120" step="5" required>
                <span>phút</span>
              </div>
            </label>
            <label>
              <span>Nhắc trước giờ chơi</span>
              <div class="input-unit">
                <input v-model.number="form.reminder_before_minutes" type="number" min="0" max="10080" step="5" required>
                <span>phút</span>
              </div>
            </label>
          </div>
        </article>
      </div>

      <article class="setting-card">
        <header class="card-head"><h3>Hình thức thanh toán</h3></header>
        <div class="payment-list">
          <label class="payment-option" :class="{ enabled: form.allow_full_payment }">
            <input v-model="form.allow_full_payment" type="checkbox">
            <strong>Thanh toán đủ</strong>
          </label>
          <label class="payment-option" :class="{ enabled: form.allow_deposit }">
            <input v-model="form.allow_deposit" type="checkbox">
               <strong>Đặt cọc</strong>
            <div v-if="form.allow_deposit" class="deposit-field" @click.stop>
              <input v-model.number="form.deposit_percent" type="number" min="1" max="100" required>
              <span>%</span>
            </div>
          </label>
          <label class="payment-option" :class="{ enabled: form.allow_no_prepay }">
            <input v-model="form.allow_no_prepay" type="checkbox">
            <strong>Trả sau tại sân</strong>
          </label>
        </div>
      </article>

      <article class="setting-card">
        <header class="card-head"><h3>Hạng thành viên</h3></header>
        <div class="membership-table">
          <div class="membership-row membership-head">
            <span>Hạng</span>
            <span>Giảm (%)</span>
            <span>Booking lên hạng</span>
            <span>Chi tiêu lên hạng</span>
            <span>Kỳ duy trì</span>
            <span>Booking duy trì</span>
            <span>Chi tiêu duy trì</span>
          </div>
          <div v-for="tier in form.membership_tiers" :key="tier.tier_key" class="membership-row">
            <strong>{{ tier.label }}</strong>
            <input v-model.number="tier.discount_percent" type="number" min="0" max="100" step="0.1">
            <input v-model.number="tier.min_completed_bookings" type="number" min="0" step="1">
            <input v-model.number="tier.min_spend_amount" type="number" min="0" step="1000">
            <input v-model.number="tier.maintain_period_months" type="number" min="1" max="36" step="1" placeholder="Trống">
            <input v-model.number="tier.maintain_min_bookings" type="number" min="0" step="1" placeholder="Trống">
            <input v-model.number="tier.maintain_min_spend_amount" type="number" min="0" step="1000" placeholder="Trống">
          </div>
        </div>
      </article>

      <footer class="save-bar">
        <strong>{{ selectedCluster?.name }}</strong>
        <button class="primary-btn" type="submit" :disabled="saving">
          {{ saving ? 'Đang lưu...' : 'Lưu cấu hình' }}
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
      validationAttempted: false,
      form: null,
    };
  },
  computed: {
    selectedCluster() {
      return this.clusters.find((cluster) => cluster.id === this.selectedClusterId) || null;
    },
    allTimeOptions() {
      return Array.from({ length: 49 }, (_, index) => {
        const minutes = index * 30;
        return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
      });
    },
    openTimeOptions() {
      return this.allTimeOptions.slice(0, -1);
    },
    closeTimeOptions() {
      return this.allTimeOptions.slice(1);
    },
    validationMessages() {
      if (!this.form) return [];

      const messages = [];
      if (!Number.isInteger(this.form.min_advance_booking_minutes) || this.form.min_advance_booking_minutes < 30) {
        messages.push('Thời gian đặt trước tối thiểu là 30 phút.');
      }
      if (!Number.isInteger(this.form.min_duration_minutes) || this.form.min_duration_minutes < 30 || this.form.min_duration_minutes > 120 || this.form.min_duration_minutes % 30 !== 0) {
        messages.push('Thời lượng tối thiểu phải từ 30 phút đến 2 giờ, theo bước 30 phút.');
      }
      if (this.form.max_duration_minutes && (this.form.max_duration_minutes > 1440 || this.form.max_duration_minutes % 30 !== 0 || this.form.max_duration_minutes < this.form.min_duration_minutes)) {
        messages.push('Thời lượng tối đa phải từ mức tối thiểu đến 24 giờ, theo bước 30 phút.');
      }
      if (!Number.isInteger(this.form.slot_hold_minutes) || this.form.slot_hold_minutes < 5 || this.form.slot_hold_minutes > 120 || this.form.slot_hold_minutes % 5 !== 0) {
        messages.push('Thời gian giữ chỗ phải từ 5 đến 120 phút, theo bước 5 phút.');
      }
      if (!Number.isInteger(this.form.reminder_before_minutes) || this.form.reminder_before_minutes < 0 || this.form.reminder_before_minutes > 10080 || this.form.reminder_before_minutes % 5 !== 0) {
        messages.push('Thời gian nhắc lịch phải từ 0 đến 7 ngày, theo bước 5 phút.');
      }

      if (!this.validOpenTime(this.form.fixed_open_time) || !this.validCloseTime(this.form.fixed_close_time)) {
        messages.push('Giờ hoạt động phải đúng định dạng HH:mm.');
      } else if (!this.validOperatingRange(this.form.fixed_open_time, this.form.fixed_close_time)) {
        messages.push('Giờ mở cửa đến giờ đóng cửa phải từ 2 giờ đến 24 giờ.');
      }

      const sortedSpecial = [...this.form.special_operating_hours].sort((a, b) => a.start_date.localeCompare(b.start_date));
      sortedSpecial.forEach((hours, index) => {
        const shouldValidate = this.validationAttempted || hours._touched;
        if (shouldValidate && (!hours.start_date || !hours.end_date)) {
          messages.push(`Khoảng ngày tùy chỉnh ${index + 1}: vui lòng chọn đủ ngày bắt đầu và kết thúc.`);
        } else if (hours.end_date < hours.start_date) {
          messages.push(`Khoảng ngày tùy chỉnh ${index + 1}: ngày kết thúc phải từ ngày bắt đầu trở đi.`);
        }
        if (!this.validOperatingRange(hours.open_time, hours.close_time)) {
          messages.push(`Khoảng ngày tùy chỉnh ${index + 1}: giờ mở cửa đến giờ đóng cửa phải từ 2 giờ đến 24 giờ.`);
        }
        if (index > 0 && hours.start_date && hours.start_date <= sortedSpecial[index - 1].end_date) {
          messages.push('Các khoảng ngày tùy chỉnh không được chồng lấn.');
        }
      });

      if (!this.form.allow_full_payment && !this.form.allow_deposit && !this.form.allow_no_prepay) {
        messages.push('Phải bật ít nhất một hình thức thanh toán.');
      }
      if (this.form.allow_deposit && (!this.form.deposit_percent || this.form.deposit_percent < 1 || this.form.deposit_percent > 100)) {
        messages.push('Phần trăm cọc phải từ 1 đến 100.');
      }

      const tiers = this.form.membership_tiers || [];
      let previousBookings = -1;
      let previousSpend = -1;
      tiers.forEach((tier) => {
        if (tier.discount_percent < 0 || tier.discount_percent > 100) {
          messages.push('Giảm giá hạng thành viên phải từ 0 đến 100%.');
        }
        if (tier.min_completed_bookings < previousBookings || tier.min_spend_amount < previousSpend) {
          messages.push('Mốc lên hạng phải tăng dần theo thứ tự Thường, Bạc, Vàng, Kim cương.');
        }
        previousBookings = Number(tier.min_completed_bookings || 0);
        previousSpend = Number(tier.min_spend_amount || 0);
      });

      return [...new Set(messages)];
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
    defaultMembershipTiers() {
      return [
        { tier_key: 'standard', label: 'Thường', discount_percent: 0, min_completed_bookings: 0, min_spend_amount: 0, maintain_period_months: null, maintain_min_bookings: null, maintain_min_spend_amount: null },
        { tier_key: 'silver', label: 'Bạc', discount_percent: 3, min_completed_bookings: 5, min_spend_amount: 500000, maintain_period_months: null, maintain_min_bookings: null, maintain_min_spend_amount: null },
        { tier_key: 'gold', label: 'Vàng', discount_percent: 5, min_completed_bookings: 15, min_spend_amount: 2000000, maintain_period_months: null, maintain_min_bookings: null, maintain_min_spend_amount: null },
        { tier_key: 'diamond', label: 'Kim cương', discount_percent: 8, min_completed_bookings: 30, min_spend_amount: 5000000, maintain_period_months: null, maintain_min_bookings: null, maintain_min_spend_amount: null },
      ];
    },
    defaultForm() {
      return {
        min_duration_minutes: 30,
        max_duration_minutes: null,
        min_advance_booking_minutes: 30,
        fixed_open_time: '08:00',
        fixed_close_time: '22:00',
        special_operating_hours: [],
        slot_hold_minutes: 20,
        reminder_before_minutes: 30,
        allow_full_payment: true,
        allow_deposit: true,
        allow_no_prepay: true,
        deposit_percent: 30,
        membership_tiers: this.defaultMembershipTiers(),
      };
    },
    normalizeTime(time, fallback) {
      return time ? String(time).slice(0, 5) : fallback;
    },
    specialKey() {
      return window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;
    },
    validOperatingRange(openTime, closeTime) {
      const duration = this.timeToMinutes(closeTime) - this.timeToMinutes(openTime);
      return duration >= 120 && duration <= 1440;
    },
    validOpenTime(time) {
      return /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(String(time || ''));
    },
    validCloseTime(time) {
      return /^(?:(?:[01]\d|2[0-3]):[0-5]\d|24:00)$/.test(String(time || ''));
    },
    timeToMinutes(time) {
      const [hour, minute] = String(time || '00:00').split(':').map(Number);
      return hour * 60 + minute;
    },
    addSpecialHours() {
      this.form.special_operating_hours.push({
        _key: this.specialKey(),
        _touched: false,
        start_date: '',
        end_date: '',
        open_time: '08:00',
        close_time: '22:00',
      });
      this.notice = '';
    },
    removeSpecialHours(index) {
      this.form.special_operating_hours.splice(index, 1);
      this.notice = '';
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
      if (!config) {
        this.form = this.defaultForm();
      } else {
        this.form = {
          min_duration_minutes: Number(config.min_duration_minutes),
          max_duration_minutes: config.max_duration_minutes === null ? null : Number(config.max_duration_minutes),
          min_advance_booking_minutes: Number(config.min_advance_booking_minutes ?? 30),
          fixed_open_time: this.normalizeTime(
            config.fixed_open_time || config.weekly_operating_hours?.find((hours) => hours.is_open)?.open_time,
            '08:00',
          ),
          fixed_close_time: this.normalizeTime(
            config.fixed_close_time || config.weekly_operating_hours?.find((hours) => hours.is_open)?.close_time,
            '22:00',
          ),
          special_operating_hours: (config.special_operating_hours || []).map((hours) => ({
            _key: this.specialKey(),
            _touched: false,
            start_date: hours.start_date,
            end_date: hours.end_date,
            open_time: this.normalizeTime(hours.open_time, '08:00'),
            close_time: this.normalizeTime(hours.close_time, '22:00'),
          })),
          slot_hold_minutes: Number(config.slot_hold_minutes),
          reminder_before_minutes: Number(config.reminder_before_minutes),
          allow_full_payment: Boolean(config.allow_full_payment),
          allow_deposit: Boolean(config.allow_deposit),
          allow_no_prepay: Boolean(config.allow_no_prepay),
          deposit_percent: Number(config.deposit_percent || 30),
          membership_tiers: this.normalizeMembershipTiers(config.membership_tiers),
        };
      }
      this.error = '';
      this.notice = '';
      this.validationAttempted = false;
    },
    async save() {
      if (!this.selectedClusterId) return;
      this.validationAttempted = true;
      if (this.validationMessages.length) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
      }
      this.saving = true;
      this.error = '';
      this.notice = '';
      try {
        const response = await ownerBookingConfigService.update(this.selectedClusterId, {
          ...this.form,
          max_duration_minutes: this.form.max_duration_minutes || null,
          deposit_percent: this.form.allow_deposit ? this.form.deposit_percent : null,
          membership_tiers: this.form.membership_tiers.map((tier) => ({
            tier_key: tier.tier_key,
            discount_percent: Number(tier.discount_percent || 0),
            min_completed_bookings: Number(tier.min_completed_bookings || 0),
            min_spend_amount: Number(tier.min_spend_amount || 0),
            maintain_period_months: tier.maintain_period_months || null,
            maintain_min_bookings: tier.maintain_min_bookings ?? null,
            maintain_min_spend_amount: tier.maintain_min_spend_amount ?? null,
          })),
          special_operating_hours: this.form.special_operating_hours
            .map(({ _key, _touched, ...hours }) => hours)
            .sort((a, b) => a.start_date.localeCompare(b.start_date)),
        });
        const cluster = this.clusters.find((item) => item.id === this.selectedClusterId);
        if (cluster) cluster.booking_config = response.data;
        this.notice = response.message;
        this.syncForm();
        this.notice = response.message;
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } catch (error) {
        this.error = error.message || 'Không thể lưu cấu hình đặt sân.';
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } finally {
        this.saving = false;
      }
    },
    normalizeMembershipTiers(tiers = []) {
      const byKey = Object.fromEntries((tiers || []).map((tier) => [tier.tier_key || tier.tier || tier.key, tier]));
      return this.defaultMembershipTiers().map((fallback) => {
        const source = byKey[fallback.tier_key] || {};
        return {
          ...fallback,
          label: source.label || source.tier_label || fallback.label,
          discount_percent: Number(source.discount_percent ?? fallback.discount_percent),
          min_completed_bookings: Number(source.min_completed_bookings ?? source.min_bookings ?? fallback.min_completed_bookings),
          min_spend_amount: Number(source.min_spend_amount ?? source.min_spent_amount ?? fallback.min_spend_amount),
          maintain_period_months: source.maintain_period_months ?? null,
          maintain_min_bookings: source.maintain_min_bookings ?? null,
          maintain_min_spend_amount: source.maintain_min_spend_amount ?? source.maintain_min_spent ?? null,
        };
      });
    },
  },
};
</script>

<style scoped>
.settings-page{display:grid;gap:14px;max-width:1120px}.page-head,.card-head,.save-bar{display:flex;justify-content:space-between;align-items:center;gap:16px}.page-head h2,.card-head h3{margin:0;color:#0f172a}.card-head h3{font-size:16px}.eyebrow{margin:0 0 5px;color:#059669;font-size:11px;font-weight:900;letter-spacing:.1em}.cluster-select{min-width:260px;display:grid;gap:6px;color:#475569;font-size:12px;font-weight:850}.cluster-select select,.fixed-hours input,.special-row input,.special-row select,.input-unit input,.deposit-field input,.membership-row input{width:100%;height:40px;border:1px solid #cbd5e1;border-radius:9px;padding:0 10px;background:#fff;color:#0f172a;font:inherit}.alert,.state-card,.validation-summary{padding:12px 14px;border-radius:10px;font-weight:750}.alert.error,.validation-summary{background:#fff1f2;color:#9f1239;border:1px solid #fecdd3}.alert.success{background:#dcfce7;color:#166534}.state-card{text-align:center;background:#fff;border:1px solid #e2e8f0;color:#64748b}.settings-form{display:grid;gap:12px}.validation-summary{box-shadow:0 8px 24px rgba(159,18,57,.1)}.validation-summary ul{margin:6px 0 0;padding-left:20px;font-size:13px;font-weight:650}.setting-card,.save-bar{padding:15px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;box-shadow:0 5px 18px rgba(15,23,42,.035)}.card-head{padding-bottom:11px;border-bottom:1px solid #e2e8f0}.fixed-hours{display:grid;grid-template-columns:minmax(180px,1fr) auto minmax(180px,1fr) minmax(180px,1fr);align-items:end;gap:14px;margin-top:14px}.fixed-hours label,.special-row label,.compact-fields label{display:grid;gap:5px;color:#475569;font-size:12px;font-weight:800}.range-arrow{padding-bottom:10px;color:#94a3b8;font-size:20px}.secondary-btn,.remove-btn,.primary-btn{border:0;border-radius:9px;font:inherit;font-weight:850;cursor:pointer}.secondary-btn{padding:8px 11px;background:#ecfdf5;color:#047857}.empty-row{padding:18px 0 4px;text-align:center;color:#94a3b8;font-size:13px}.special-list{display:grid;gap:8px;margin-top:10px}.special-row{display:grid;grid-template-columns:1fr 1fr .8fr .8fr 36px;align-items:end;gap:9px;padding:10px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc}.remove-btn{height:40px;background:#fee2e2;color:#be123c;font-size:21px}.two-column{display:grid;grid-template-columns:1fr 1fr;gap:12px}.compact-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}.compact-fields label:first-child:last-child{grid-column:auto}.input-unit,.deposit-field{position:relative}.input-unit input{padding-right:50px}.input-unit>span,.deposit-field>span{position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#64748b;font-size:11px;font-weight:800}.payment-list{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}.payment-option{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:9px;min-height:52px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer}.payment-option.enabled{border-color:#6ee7b7;background:#ecfdf5}.payment-option>input{width:17px;height:17px;accent-color:#059669}.payment-option strong{color:#0f172a;font-size:13px}.deposit-field{width:82px}.deposit-field input{padding-right:26px}.membership-table{display:grid;gap:8px;margin-top:12px;overflow:auto}.membership-row{display:grid;grid-template-columns:120px repeat(6,minmax(110px,1fr));gap:8px;align-items:center;min-width:900px}.membership-row strong{color:#0f172a}.membership-head{color:#64748b;font-size:11px;font-weight:900;text-transform:uppercase}.save-bar{position:sticky;bottom:10px;border-color:#a7f3d0}.primary-btn{padding:10px 18px;background:#059669;color:#fff}.primary-btn:disabled{opacity:.5;cursor:not-allowed}@media(max-width:820px){.fixed-hours{grid-template-columns:1fr auto 1fr}.fixed-hours>label:last-child{grid-column:1/4}.two-column,.payment-list{grid-template-columns:1fr}.special-row{grid-template-columns:1fr 1fr}.remove-btn{grid-column:2;justify-self:end;width:40px}.page-head{align-items:end}}@media(max-width:560px){.page-head,.save-bar{display:grid}.cluster-select{min-width:0}.fixed-hours,.special-row,.compact-fields{grid-template-columns:1fr}.fixed-hours>label:last-child{grid-column:auto}.range-arrow{display:none}.remove-btn{grid-column:1}.primary-btn{width:100%}}
</style>
