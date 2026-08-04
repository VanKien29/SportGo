<template>
  <div class="cluster-profile-surface standalone">
    <!-- Global Alert Notifications -->
    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="loading" class="state-card">Đang tải cấu hình đặt sân...</div>
    <div v-else-if="!selectedClusterId" class="state-card">Chưa có cụm sân để cấu hình.</div>

    <!-- Main Unified Content Surface Card -->
    <form v-else class="profile-section-card settings-main-content" novalidate @submit.prevent="save">
      <div v-if="validationMessages.length" class="validation-summary" role="alert">
        <strong>Vui lòng kiểm tra lại</strong>
        <ul>
          <li v-for="message in validationMessages" :key="message">{{ message }}</li>
        </ul>
      </div>

      <!-- Section 1: Giờ hoạt động cố định -->
      <section class="setting-section">
        <header class="section-head">
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
              @input="normalizeTimeInput('fixed_open_time')"
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
              @input="normalizeTimeInput('fixed_close_time')"
            >
          </label>
          <label>
            <span>Thời lượng 1 booking</span>
            <div class="input-unit">
              <input v-model.trim="form.min_duration_minutes" type="text" inputmode="numeric" @input="normalizeIntegerInput('min_duration_minutes')">
              <span>phút</span>
            </div>
          </label>
        </div>
      </section>

      <!-- Section 1.5: Cấu hình các ca / khung giờ chơi -->
      <section class="setting-section">
        <header class="section-head split">
          <div>
            <h3>Cấu hình các ca / khung giờ chơi</h3>
            <p class="section-sub">Tùy chỉnh tên ca và khoảng giờ cho từng ca (Sáng, Chiều, Tối, Đêm, Khuya...) để chọn nhanh trên lịch.</p>
          </div>
          <div class="head-actions" style="display: flex; gap: 8px;">
            <button class="secondary-btn" type="button" @click="resetDefaultPeriods">Khôi phục mặc định</button>
            <button class="secondary-btn" type="button" @click="addCustomPeriod">+ Thêm ca</button>
          </div>
        </header>

        <div v-if="!form.custom_time_periods.length" class="empty-row">Đang sử dụng các ca chia tự động theo giờ mở/đóng cửa. Bấm "+ Thêm ca" hoặc "Khôi phục mặc định" để cấu hình.</div>
        <div v-else class="period-config-list">
          <div v-for="(period, index) in form.custom_time_periods" :key="period._key" class="period-config-row">
            <label class="period-label-field">
              <span>Tên ca</span>
              <input v-model.trim="period.label" type="text" placeholder="Ví dụ: Sáng / Ca 1" maxlength="50">
            </label>
            <label>
              <span>Giờ bắt đầu</span>
              <select v-model="period.start_time">
                <option v-for="time in openTimeOptions" :key="time" :value="time">{{ time }}</option>
              </select>
            </label>
            <span class="range-arrow">→</span>
            <label>
              <span>Giờ kết thúc</span>
              <select v-model="period.end_time">
                <option v-for="time in closeTimeOptions" :key="time" :value="time">{{ time }}</option>
              </select>
            </label>
            <button class="remove-btn" type="button" :aria-label="`Xóa ca ${index + 1}`" @click="removeCustomPeriod(index)">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle;">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>
        </div>
      </section>

      <!-- Section 2: Giờ hoạt động theo ngày -->
      <section class="setting-section">
        <header class="section-head split">
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
      </section>

      <!-- Section 3: Quy định thời gian & Giữ chỗ -->
      <section class="setting-section">
        <div class="two-column">
          <div class="compact-card-group">
            <header class="section-head"><h3>Đặt trước & giới hạn booking</h3></header>
            <div class="compact-fields">
              <label>
                <span>Đặt trước tối thiểu</span>
                <div class="input-unit">
                  <input v-model.trim="form.min_advance_booking_minutes" type="text" inputmode="numeric" @input="normalizeIntegerInput('min_advance_booking_minutes')">
                  <span>phút</span>
                </div>
              </label>
              <label>
                <span>Thời lượng tối đa</span>
                <div class="input-unit">
                  <input v-model.trim="form.max_duration_minutes" type="text" inputmode="numeric" placeholder="Không giới hạn" @input="normalizeIntegerInput('max_duration_minutes', true)">
                  <span>phút</span>
                </div>
              </label>
            </div>
          </div>

          <div class="compact-card-group">
            <header class="section-head"><h3>Giữ chỗ & nhắc lịch</h3></header>
            <div class="compact-fields">
              <label>
                <span>Thời gian giữ chỗ</span>
                <div class="input-unit">
                  <input v-model.trim="form.slot_hold_minutes" type="text" inputmode="numeric" @input="normalizeIntegerInput('slot_hold_minutes')">
                  <span>phút</span>
                </div>
              </label>
              <label>
                <span>Nhắc trước giờ chơi</span>
                <div class="input-unit">
                  <input v-model.trim="form.reminder_before_minutes" type="text" inputmode="numeric" @input="normalizeIntegerInput('reminder_before_minutes')">
                  <span>phút</span>
                </div>
              </label>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 4: Hình thức thanh toán -->
      <section class="setting-section">
        <header class="section-head"><h3>Hình thức thanh toán</h3></header>
        <div class="payment-list">
          <label class="payment-option" :class="{ enabled: form.allow_full_payment }">
            <input v-model="form.allow_full_payment" type="checkbox">
            <strong>Thanh toán đủ</strong>
          </label>
          <label class="payment-option" :class="{ enabled: form.allow_deposit }">
            <input v-model="form.allow_deposit" type="checkbox">
            <strong>Đặt cọc</strong>
            <div v-if="form.allow_deposit" class="deposit-field" @click.stop>
              <input v-model.trim="form.deposit_percent" type="text" inputmode="numeric" @input="normalizeIntegerInput('deposit_percent')">
              <span>%</span>
            </div>
          </label>
          <label class="payment-option" :class="{ enabled: form.allow_no_prepay }">
            <input v-model="form.allow_no_prepay" type="checkbox">
            <strong>Trả sau tại sân</strong>
          </label>
        </div>
      </section>

      <!-- Section 5: Hạng thành viên -->
      <section class="setting-section">
        <header class="section-head"><h3>Hạng thành viên</h3></header>
        <label class="membership-reset-toggle" :class="{ enabled: form.reset_membership_progress_on_upgrade }">
          <input v-model="form.reset_membership_progress_on_upgrade" type="checkbox">
          <span>
            <strong>Reset tiến độ sau khi lên hạng</strong>
            <small>
              {{ form.reset_membership_progress_on_upgrade ? 'Booking và chi tiêu sẽ về 0 cho mốc hạng tiếp theo.' : 'Booking và chi tiêu tiếp tục cộng dồn sau khi lên hạng.' }}
            </small>
          </span>
        </label>
        <div class="membership-table">
          <div class="membership-row membership-head">
            <span>Hạng</span>
            <span>Tên hiển thị</span>
            <span>Trạng thái</span>
            <span>Voucher đi kèm</span>
            <span>Giảm (%)</span>
            <span>Booking lên hạng</span>
            <span>Chi tiêu lên hạng (VNĐ)</span>
            <span>Kỳ duy trì/ tháng</span>
            <span>Số lượng Booking duy trì</span>
            <span>Chi tiêu duy trì (VNĐ)</span>
          </div>
          <div v-for="tier in form.membership_tiers" :key="tier.tier_key" class="membership-row">
            <strong>{{ tier.label }}</strong>
            <input v-model.trim="tier.tier_label" type="text" maxlength="80">
            <select v-model="tier.is_active" :disabled="tier.tier_key === 'standard'">
              <option :value="true">Bật</option>
              <option :value="false">Tắt</option>
            </select>
            <select v-model="tier.voucher_id">
              <option :value="null">Không gắn</option>
              <option v-for="voucher in membershipVoucherOptions" :key="voucher.id" :value="voucher.id">
                {{ voucher.code }} - {{ voucher.name }}
              </option>
            </select>
            <input v-model.trim="tier.discount_percent" type="text" inputmode="decimal">
            <input v-model.trim="tier.min_completed_bookings" type="text" inputmode="numeric">
            <input v-model.trim="tier.min_spend_amount" type="text" inputmode="decimal">
            <input v-model.trim="tier.maintain_period_months" type="text" inputmode="numeric" placeholder="Trống">
            <input v-model.trim="tier.maintain_min_bookings" type="text" inputmode="numeric" placeholder="Trống">
            <input v-model.trim="tier.maintain_min_spend_amount" type="text" inputmode="decimal" placeholder="Trống">
          </div>
        </div>
        <div v-if="membershipValidationMessages.length" class="membership-inline-errors" role="alert">
          <span v-for="message in membershipValidationMessages" :key="message">{{ message }}</span>
        </div>
      </section>

      <!-- Bottom Save Action Bar integrated inside container surface -->
      <footer class="save-bar-footer">
        <div class="save-bar-info">
          <span>Đang cấu hình cho cụm sân:</span>
          <strong>{{ selectedCluster?.name }}</strong>
        </div>
        <button class="primary-btn" type="submit" :disabled="saving">
          {{ saving ? 'Đang lưu...' : 'Lưu cấu hình' }}
        </button>
      </footer>
    </form>
  </div>
</template>

<script>
import { useToast } from 'vue-toastification';
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
    membershipVoucherOptions() {
      return this.selectedCluster?.booking_config?.membership_voucher_options || [];
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
      const minAdvance = this.integerInputValue(this.form.min_advance_booking_minutes);
      const minDuration = this.integerInputValue(this.form.min_duration_minutes);
      const maxDuration = this.nullableIntegerInputValue(this.form.max_duration_minutes);
      const slotHold = this.integerInputValue(this.form.slot_hold_minutes);
      const reminderBefore = this.integerInputValue(this.form.reminder_before_minutes);
      const depositPercent = this.integerInputValue(this.form.deposit_percent);

      if (!Number.isInteger(minAdvance) || minAdvance < 30) {
        messages.push('Thời gian đặt trước tối thiểu là 30 phút.');
      }
      if (!Number.isInteger(minDuration) || minDuration < 30 || minDuration > 120 || minDuration % 30 !== 0) {
        messages.push('Thời lượng tối thiểu phải từ 30 phút đến 2 giờ, theo bước 30 phút.');
      }
      if (maxDuration !== null && (!Number.isInteger(maxDuration) || maxDuration > 1440 || maxDuration % 30 !== 0 || maxDuration < minDuration)) {
        messages.push('Thời lượng tối đa phải từ mức tối thiểu đến 24 giờ, theo bước 30 phút.');
      }
      if (!Number.isInteger(slotHold) || slotHold < 5 || slotHold > 120 || slotHold % 5 !== 0) {
        messages.push('Thời gian giữ chỗ phải từ 5 đến 120 phút, theo bước 5 phút.');
      }
      if (!Number.isInteger(reminderBefore) || reminderBefore < 0 || reminderBefore > 10080 || reminderBefore % 5 !== 0) {
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
      if (this.form.allow_deposit && (!Number.isInteger(depositPercent) || depositPercent < 1 || depositPercent > 100)) {
        messages.push('Phần trăm cọc phải từ 1 đến 100.');
      }

      messages.push(...this.membershipValidationMessages);

      const tiers = this.form.membership_tiers || [];
      let previousBookings = -1;
      let previousSpend = -1;
      let previousDiscount = -1;
      const seenTierKeys = new Set();
      const seenConditions = new Set();

      if (tiers.length !== 4) {
        messages.push('Phải cấu hình đủ 4 hạng thành viên cố định.');
      }

      tiers.forEach((tier) => {
        if (seenTierKeys.has(tier.tier_key)) {
          messages.push('Không được cấu hình trùng hạng thành viên.');
        }
        seenTierKeys.add(tier.tier_key);

        const bookings = Number(tier.min_completed_bookings || 0);
        const spend = Number(tier.min_spend_amount || 0);
        const discount = Number(tier.discount_percent || 0);
        const conditionKey = `${bookings}|${spend.toFixed(2)}`;

        if (tier.discount_percent < 0 || tier.discount_percent > 100) {
          messages.push('Giảm giá hạng thành viên phải từ 0 đến 100%.');
        }
        if (tier.tier_key === 'standard' && (bookings !== 0 || spend !== 0)) {
          messages.push('Hạng Thường phải bắt đầu từ 0 booking và 0 đồng chi tiêu.');
        }
        if (seenConditions.has(conditionKey)) {
          messages.push('Không được cấu hình hai hạng trùng điều kiện lên hạng.');
        }
        if (bookings < previousBookings || spend < previousSpend) {
          messages.push('Điều kiện lên hạng sau không được thấp hơn hạng trước.');
        }
        if (previousBookings >= 0 && bookings === previousBookings && spend === previousSpend) {
          messages.push('Mốc lên hạng phải tăng thật sự, không được trùng điều kiện với hạng trước.');
        }
        if (discount < previousDiscount) {
          messages.push('Quyền lợi giảm giá của hạng cao hơn không được thấp hơn hạng trước.');
        }

        const hasMaintainPeriod = tier.maintain_period_months !== null && tier.maintain_period_months !== '';
        const hasMaintainCondition = (tier.maintain_min_bookings !== null && tier.maintain_min_bookings !== '')
          || (tier.maintain_min_spend_amount !== null && tier.maintain_min_spend_amount !== '');
        if (hasMaintainCondition && !hasMaintainPeriod) {
          messages.push('Nếu nhập điều kiện duy trì hạng thì phải nhập kỳ duy trì.');
        }
        if (hasMaintainPeriod && !hasMaintainCondition) {
          messages.push('Nếu nhập kỳ duy trì hạng thì phải nhập ít nhất một điều kiện duy trì.');
        }

        seenConditions.add(conditionKey);
        previousBookings = bookings;
        previousSpend = spend;
        previousDiscount = discount;
      });

      return [...new Set(messages)];
    },
    membershipValidationMessages() {
      if (!this.form) return [];

      const messages = [];
      const tiers = this.form.membership_tiers || [];
      const voucherIds = new Set(this.membershipVoucherOptions.map((voucher) => voucher.id));

      tiers.forEach((tier) => {
        const label = tier.label || tier.tier_label || tier.tier_key || 'Hạng';
        const discount = this.numberInputValue(tier.discount_percent);
        const bookings = this.numberInputValue(tier.min_completed_bookings);
        const spend = this.numberInputValue(tier.min_spend_amount);
        const maintainPeriod = this.numberInputValue(tier.maintain_period_months);
        const maintainBookings = this.numberInputValue(tier.maintain_min_bookings);
        const maintainSpend = this.numberInputValue(tier.maintain_min_spend_amount);

        if (!String(tier.tier_label || '').trim()) {
          messages.push(`${label}: Tên hiển thị không được để trống.`);
        }
        if (tier.tier_key === 'standard' && !tier.is_active) {
          messages.push('Hạng Thường luôn phải được kích hoạt.');
        }
        if (tier.voucher_id && !voucherIds.has(tier.voucher_id)) {
          messages.push(`${label}: Voucher đi kèm không hợp lệ hoặc không còn kích hoạt.`);
        }
        if (!Number.isFinite(discount) || discount < 0 || discount > 100) {
          messages.push(`${label}: Giảm giá phải là số từ 0 đến 100%.`);
        }
        if (!Number.isInteger(bookings) || bookings < 0) {
          messages.push(`${label}: Booking lên hạng phải là số nguyên không âm, ví dụ nhập 6 hoặc 7.`);
        }
        if (!Number.isFinite(spend) || spend < 0) {
          messages.push(`${label}: Chi tiêu lên hạng phải là số không âm.`);
        }
        if (!this.isBlank(tier.maintain_period_months) && (!Number.isInteger(maintainPeriod) || maintainPeriod < 1 || maintainPeriod > 36)) {
          messages.push(`${label}: Kỳ duy trì phải là số nguyên từ 1 đến 36 tháng.`);
        }
        if (!this.isBlank(tier.maintain_min_bookings) && (!Number.isInteger(maintainBookings) || maintainBookings < 0)) {
          messages.push(`${label}: Số lượng booking duy trì phải là số nguyên không âm.`);
        }
        if (!this.isBlank(tier.maintain_min_spend_amount) && (!Number.isFinite(maintainSpend) || maintainSpend < 0)) {
          messages.push(`${label}: Chi tiêu duy trì phải là số không âm và không phải chữ.`);
        }
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
    isBlank(value) {
      return value === null || value === undefined || String(value).trim() === '';
    },
    normalizeIntegerInput(field, allowBlank = false) {
      const digits = String(this.form[field] ?? '').replace(/\D/g, '');
      this.form[field] = allowBlank && digits === '' ? '' : digits;
    },
    normalizeTimeInput(field) {
      const digits = String(this.form[field] ?? '').replace(/\D/g, '').slice(0, 4);
      if (digits.length <= 2) {
        this.form[field] = digits;
        return;
      }
      this.form[field] = `${digits.slice(0, 2)}:${digits.slice(2)}`;
    },
    integerInputValue(value) {
      if (this.isBlank(value)) return NaN;
      if (typeof value === 'number') return Number.isInteger(value) ? value : NaN;
      const normalized = String(value).trim();
      return /^\d+$/.test(normalized) ? Number(normalized) : NaN;
    },
    nullableIntegerInputValue(value) {
      return this.isBlank(value) ? null : this.integerInputValue(value);
    },
    numberInputValue(value) {
      if (this.isBlank(value)) return NaN;
      if (typeof value === 'number') return value;

      const normalized = String(value).trim().replace(',', '.');
      if (!/^-?\d+(?:\.\d+)?$/.test(normalized)) return NaN;

      return Number(normalized);
    },
    nullableNumberInputValue(value) {
      return this.isBlank(value) ? null : this.numberInputValue(value);
    },
    defaultMembershipTiers() {
      return [
        { tier_key: 'standard', label: 'Thường', tier_label: 'Thường', is_active: true, voucher_id: null, discount_percent: 0, min_completed_bookings: 0, min_spend_amount: 0, maintain_period_months: null, maintain_min_bookings: null, maintain_min_spend_amount: null },
        { tier_key: 'silver', label: 'Bạc', tier_label: 'Bạc', is_active: true, voucher_id: null, discount_percent: 3, min_completed_bookings: 5, min_spend_amount: 500000, maintain_period_months: null, maintain_min_bookings: null, maintain_min_spend_amount: null },
        { tier_key: 'gold', label: 'Vàng', tier_label: 'Vàng', is_active: true, voucher_id: null, discount_percent: 5, min_completed_bookings: 15, min_spend_amount: 2000000, maintain_period_months: null, maintain_min_bookings: null, maintain_min_spend_amount: null },
        { tier_key: 'diamond', label: 'Kim cương', tier_label: 'Kim cương', is_active: true, voucher_id: null, discount_percent: 8, min_completed_bookings: 30, min_spend_amount: 5000000, maintain_period_months: null, maintain_min_bookings: null, maintain_min_spend_amount: null },
      ];
    },
    defaultCustomPeriods() {
      return [
        { _key: this.specialKey(), label: 'Khuya', start_time: '00:00', end_time: '06:00' },
        { _key: this.specialKey(), label: 'Sáng', start_time: '06:00', end_time: '12:00' },
        { _key: this.specialKey(), label: 'Chiều', start_time: '12:00', end_time: '18:00' },
        { _key: this.specialKey(), label: 'Tối', start_time: '18:00', end_time: '22:00' },
        { _key: this.specialKey(), label: 'Đêm', start_time: '22:00', end_time: '24:00' },
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
        custom_time_periods: [],
        slot_hold_minutes: 20,
        reminder_before_minutes: 30,
        allow_full_payment: true,
        allow_deposit: true,
        allow_no_prepay: true,
        deposit_percent: 30,
        reset_membership_progress_on_upgrade: false,
        membership_tiers: this.defaultMembershipTiers(),
      };
    },
    normalizeTime(time, fallback) {
      return time ? String(time).slice(0, 5) : fallback;
    },
    specialKey() {
      return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    },
    addCustomPeriod() {
      this.form.custom_time_periods.push({
        _key: this.specialKey(),
        label: '',
        start_time: '08:00',
        end_time: '12:00',
      });
      this.notice = '';
    },
    removeCustomPeriod(index) {
      this.form.custom_time_periods.splice(index, 1);
      this.notice = '';
    },
    resetDefaultPeriods() {
      this.form.custom_time_periods = this.defaultCustomPeriods();
      this.notice = '';
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
          custom_time_periods: (config.custom_time_periods && config.custom_time_periods.length)
            ? config.custom_time_periods.map((p) => ({
                _key: this.specialKey(),
                label: p.label || '',
                start_time: this.normalizeTime(p.start_time, '08:00'),
                end_time: this.normalizeTime(p.end_time, '12:00'),
              }))
            : [],
          slot_hold_minutes: Number(config.slot_hold_minutes),
          reminder_before_minutes: Number(config.reminder_before_minutes),
          allow_full_payment: Boolean(config.allow_full_payment),
          allow_deposit: Boolean(config.allow_deposit),
          allow_no_prepay: Boolean(config.allow_no_prepay),
          deposit_percent: Number(config.deposit_percent || 30),
          reset_membership_progress_on_upgrade: Boolean(config.reset_membership_progress_on_upgrade),
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
          min_duration_minutes: this.integerInputValue(this.form.min_duration_minutes),
          max_duration_minutes: this.nullableIntegerInputValue(this.form.max_duration_minutes),
          min_advance_booking_minutes: this.integerInputValue(this.form.min_advance_booking_minutes),
          slot_hold_minutes: this.integerInputValue(this.form.slot_hold_minutes),
          reminder_before_minutes: this.integerInputValue(this.form.reminder_before_minutes),
          deposit_percent: this.form.allow_deposit ? this.integerInputValue(this.form.deposit_percent) : null,
          membership_tiers: this.form.membership_tiers.map((tier) => ({
            tier_key: tier.tier_key,
            tier_label: tier.tier_label || tier.label,
            is_active: tier.tier_key === 'standard' ? true : Boolean(tier.is_active),
            voucher_id: tier.voucher_id || null,
            discount_percent: this.numberInputValue(tier.discount_percent),
            min_completed_bookings: this.numberInputValue(tier.min_completed_bookings),
            min_spend_amount: this.numberInputValue(tier.min_spend_amount),
            maintain_period_months: this.nullableNumberInputValue(tier.maintain_period_months),
            maintain_min_bookings: this.nullableNumberInputValue(tier.maintain_min_bookings),
            maintain_min_spend_amount: this.nullableNumberInputValue(tier.maintain_min_spend_amount),
          })),
          special_operating_hours: this.form.special_operating_hours
            .map(({ _key, _touched, ...hours }) => hours)
            .sort((a, b) => a.start_date.localeCompare(b.start_date)),
          custom_time_periods: (this.form.custom_time_periods || [])
            .filter((p) => p.label && p.start_time && p.end_time)
            .map(({ _key, ...p }) => ({
              label: p.label.trim(),
              start_time: p.start_time,
              end_time: p.end_time,
            })),
        });
        const cluster = this.clusters.find((item) => item.id === this.selectedClusterId);
        if (cluster) cluster.booking_config = response.data;
        this.syncForm();
        const toast = useToast();
        toast.success(response.message || 'Đã lưu cấu hình đặt sân thành công!');
      } catch (error) {
        const toast = useToast();
        toast.error(error.message || 'Không thể lưu cấu hình đặt sân.');
      } finally {
        this.saving = false;
      }
    },
    normalizeMembershipTiers(tiers = []) {
      const byKey = Object.fromEntries((tiers || []).map((tier) => [tier.tier_key || tier.tier || tier.key, tier]));
      return this.defaultMembershipTiers().map((fallback) => {
        const source = byKey[fallback.tier_key] || {};
        const isActive = fallback.tier_key === 'standard'
          ? true
          : (source.is_active === undefined ? true : Boolean(source.is_active));
        return {
          ...fallback,
          label: source.label || source.tier_label || fallback.label,
          tier_label: source.tier_label || source.label || fallback.tier_label || fallback.label,
          is_active: isActive,
          voucher_id: source.voucher_id || null,
          voucher: source.voucher || null,
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
.cluster-profile-surface.standalone {
  width: 100%;
  min-width: 0;
  background: var(--admin-surface, #ffffff);
  border-radius: 0;
  border: none;
  box-shadow: none;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.settings-header-surface {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 20px 24px;
  background: var(--admin-surface, #ffffff);
}

.header-title-group h2,
.section-head h3 {
  margin: 0;
  color: var(--admin-text, #0f172a);
}

.header-title-group h2 {
  font-size: 20px;
  font-weight: 500;
}

.section-head h3 {
  font-size: 16px;
  font-weight: 500;
}

.eyebrow {
  margin: 0 0 4px;
  color: #059669;
  font-size: 11px;
  font-weight: 400;
  letter-spacing: 0.1em;
}

.cluster-select {
  min-width: 260px;
  display: grid;
  gap: 6px;
  color: var(--admin-muted, #475569);
  font-size: 12px;
  font-weight: 400;
}

.cluster-select select,
.fixed-hours input,
.special-row input,
.special-row select,
.input-unit input,
.deposit-field input,
.membership-row input,
.membership-row select {
  width: 100%;
  height: 40px;
  border: 1px solid var(--admin-border, #cbd5e1);
  border-radius: 9px;
  padding: 0 10px;
  background: var(--admin-surface, #fff);
  color: var(--admin-text, #0f172a);
  font: inherit;
}

.alert,
.state-card,
.validation-summary {
  padding: 12px 14px;
  border-radius: 10px;
  font-weight: 400;
}

.alert.error,
.validation-summary {
  background: #fff1f2;
  color: #9f1239;
  border: 1px solid #fecdd3;
  margin: 16px 24px 0;
}

.alert.success {
  background: #dcfce7;
  color: #166534;
  margin: 16px 24px 0;
}

.state-card {
  text-align: center;
  background: var(--admin-surface, #fff);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  color: var(--admin-muted, #64748b);
  margin: 24px;
}

.profile-section-card.settings-main-content {
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 24px;
  min-width: 0;
  max-width: 100%;
  background: var(--admin-surface, #ffffff);
}

.validation-summary {
  box-shadow: 0 8px 24px rgba(159, 18, 57, 0.1);
  margin: 0;
}

.validation-summary ul {
  margin: 6px 0 0;
  padding-left: 20px;
  font-size: 13px;
  font-weight: 400;
}

.setting-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.section-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 0 !important;
  border-bottom: none !important;
}

.section-divider {
  height: 1px;
  background: var(--admin-border-soft, #e2e8f0);
}

.fixed-hours {
  display: grid;
  grid-template-columns: minmax(180px, 1fr) auto minmax(180px, 1fr) minmax(180px, 1fr);
  align-items: end;
  gap: 14px;
}

.fixed-hours label,
.special-row label,
.period-config-row label,
.compact-fields label {
  display: grid;
  gap: 5px;
  color: var(--admin-muted, #475569);
  font-size: 12px;
  font-weight: 400;
}

.range-arrow {
  padding-bottom: 10px;
  color: var(--admin-muted, #94a3b8);
  font-size: 20px;
}

.secondary-btn,
.remove-btn,
.primary-btn {
  border: 0;
  border-radius: 9px;
  font: inherit;
  font-weight: 400;
  cursor: pointer;
}

.secondary-btn {
  padding: 8px 14px;
  background: #ecfdf5;
  color: #047857;
  font-size: 13px;
  font-weight: 500;
}

.empty-row {
  padding: 18px 0 4px;
  text-align: center;
  color: var(--admin-muted, #94a3b8);
  font-size: 13px;
}

.special-list {
  display: grid;
  gap: 10px;
}

.special-row {
  display: grid;
  grid-template-columns: 1fr 1fr 0.8fr 0.8fr 36px;
  align-items: end;
  gap: 10px;
  padding: 12px;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 10px;
  background: var(--admin-surface-muted, #f8fafc);
}

.period-config-list {
  display: grid;
  gap: 10px;
}

.period-config-row {
  display: grid;
  grid-template-columns: 1.5fr 1fr auto 1fr 36px;
  align-items: end;
  gap: 10px;
  padding: 12px;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 10px;
  background: var(--admin-surface-muted, #f8fafc);
}

.period-config-row .range-arrow {
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding-bottom: 0;
}

.remove-btn {
  width: 36px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border-radius: 9px;
  background: #fee2e2;
  color: #be123c;
  font-size: 16px;
  transition: background 0.15s, color 0.15s;
}

.remove-btn:hover {
  background: #fecdd3;
  color: #9f1239;
}

.two-column {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.compact-card-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.compact-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.compact-fields label:first-child:last-child {
  grid-column: auto;
}

.input-unit,
.deposit-field {
  position: relative;
}

.input-unit input {
  padding-right: 50px;
}

.input-unit > span,
.deposit-field > span {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--admin-muted, #64748b);
  font-size: 11px;
  font-weight: 400;
}

.payment-list {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.payment-option {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 9px;
  min-height: 52px;
  padding: 10px 14px;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 10px;
  cursor: pointer;
  background: var(--admin-surface, #fff);
  transition: all 0.15s ease;
}

.payment-option.enabled {
  border-color: #6ee7b7;
  background: #ecfdf5;
}

.payment-option > input {
  width: 17px;
  height: 17px;
  accent-color: #059669;
}

.payment-option strong {
  color: var(--admin-text, #0f172a);
  font-size: 13px;
}

.deposit-field {
  width: 82px;
}

.deposit-field input {
  padding-right: 26px;
}

.membership-reset-toggle {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 12px;
  align-items: center;
  padding: 14px;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 10px;
  background: var(--admin-surface-muted, #f8fafc);
  cursor: pointer;
  transition: all 0.15s ease;
}

.membership-reset-toggle.enabled {
  border-color: #6ee7b7;
  background: #ecfdf5;
}

.membership-reset-toggle input {
  width: 18px;
  height: 18px;
  accent-color: #059669;
}

.membership-reset-toggle span {
  display: grid;
  gap: 3px;
}

.membership-reset-toggle strong {
  color: var(--admin-text, #0f172a);
  font-size: 13px;
}

.membership-reset-toggle small {
  color: var(--admin-muted, #64748b);
  font-size: 12px;
  font-weight: 400;
}

.membership-table {
  display: grid;
  gap: 8px;
  overflow: auto;
}

.membership-row {
  display: grid;
  grid-template-columns: 110px 150px 100px minmax(180px, 1.2fr) repeat(6, minmax(110px, 1fr));
  gap: 8px;
  align-items: center;
  min-width: 1280px;
}

.membership-row strong {
  color: var(--admin-text, #0f172a);
}

.membership-head {
  color: var(--admin-muted, #64748b);
  font-size: 11px;
  font-weight: 400;
  text-transform: uppercase;
}

.membership-inline-errors {
  display: grid;
  gap: 6px;
  padding: 10px 12px;
  border: 1px solid #fed7aa;
  border-radius: 9px;
  background: #fff7ed;
  color: #9a3412;
  font-size: 12px;
  font-weight: 400;
}

.save-bar-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 14px 20px;
  margin-top: 8px;
  border-radius: 10px;
  border: 1px solid #a7f3d0;
  background: #ecfdf5;
  position: sticky;
  bottom: 12px;
  z-index: 10;
  box-shadow: 0 4px 14px rgba(5, 150, 105, 0.08);
}

.save-bar-info {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--admin-text, #0f172a);
}

.save-bar-info span {
  color: var(--admin-muted, #64748b);
}

.primary-btn {
  padding: 10px 22px;
  background: #059669;
  color: #fff;
  font-weight: 500;
  font-size: 14px;

  transition: background 0.15s ease;
}

.primary-btn:hover:not(:disabled) {
  background: #047857;
}

.primary-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 820px) {
  .fixed-hours {
    grid-template-columns: 1fr auto 1fr;
  }
  .fixed-hours > label:last-child {
    grid-column: 1 / 4;
  }
  .two-column,
  .payment-list {
    grid-template-columns: 1fr;
  }
  .special-row {
    grid-template-columns: 1fr 1fr;
  }
  .remove-btn {
    grid-column: 2;
    justify-self: end;
    width: 40px;
  }
  .settings-header-surface {
    flex-direction: column;
    align-items: flex-start;
  }
}

@media (max-width: 560px) {
  .settings-header-surface,
  .save-bar-footer {
    display: grid;
  }
  .cluster-select {
    min-width: 0;
  }
  .fixed-hours,
  .special-row,
  .compact-fields {
    grid-template-columns: 1fr;
  }
  .fixed-hours > label:last-child {
    grid-column: auto;
  }
  .range-arrow {
    display: none;
  }
  .remove-btn {
    grid-column: 1;
  }
  .primary-btn {
    width: 100%;
  }
}
</style>
