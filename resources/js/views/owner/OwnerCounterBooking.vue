<template>
  <div class="owner-counter-page">
    <section class="page-head">
      <div>
        <h1>Booking tại quầy</h1>
        <p>Quản lý lịch sân trong ngày, tạo booking vãng lai và lịch cố định cho khách quen.</p>
      </div>
      <router-link class="secondary-btn" :to="{ name: 'owner-bookings' }">
        <AppIcon name="calendar" size="16" />
        <span>Lịch sân</span>
      </router-link>
    </section>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="notice" class="alert success">{{ notice }}</div>

    <div class="tabs">
      <button type="button" :class="{ active: activeTab === 'counter' }" @click="activeTab = 'counter'">
        <AppIcon name="plus" size="16" />
        <span>Booking tại quầy</span>
      </button>
      <button type="button" :class="{ active: activeTab === 'recurring' }" @click="activeTab = 'recurring'">
        <AppIcon name="calendar" size="16" />
        <span>Đặt lịch cố định</span>
      </button>
    </div>

    <section v-if="activeTab === 'counter'" class="counter-board">
      <div class="schedule-panel">
        <div class="panel-head compact">
          <div>
            <h2>Lịch sân trong ngày</h2>
            <p>{{ currentScheduleLabel }}</p>
          </div>
          <button class="icon-btn" type="button" title="Tải lại lịch" @click="loadSchedule">
            <AppIcon name="refresh" size="17" />
          </button>
        </div>

        <div class="filters schedule-filters">
          <label>
            <span>Cụm sân</span>
            <select v-model="selectedClusterId" @change="handleClusterChange">
              <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">{{ cluster.name }}</option>
            </select>
          </label>
          <label>
            <span>Ngày chơi</span>
            <input v-model="form.booking_date" type="date" :min="today" @change="loadSchedule" />
          </label>
          <label>
            <span>Loại sân</span>
            <select v-model="selectedCourtTypeId" @change="loadSchedule">
              <option value="">Tất cả</option>
              <option v-for="type in courtTypeOptions" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
          </label>
        </div>

        <div class="booking-picker">
          <label class="court-field">
            <span>Sân con</span>
            <select v-model="selectedGridCourtId" @change="handleCounterCourtChange">
              <option value="">Chọn sân</option>
              <option v-for="court in scheduleCourts" :key="court.id" :value="court.id">
                {{ court.name }} · {{ court.court_type?.name || '-' }}
              </option>
            </select>
          </label>
          <div class="selection-help">
            <span>Cách chọn</span>
            <strong>Bấm từng ô 30 phút</strong>
            <small>Có thể chọn nhiều khoảng cách nhau trong cùng một sân.</small>
          </div>
          <div class="duration-pill" :class="{ active: hasCounterSelection }">
            <span>Đã chọn</span>
            <strong>{{ selectedDurationText }}</strong>
          </div>
        </div>

        <div class="legend">
          <span><i></i>Trống</span>
          <span><i class="selected"></i>Đang chọn</span>
          <span><i class="pending"></i>Chờ thanh toán / giữ chỗ</span>
          <span><i class="busy"></i>Không khả dụng</span>
        </div>

        <p v-if="selectionError" class="selection-error">{{ selectionError }}</p>

        <div v-if="scheduleLoading" class="state-card">Đang tải lịch sân...</div>
        <div v-else-if="scheduleError" class="state-card error-state">{{ scheduleError }}</div>
        <div v-else-if="!selectedCounterCourt" class="state-card">Chọn sân con để xem khung giờ còn trống.</div>
        <div v-else class="time-board">
          <div class="selected-court-strip">
            <div>
              <span>Đang chọn sân</span>
              <strong>{{ selectedCounterCourt.name }}</strong>
            </div>
            <div>
              <span>Loại sân</span>
              <strong>{{ selectedCounterCourt.court_type?.name || '-' }}</strong>
            </div>
            <div>
              <span>Khung giờ</span>
              <strong>{{ hasCounterSelection ? selectedTimeText : '06:00 - 22:00' }}</strong>
            </div>
          </div>

          <div class="slot-matrix" role="grid" aria-label="Bảng chọn khung giờ">
            <div v-for="period in slotPeriods" :key="period.key" class="period-row" role="row">
              <div class="period-label" role="rowheader">
                <strong>{{ period.label }}</strong>
                <span>{{ period.range }}</span>
              </div>
              <div class="period-slots" role="presentation">
                <button
                  v-for="slot in period.slots"
                  :key="slot.start_time"
                  type="button"
                  class="time-slot"
                  role="gridcell"
                  :aria-pressed="isSlotSelected(slot)"
                  :aria-label="slotActionTitle(slot)"
                  :class="slotButtonClass(slot)"
                  :disabled="isSlotDisabled(slot)"
                  :title="slotActionTitle(slot)"
                  @click="toggleSlot(slot)"
                ></button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <aside class="booking-side">
        <section class="side-section">
          <div class="section-title muted">
            <h2>Thông tin booking</h2>
          </div>
          <div v-if="!hasCounterSelection" class="empty-summary">Chưa có khung giờ được chọn.</div>
          <dl v-else class="summary-list">
            <div v-for="[label, value] in counterSummaryRows" :key="label">
              <dt>{{ label }}</dt>
              <dd>{{ value }}</dd>
            </div>
          </dl>
        </section>

        <section class="side-section" :class="{ disabled: !hasCounterSelection }">
          <div class="section-title muted">
            <h2>Khách hàng</h2>
          </div>
          <label>
            <span>Tên khách</span>
            <input v-model.trim="form.walk_in_name" type="text" placeholder="Nguyễn Văn A" />
          </label>
          <label>
            <span>Số điện thoại</span>
            <input v-model.trim="form.walk_in_phone" type="tel" placeholder="0901234567" />
          </label>
        </section>

        <section class="side-section" :class="{ disabled: !hasCounterSelection }">
          <div class="section-title muted">
            <h2>Thu tiền</h2>
          </div>
          <div class="payment-list">
            <label
              v-for="option in counterCollectionOptions"
              :key="option.value"
              class="payment-card"
              :class="{ active: form.collection_mode === option.value }"
            >
              <input v-model="form.collection_mode" type="radio" :value="option.value" @change="applyCounterCollectionMode" />
              <span>
                {{ option.label }}
                <small>{{ option.description }}</small>
              </span>
              <strong>{{ formatCurrency(option.amount) }}</strong>
            </label>
          </div>

          <div v-if="form.collection_mode === 'transfer'" class="inline-note">
            Thông tin chuyển khoản sẽ được tạo sau khi booking được giữ chỗ. Hệ thống tự cập nhật khi nhận được thanh toán.
          </div>
          <div v-if="form.collection_mode === 'later'" class="inline-note">
            Booking được giữ chỗ ngay, tiền sẽ thu sau khi khách chơi xong.
          </div>
        </section>

        <button class="primary-btn full" type="button" :disabled="submitting || !canSubmitCounter" @click="submitCounter">
          <AppIcon name="plus" size="16" />
          <span>{{ submitting ? 'Đang tạo...' : 'Tạo booking' }}</span>
        </button>

        <section v-if="counterQr" class="side-section qr-section">
          <div class="section-title muted">
            <h2>Thông tin chuyển khoản</h2>
          </div>
          <img :src="counterQr.qr_url" alt="Mã chuyển khoản" />
          <div class="qr-info">
            <div>
              <span>Nội dung</span>
              <button type="button" @click="copyText(counterQr.transfer_content)">{{ counterQr.transfer_content }}</button>
            </div>
            <div>
              <span>Số tiền</span>
              <strong>{{ formatCurrency(counterQr.payment?.amount) }}</strong>
            </div>
            <div>
              <span>Tài khoản</span>
              <strong>{{ counterQr.payment_account?.account_number || '-' }}</strong>
            </div>
          </div>
        </section>
      </aside>
    </section>

    <section v-else class="recurring-panel">
      <div class="form-card">
        <div class="panel-head compact">
          <div>
            <h2>Lịch cố định</h2>
            <p>Nhóm lịch sẽ dùng cùng mã cố định để dễ theo dõi.</p>
          </div>
        </div>

        <div class="form-grid">
          <label>
            <span>Cụm sân</span>
            <select v-model="selectedClusterId" @change="handleClusterChange">
              <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">{{ cluster.name }}</option>
            </select>
          </label>
          <label>
            <span>Sân con</span>
            <select v-model="form.venue_court_id" required>
              <option v-for="court in courts" :key="court.id" :value="court.id">
                {{ court.name }} · {{ court.court_type?.name || '-' }}
              </option>
            </select>
          </label>
          <label>
            <span>Tên khách</span>
            <input v-model.trim="form.walk_in_name" type="text" placeholder="Nguyễn Văn A" />
          </label>
          <label>
            <span>Số điện thoại</span>
            <input v-model.trim="form.walk_in_phone" type="tel" placeholder="0901234567" />
          </label>
          <label>
            <span>Từ ngày</span>
            <input v-model="form.recurring_start_date" type="date" :min="today" />
          </label>
          <label>
            <span>Đến ngày</span>
            <input v-model="form.recurring_end_date" type="date" :min="form.recurring_start_date || today" />
          </label>
          <label>
            <span>Bắt đầu</span>
            <input v-model="form.start_time" type="time" min="06:00" max="21:30" step="1800" @change="normalizeRecurringTime" />
          </label>
          <label>
            <span>Kết thúc</span>
            <input v-model="form.end_time" type="time" min="06:30" max="22:00" step="1800" @change="normalizeRecurringTime" />
          </label>
          <label>
            <span>Kiểu lặp</span>
            <select v-model="form.recurrence_type">
              <option value="daily">Hàng ngày</option>
              <option value="weekly">Hàng tuần</option>
              <option value="monthly">Hàng tháng</option>
            </select>
          </label>
          <label>
            <span>Chu kỳ</span>
            <select v-model.number="form.recurrence_interval">
              <option v-for="value in 12" :key="value" :value="value">{{ value }}</option>
            </select>
          </label>
        </div>

        <div v-if="form.recurrence_type === 'weekly'" class="day-grid">
          <label
            v-for="day in weekDays"
            :key="day.value"
            :class="{ selected: form.recurrence_days_of_week.includes(day.value) }"
          >
            <input v-model="form.recurrence_days_of_week" type="checkbox" :value="day.value" />
            <span>{{ day.label }}</span>
          </label>
        </div>

        <label v-if="form.recurrence_type === 'monthly'" class="month-days">
          <span>Ngày trong tháng</span>
          <input v-model="monthDaysInput" type="text" placeholder="1, 15, 30" />
        </label>

        <section class="recurring-payment">
          <div class="section-title muted">
            <h2>Thu tiền</h2>
            <p>Thiết lập cách ghi nhận thanh toán cho toàn bộ nhóm lịch cố định.</p>
          </div>

          <div class="payment-list recurring-payment-list">
            <label
              v-for="option in recurringPaymentOptions"
              :key="option.value"
              class="payment-card"
              :class="{ active: form.payment_option === option.value }"
            >
              <input v-model="form.payment_option" type="radio" :value="option.value" @change="syncPaidState" />
              <span>
                {{ option.label }}
                <small>{{ option.description }}</small>
              </span>
            </label>
          </div>

          <div v-if="form.payment_option !== 'no_prepay'" class="settlement-card">
            <div class="segmented-field">
              <span>Trạng thái thu</span>
              <div>
                <button type="button" :class="{ active: form.is_paid }" @click="setRecurringPaid(true)">Đã thu</button>
                <button type="button" :class="{ active: !form.is_paid }" @click="setRecurringPaid(false)">Chưa thu</button>
              </div>
            </div>

            <div v-if="form.is_paid" class="segmented-field">
              <span>Phương thức</span>
              <div>
                <button
                  v-for="method in recurringPaymentMethods"
                  :key="method.value"
                  type="button"
                  :class="{ active: form.payment_method === method.value }"
                  @click="form.payment_method = method.value"
                >
                  <AppIcon :name="method.icon" size="15" />
                  {{ method.label }}
                </button>
              </div>
            </div>
          </div>

          <div v-else class="inline-note">
            Lịch sẽ được tạo trước, tiền thu sau từng buổi khi khách đến chơi.
          </div>
        </section>

        <div class="form-actions">
          <button class="primary-btn" type="button" :disabled="submitting || !canSubmitRecurring" @click="submitRecurring">
            <AppIcon name="calendar" size="16" />
            <span>{{ submitting ? 'Đang tạo...' : 'Tạo lịch cố định' }}</span>
          </button>
        </div>
      </div>

      <aside class="preview-box">
        <strong>{{ recurringPreview.length }} buổi sẽ được tạo</strong>
        <span v-if="recurringPreview.length">
          {{ selectedRecurringCourt?.name || 'Sân con' }} · {{ formatTime(form.start_time) }} - {{ formatTime(form.end_time) }}
        </span>
        <span v-else>Chưa có buổi phù hợp.</span>
        <div v-if="recurringPreview.length" class="preview-list">
          <span v-for="date in recurringPreview.slice(0, 18)" :key="date">{{ formatDate(date) }}</span>
        </div>
        <small v-if="recurringPreview.length > 18">Còn {{ recurringPreview.length - 18 }} buổi khác.</small>
      </aside>
    </section>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { bookingService } from '../../services/bookingService.js';
import { ownerBookingService } from '../../services/ownerBookings.js';
import { venueClusterService } from '../../services/venueClusters.js';

function toIsoDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function toWeekDayIndex(date) {
  return (date.getDay() + 6) % 7;
}

const BOOKING_DAY_START = 6 * 60;
const BOOKING_DAY_END = 22 * 60;
const SLOT_STEP_MINUTES = 30;
const SLOT_PERIODS = [
  { key: 'morning', label: 'Sáng', range: '06:00 - 12:00', start: 6 * 60, end: 12 * 60 },
  { key: 'afternoon', label: 'Chiều', range: '12:00 - 18:00', start: 12 * 60, end: 18 * 60 },
  { key: 'evening', label: 'Tối', range: '18:00 - 22:00', start: 18 * 60, end: 22 * 60 },
];

export default {
  name: 'OwnerCounterBooking',
  components: { AppIcon },
  data() {
    const now = new Date();
    const today = toIsoDate(now);

    return {
      today,
      activeTab: 'counter',
      clusters: [],
      courts: [],
      selectedClusterId: '',
      selectedClusterDetail: null,
      selectedCourtTypeId: '',
      scheduleSlots: [],
      scheduleCourts: [],
      scheduleSlotStatuses: [],
      scheduleBusyIntervals: [],
      selectedGridCourtId: '',
      selectedSlotIndexes: [],
      timePeriods: SLOT_PERIODS,
      scheduleLoading: false,
      scheduleError: '',
      selectionError: '',
      monthDaysInput: '1',
      form: {
        venue_court_id: '',
        walk_in_name: '',
        walk_in_phone: '',
        booking_date: today,
        recurring_start_date: today,
        recurring_end_date: today,
        recurrence_type: 'weekly',
        recurrence_interval: 1,
        recurrence_days_of_week: [toWeekDayIndex(now)],
        start_time: '08:00',
        end_time: '09:00',
        payment_option: 'full_payment',
        collection_mode: 'cash',
        is_paid: true,
        payment_method: 'cash',
      },
      weekDays: [
        { value: 0, label: 'T2' },
        { value: 1, label: 'T3' },
        { value: 2, label: 'T4' },
        { value: 3, label: 'T5' },
        { value: 4, label: 'T6' },
        { value: 5, label: 'T7' },
        { value: 6, label: 'CN' },
      ],
      recurringPaymentMethods: [
        { value: 'cash', label: 'Tiền mặt', icon: 'banknote' },
        { value: 'bank_transfer', label: 'Chuyển khoản', icon: 'creditCard' },
      ],
      submitting: false,
      error: '',
      notice: '',
      counterQr: null,
      counterQrBookingId: '',
      counterQrPollInterval: null,
    };
  },
  computed: {
    selectedCluster() {
      return this.clusters.find((cluster) => String(cluster.id) === String(this.selectedClusterId)) || null;
    },
    selectedCounterCourt() {
      return this.scheduleCourts.find((court) => String(court.id) === String(this.selectedGridCourtId)) || null;
    },
    selectedRecurringCourt() {
      return this.courts.find((court) => String(court.id) === String(this.form.venue_court_id)) || null;
    },
    hasCounterSelection() {
      return Boolean(this.selectedCounterCourt && this.selectedSlotIndexes.length);
    },
    courtTypeOptions() {
      const map = new Map();
      this.courts.forEach((court) => {
        if (court.court_type?.id) map.set(court.court_type.id, court.court_type);
      });
      return [...map.values()].sort((a, b) => a.name.localeCompare(b.name));
    },
    bookableScheduleSlots() {
      return this.scheduleSlots.filter((slot) => {
        const start = this.timeToMinutes(slot.start_time);
        const end = this.timeToMinutes(slot.end_time);
        return start >= BOOKING_DAY_START && end <= BOOKING_DAY_END;
      });
    },
    slotPeriods() {
      return this.timePeriods.map((period) => ({
        ...period,
        slots: this.bookableScheduleSlots.filter((slot) => {
          const start = this.timeToMinutes(slot.start_time);
          return start >= period.start && start < period.end;
        }),
      }));
    },
    selectedDurationMinutes() {
      return this.selectedSlotIndexes.reduce((total, index) => {
        const slot = this.bookableScheduleSlots[index];
        if (!slot) return total;

        return total + Math.max(this.timeToMinutes(slot.end_time) - this.timeToMinutes(slot.start_time), 0);
      }, 0);
    },
    selectedDurationText() {
      if (!this.selectedDurationMinutes) return '0 phút';

      const hours = Math.floor(this.selectedDurationMinutes / 60);
      const minutes = this.selectedDurationMinutes % 60;
      if (!hours) return `${minutes} phút`;
      if (!minutes) return `${hours} giờ`;
      return `${hours} giờ ${minutes} phút`;
    },
    selectedSlotRanges() {
      const slots = [...this.selectedSlotIndexes]
        .sort((a, b) => a - b)
        .map((index) => this.bookableScheduleSlots[index])
        .filter(Boolean);
      const ranges = [];

      slots.forEach((slot) => {
        const current = ranges[ranges.length - 1];
        if (!current || current.end_time !== slot.start_time) {
          ranges.push({
            start_time: slot.start_time,
            end_time: slot.end_time,
          });
          return;
        }

        current.end_time = slot.end_time;
      });

      return ranges;
    },
    selectedTimeText() {
      if (!this.hasCounterSelection) return '-';
      return this.selectedSlotRanges
        .map((range) => `${this.formatTime(range.start_time)} - ${this.formatTime(range.end_time)}`)
        .join(', ');
    },
    selectedTotal() {
      return this.selectedSlotIndexes.reduce((total, index) => {
        const slot = this.bookableScheduleSlots[index];
        const status = this.slotStatus(this.selectedGridCourtId, slot);
        return total + Number(status?.price || 0);
      }, 0);
    },
    depositPercent() {
      return Number(this.selectedClusterDetail?.booking_config?.deposit_percent || 30);
    },
    counterCollectionOptions() {
      return [
        {
          value: 'cash',
          label: 'Tiền mặt',
          description: 'Ghi nhận đã thu tại quầy.',
          amount: this.selectedTotal,
        },
        {
          value: 'transfer',
          label: 'Chuyển khoản',
          description: 'Tạo thông tin chuyển khoản và tự xác nhận khi tiền về.',
          amount: this.selectedTotal,
        },
        {
          value: 'later',
          label: 'Thu sau',
          description: 'Giữ sân trước, thu tiền sau khi khách chơi xong.',
          amount: this.selectedTotal,
        },
      ];
    },
    paymentOptions() {
      const config = this.selectedClusterDetail?.booking_config || {};
      const baseAmount = this.activeTab === 'counter' ? this.selectedTotal : 0;
      const options = [
        {
          value: 'full_payment',
          label: 'Thanh toán đủ',
          amount: baseAmount,
          enabled: config.allow_full_payment !== false,
        },
        {
          value: 'deposit',
          label: `Đặt cọc ${this.depositPercent}%`,
          amount: Math.round(baseAmount * this.depositPercent / 100),
          enabled: config.allow_deposit !== false,
        },
        {
          value: 'no_prepay',
          label: 'Thu sau / ghi nợ',
          amount: 0,
          enabled: config.allow_no_prepay !== false,
        },
      ];

      return options.filter((option) => option.enabled);
    },
    recurringPaymentOptions() {
      const descriptions = {
        full_payment: 'Ghi nhận thu đủ cho từng buổi trong nhóm lịch.',
        deposit: `Ghi nhận tiền cọc ${this.depositPercent}% theo chính sách hiện tại.`,
        no_prepay: 'Tạo lịch trước, thu tiền sau khi khách đến chơi.',
      };

      return this.paymentOptions.map((option) => ({
        ...option,
        description: descriptions[option.value] || option.label,
      }));
    },
    counterSummaryRows() {
      return [
        ['Cụm sân', this.selectedCluster?.name || '-'],
        ['Sân', this.selectedCounterCourt?.name || '-'],
        ['Ngày', this.formatDate(this.form.booking_date)],
        ['Giờ', this.selectedTimeText],
        ['Thời lượng', this.selectedDurationText],
        ['Tổng tiền', this.formatCurrency(this.selectedTotal)],
      ];
    },
    currentScheduleLabel() {
      return `${this.selectedCluster?.name || 'Cụm sân'} · ${this.formatDate(this.form.booking_date)}`;
    },
    recurringPreview() {
      if (this.activeTab !== 'recurring') return [];
      const start = this.parseDate(this.form.recurring_start_date);
      const end = this.parseDate(this.form.recurring_end_date);
      if (!start || !end || end < start) return [];

      const dates = [];
      const selectedMonthDays = this.monthDaysInput
        .split(',')
        .map((item) => Number(item.trim()))
        .filter((day) => day >= 1 && day <= 31);

      for (let date = new Date(start); date <= end && dates.length <= 130; date.setDate(date.getDate() + 1)) {
        const current = new Date(date);
        const dayDiff = Math.floor((current - start) / 86400000);
        const weekDiff = Math.floor(dayDiff / 7);
        const monthDiff = (current.getFullYear() - start.getFullYear()) * 12 + (current.getMonth() - start.getMonth());
        let match = false;

        if (this.form.recurrence_type === 'daily') {
          match = dayDiff % this.form.recurrence_interval === 0;
        } else if (this.form.recurrence_type === 'weekly') {
          match = weekDiff % this.form.recurrence_interval === 0 && this.form.recurrence_days_of_week.includes(this.dayIndex(current));
        } else {
          match = monthDiff % this.form.recurrence_interval === 0 && selectedMonthDays.includes(current.getDate());
        }

        if (match) dates.push(this.formatIsoDate(current));
      }

      return dates;
    },
    canSubmitCounter() {
      return this.hasCounterSelection
        && this.form.walk_in_name
        && this.form.walk_in_phone
        && this.form.payment_option
        && !this.submitting;
    },
    canSubmitRecurring() {
      const start = this.timeToMinutes(this.form.start_time);
      const end = this.timeToMinutes(this.form.end_time);

      return this.form.venue_court_id
        && this.form.walk_in_name
        && this.form.walk_in_phone
        && this.form.payment_option
        && start >= BOOKING_DAY_START
        && end <= BOOKING_DAY_END
        && end > start
        && this.recurringPreview.length > 0
        && !this.submitting;
    },
  },
  async created() {
    await this.loadOwnerData();
  },
  beforeUnmount() {
    this.clearCounterQrPolling();
  },
  methods: {
    async loadOwnerData() {
      this.error = '';

      try {
        const response = await venueClusterService.getClusters();
        this.clusters = response.data || [];
        this.selectedClusterId = this.$route.query.venue_cluster_id || localStorage.getItem('selected_cluster') || this.clusters[0]?.id || '';
        await this.handleClusterChange();
      } catch (error) {
        this.error = error.message || 'Không thể tải dữ liệu cụm sân.';
      }
    },
    async handleClusterChange() {
      this.selectedCourtTypeId = '';
      this.selectedSlotIndexes = [];
      this.selectedGridCourtId = '';
      this.form.venue_court_id = '';

      if (!this.selectedClusterId) return;
      localStorage.setItem('selected_cluster', this.selectedClusterId);

      await Promise.all([this.loadClusterDetail(), this.loadCourts()]);
      this.syncPaymentOption();
      await this.loadSchedule();
    },
    async loadClusterDetail() {
      try {
        const response = await venueClusterService.getClusterDetails(this.selectedClusterId);
        this.selectedClusterDetail = response.data || null;
      } catch {
        this.selectedClusterDetail = null;
      }
    },
    async loadCourts() {
      const response = await venueClusterService.getCourts(this.selectedClusterId, { status: 'active' });
      this.courts = response.data || [];
      this.form.venue_court_id = this.courts[0]?.id || '';
    },
    async loadSchedule() {
      if (!this.selectedClusterId) return;

      this.scheduleLoading = true;
      this.scheduleError = '';
      this.selectionError = '';
      const previousCourtId = this.selectedGridCourtId;
      this.selectedSlotIndexes = [];

      try {
        const response = await bookingService.getSchedule({
          venue_cluster_id: this.selectedClusterId,
          booking_date: this.form.booking_date,
          court_type_id: this.selectedCourtTypeId,
          booking_type: this.activeTab === 'recurring' ? 'recurring' : 'single',
        });

        this.scheduleSlots = response.time_slots || [];
        this.scheduleCourts = response.courts || [];
        this.scheduleSlotStatuses = response.slot_statuses || [];
        this.scheduleBusyIntervals = response.busy_intervals || [];

        this.selectedGridCourtId = this.scheduleCourts.some((court) => String(court.id) === String(previousCourtId))
          ? previousCourtId
          : this.scheduleCourts[0]?.id || '';
        this.form.venue_court_id = this.selectedGridCourtId;
        this.syncCounterRangeFields();
      } catch (error) {
        this.scheduleError = error.message || 'Không thể tải lịch sân.';
      } finally {
        this.scheduleLoading = false;
      }
    },
    slotStatus(courtId, slot) {
      if (!slot) return null;
      return this.scheduleSlotStatuses.find((status) => String(status.venue_court_id) === String(courtId) && status.start_time === slot.start_time) || null;
    },
    busyInterval(courtId, slot) {
      if (!slot) return null;
      return this.scheduleBusyIntervals.find((item) => String(item.venue_court_id) === String(courtId)
        && this.timeToMinutes(item.start_time) < this.timeToMinutes(slot.end_time)
        && this.timeToMinutes(item.end_time) > this.timeToMinutes(slot.start_time));
    },
    isSlotBusy(courtId, slot) {
      const status = this.slotStatus(courtId, slot);
      return !status || !status.is_available;
    },
    isSlotDisabled(slot) {
      if (!this.selectedGridCourtId || !slot) return true;

      return this.isSlotBusy(this.selectedGridCourtId, slot);
    },
    isSlotSelected(slot) {
      return this.selectedSlotIndexes.some((index) => this.bookableScheduleSlots[index]?.start_time === slot.start_time);
    },
    slotButtonClass(slot) {
      const selected = this.isSlotSelected(slot);
      const busy = this.isSlotBusy(this.selectedGridCourtId, slot);
      const interval = this.busyInterval(this.selectedGridCourtId, slot);

      return {
        selected,
        busy,
        pending: busy && ['pending_payment', 'pending_approval', 'auto', 'manual'].includes(interval?.status),
      };
    },
    slotPriceLabel(slot) {
      const interval = this.busyInterval(this.selectedGridCourtId, slot);
      const status = this.slotStatus(this.selectedGridCourtId, slot);

      if (interval) return ['pending_payment', 'pending_approval', 'auto', 'manual'].includes(interval.status) ? 'Đang giữ' : 'Đã đặt';
      if (!status || !status.is_available) return 'Bận';

      return this.formatCurrency(status.price);
    },
    slotActionTitle(slot) {
      if (!slot) return '';
      const start = this.formatTime(slot.start_time);
      const end = this.formatTime(slot.end_time);
      const selected = this.isSlotSelected(slot);

      if (this.isSlotDisabled(slot)) {
        return `${start} - ${end} không khả dụng`;
      }

      return selected
        ? `Bỏ chọn ${start} - ${end}`
        : `Chọn ${start} - ${end} · ${this.slotPriceLabel(slot)}`;
    },
    handleCounterCourtChange() {
      this.selectedSlotIndexes = [];
      this.form.venue_court_id = this.selectedGridCourtId;
      this.syncCounterRangeFields();
    },
    syncCounterRangeFields() {
      this.selectionError = '';
      const ranges = this.selectedSlotRanges;

      if (!ranges.length) {
        this.form.start_time = '06:00';
        this.form.end_time = '06:30';
        return;
      }

      this.form.start_time = this.formatTime(ranges[0].start_time);
      this.form.end_time = this.formatTime(ranges[ranges.length - 1].end_time);
      this.form.venue_court_id = this.selectedGridCourtId;
    },
    toggleSlot(slot) {
      if (this.isSlotDisabled(slot)) return;

      const index = this.bookableScheduleSlots.findIndex((item) => item.start_time === slot.start_time);
      if (index < 0) return;

      this.selectionError = '';
      this.selectedSlotIndexes = this.selectedSlotIndexes.includes(index)
        ? this.selectedSlotIndexes.filter((item) => item !== index)
        : [...this.selectedSlotIndexes, index].sort((a, b) => a - b);
      this.syncCounterRangeFields();
    },
    async submitCounter() {
      if (!this.canSubmitCounter) return;
      this.submitting = true;
      this.error = '';
      this.notice = '';
      this.counterQr = null;
      this.clearCounterQrPolling();

      try {
        const timeRanges = this.selectedSlotRanges.map((range) => ({
          start_time: this.withSeconds(this.formatTime(range.start_time)),
          end_time: this.withSeconds(this.formatTime(range.end_time)),
        }));
        const firstRange = timeRanges[0];
        const lastRange = timeRanges[timeRanges.length - 1];
        const response = await ownerBookingService.createCounter({
          venue_court_id: this.selectedGridCourtId,
          walk_in_name: this.form.walk_in_name,
          walk_in_phone: this.form.walk_in_phone,
          booking_date: this.form.booking_date,
          start_time: firstRange.start_time,
          end_time: lastRange.end_time,
          time_ranges: timeRanges,
          payment_option: this.form.collection_mode === 'later' ? 'no_prepay' : 'full_payment',
          is_paid: this.form.collection_mode === 'cash',
          payment_method: this.form.collection_mode === 'transfer' ? 'sepay' : 'cash',
        });

        this.notice = response.payment_qr ? 'Đã tạo booking và thông tin chuyển khoản.' : 'Đã tạo booking tại quầy.';
        if (response.payment_qr) {
          this.counterQr = response.payment_qr;
          this.counterQrBookingId = response.data?.id || '';
          this.startCounterQrPolling();
        }
        this.selectedSlotIndexes = [];
        this.selectedGridCourtId = '';
        this.syncCounterRangeFields();
        await this.loadSchedule();
      } catch (error) {
        this.error = error.message || 'Không thể tạo booking tại quầy.';
      } finally {
        this.submitting = false;
      }
    },
    async submitRecurring() {
      if (!this.canSubmitRecurring) return;
      this.normalizeRecurringTime();
      this.submitting = true;
      this.error = '';
      this.notice = '';

      try {
        const payload = {
          venue_court_id: this.form.venue_court_id,
          walk_in_name: this.form.walk_in_name,
          walk_in_phone: this.form.walk_in_phone,
          start_time: this.withSeconds(this.form.start_time),
          end_time: this.withSeconds(this.form.end_time),
          payment_option: this.form.payment_option,
          is_paid: this.form.payment_option !== 'no_prepay' ? this.form.is_paid : false,
          payment_method: this.form.payment_method,
          recurring_start_date: this.form.recurring_start_date,
          recurring_end_date: this.form.recurring_end_date,
          recurrence_type: this.form.recurrence_type,
          recurrence_interval: this.form.recurrence_interval,
        };

        if (this.form.recurrence_type === 'weekly') {
          payload.recurrence_days_of_week = this.form.recurrence_days_of_week;
        }

        if (this.form.recurrence_type === 'monthly') {
          payload.recurrence_days_of_month = this.monthDaysInput.split(',').map((item) => Number(item.trim())).filter(Boolean);
        }

        const response = await ownerBookingService.createRecurring(payload);

        this.notice = `Đã tạo ${response.data?.created_count || this.recurringPreview.length} buổi cố định.`;
      } catch (error) {
        this.error = error.message || 'Không thể tạo lịch cố định.';
      } finally {
        this.submitting = false;
      }
    },
    syncPaymentOption() {
      if (!this.paymentOptions.some((option) => option.value === this.form.payment_option)) {
        this.form.payment_option = this.paymentOptions[0]?.value || 'no_prepay';
      }
      this.syncPaidState();
    },
    syncPaidState() {
      if (this.form.payment_option === 'no_prepay') {
        this.form.is_paid = false;
        this.form.payment_method = 'cash';
        if (this.activeTab === 'counter') this.form.collection_mode = 'later';
      } else if (this.form.collection_mode === 'later' && this.form.is_paid) {
        this.form.collection_mode = 'cash';
      }
    },
    setRecurringPaid(isPaid) {
      this.form.is_paid = isPaid;
      if (!isPaid) {
        this.form.payment_method = 'cash';
      }
    },
    applyCounterCollectionMode() {
      if (this.form.collection_mode === 'later') {
        this.form.payment_option = 'no_prepay';
        this.form.payment_method = 'cash';
        this.form.is_paid = false;
        return;
      }

      this.form.payment_option = 'full_payment';
      this.form.payment_method = this.form.collection_mode === 'transfer' ? 'sepay' : 'cash';
      this.form.is_paid = this.form.collection_mode === 'cash';
    },
    normalizeRecurringTime() {
      let start = this.timeToMinutes(this.form.start_time);
      let end = this.timeToMinutes(this.form.end_time);

      if (start < BOOKING_DAY_START) start = BOOKING_DAY_START;
      if (start >= BOOKING_DAY_END) start = BOOKING_DAY_END - SLOT_STEP_MINUTES;
      if (end > BOOKING_DAY_END) end = BOOKING_DAY_END;
      if (end <= start) end = Math.min(start + 60, BOOKING_DAY_END);
      if (end <= start) {
        start = Math.max(BOOKING_DAY_START, end - SLOT_STEP_MINUTES);
      }

      this.form.start_time = this.minutesToTime(start);
      this.form.end_time = this.minutesToTime(end);
    },
    startCounterQrPolling() {
      this.clearCounterQrPolling();
      if (!this.counterQrBookingId) return;

      this.counterQrPollInterval = setInterval(() => {
        this.refreshCounterQrBooking();
      }, 5000);
    },
    async refreshCounterQrBooking() {
      if (!this.counterQrBookingId) return;

      try {
        const response = await ownerBookingService.show(this.counterQrBookingId);
        const booking = response.data || response;
        const paidAmount = this.paidAmount(booking);

        if (paidAmount >= Number(booking.total_price || 0) || booking.status !== 'pending_payment') {
          this.notice = 'Chuyển khoản đã được ghi nhận.';
          this.counterQr = null;
          this.counterQrBookingId = '';
          this.clearCounterQrPolling();
          await this.loadSchedule();
        }
      } catch {
        this.clearCounterQrPolling();
      }
    },
    clearCounterQrPolling() {
      if (this.counterQrPollInterval) {
        clearInterval(this.counterQrPollInterval);
        this.counterQrPollInterval = null;
      }
    },
    paidAmount(booking) {
      return (booking?.payments || [])
        .filter((payment) => payment.status === 'paid')
        .reduce((sum, payment) => sum + Number(payment.amount || 0), 0);
    },
    async copyText(text) {
      if (!text) return;

      try {
        await navigator.clipboard.writeText(text);
        this.notice = 'Đã sao chép nội dung chuyển khoản.';
      } catch {
        this.error = 'Không thể sao chép nội dung chuyển khoản.';
      }
    },
    withSeconds(time) {
      return time.length === 5 ? `${time}:00` : time;
    },
    formatTime(time) {
      return (time || '').slice(0, 5);
    },
    timeToMinutes(time) {
      const [hour, minute] = this.formatTime(time).split(':').map(Number);
      return (hour || 0) * 60 + (minute || 0);
    },
    minutesToTime(minutes) {
      if (minutes >= 1440) return '24:00';
      return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
    },
    dayIndex(value) {
      const date = value instanceof Date ? value : new Date(value);
      return toWeekDayIndex(date);
    },
    parseDate(value) {
      if (!value) return null;
      const date = new Date(`${value}T00:00:00`);
      return Number.isNaN(date.getTime()) ? null : date;
    },
    formatIsoDate(value) {
      const date = value instanceof Date ? value : new Date(value);
      return toIsoDate(date);
    },
    formatDate(value) {
      const date = this.parseDate(value);
      if (!date) return '-';
      return new Intl.DateTimeFormat('vi-VN').format(date);
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
      }).format(Number(amount || 0));
    },
  },
};
</script>

<style scoped>
.owner-counter-page {
  display: grid;
  gap: 18px;
}

.counter-board {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 16px;
  align-items: start;
}

.recurring-panel {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 16px;
  align-items: start;
}

.schedule-panel,
.booking-side,
.form-card,
.preview-box,
.alert {
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
}

.schedule-panel,
.booking-side,
.form-card,
.preview-box {
  padding: 18px;
}

.form-card {
  display: grid;
  gap: 16px;
}

.panel-head.compact {
  margin-bottom: 14px;
}

.panel-head h2,
.section-title h2 {
  margin: 0;
  color: #16231a;
  font-size: 17px;
  font-weight: 800;
}

.panel-head p {
  margin: 4px 0 0;
  color: #607267;
  font-size: 13px;
}

.section-title p {
  margin: 4px 0 0;
  color: #607267;
  font-size: 13px;
  line-height: 1.45;
}

.schedule-filters,
.form-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.booking-picker {
  display: grid;
  grid-template-columns: minmax(240px, 1.3fr) minmax(220px, 1fr) minmax(130px, 0.5fr);
  gap: 12px;
  align-items: end;
  margin-top: 12px;
  padding: 14px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #f7fbf5;
}

.duration-pill {
  display: grid;
  gap: 6px;
  min-height: 42px;
  padding: 9px 12px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
}

.selection-help {
  display: grid;
  gap: 5px;
  min-height: 42px;
  padding: 9px 12px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
}

.selection-help span {
  color: #607267;
  font-size: 11px;
  font-weight: 800;
}

.selection-help strong {
  color: #16231a;
  font-size: 14px;
  font-weight: 850;
}

.selection-help small {
  color: #607267;
  font-size: 12px;
  font-weight: 650;
  line-height: 1.35;
}

.duration-pill span {
  color: #607267;
  font-size: 11px;
  font-weight: 800;
}

.duration-pill strong {
  color: #16231a;
  font-size: 14px;
  font-weight: 850;
}

.duration-pill.active {
  border-color: rgba(47, 158, 68, 0.45);
  background: #e8f7ec;
}

label {
  display: grid;
  gap: 7px;
}

label span,
.summary-list dt {
  color: #223127;
  font-size: 13px;
  font-weight: 760;
}

input,
select {
  width: 100%;
}

.legend {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin: 14px 0;
  color: #475b4d;
  font-size: 12px;
  font-weight: 700;
}

.legend span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.legend i {
  width: 12px;
  height: 12px;
  border: 1px solid #b9cbbb;
  border-radius: 3px;
  background: #fff;
}

.legend i.selected {
  border-color: #2f9e44;
  background: #2f9e44;
}

.legend i.pending {
  border-color: #f2c879;
  background: #fff2c7;
}

.legend i.busy {
  border-color: #c4cec4;
  background: #dfe7df;
}

.selection-error {
  margin: 0 0 12px;
  color: #991b1b;
  font-size: 13px;
  font-weight: 800;
}

.time-board {
  display: grid;
  gap: 12px;
}

.slot-matrix {
  overflow: hidden;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
}

.selected-court-strip {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  padding: 12px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #f7fbf5;
}

.selected-court-strip div {
  display: grid;
  gap: 3px;
}

.selected-court-strip span,
.period-label span {
  color: #607267;
  font-size: 12px;
  font-weight: 750;
}

.selected-court-strip strong,
.period-label strong {
  color: #16231a;
  font-size: 14px;
  font-weight: 850;
}

.period-row {
  display: grid;
  grid-template-columns: 118px minmax(0, 1fr);
  align-items: stretch;
  border-bottom: 1px solid #d9e8d9;
  background: #fff;
}

.period-row:last-child {
  border-bottom: 0;
}

.period-label {
  display: grid;
  align-content: center;
  gap: 4px;
  min-height: 38px;
  padding: 10px 12px;
  border-right: 1px solid #d9e8d9;
  background: #f2f7ef;
}

.period-slots {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(34px, 1fr));
  gap: 0;
}

.time-slot {
  min-height: 38px;
  padding: 0;
  border: 0;
  border-right: 1px solid #e4eee4;
  border-radius: 0;
  background: #fff;
  transition: background 0.16s ease, box-shadow 0.16s ease;
}

.time-slot:last-child {
  border-right: 0;
}

.time-slot:hover:not(:disabled) {
  background: #e8f7ec;
  box-shadow: inset 0 0 0 1px rgba(47, 158, 68, 0.4);
}

.time-slot.selected {
  background: #2f9e44;
  box-shadow: inset 0 0 0 1px #2f9e44;
}

.time-slot.busy {
  background: #eef3ee;
}

.time-slot.pending {
  background: #fff7dc;
}

.time-slot:disabled {
  cursor: not-allowed;
  opacity: 0.72;
}

.booking-side {
  position: sticky;
  top: 88px;
  display: grid;
  gap: 16px;
}

.side-section {
  display: grid;
  gap: 12px;
  padding-bottom: 15px;
  border-bottom: 1px solid #e4eee4;
}

.side-section.disabled {
  opacity: 0.56;
  pointer-events: none;
}

.empty-summary {
  display: grid;
  place-items: center;
  min-height: 78px;
  padding: 14px;
  border: 1px dashed #b9cbbb;
  border-radius: 8px;
  color: #607267;
  text-align: center;
}

.summary-list {
  display: grid;
  gap: 8px;
  margin: 0;
}

.summary-list div {
  display: flex;
  justify-content: space-between;
  gap: 14px;
}

.summary-list dd {
  margin: 0;
  color: #16231a;
  font-weight: 800;
  text-align: right;
}

.payment-list {
  display: grid;
  gap: 8px;
}

.payment-card {
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
  padding: 11px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
}

.payment-card.active {
  border-color: #2f9e44;
  background: #e8f7ec;
}

.payment-card input {
  width: 16px;
  height: 16px;
  accent-color: #2f9e44;
}

.payment-card strong {
  color: #216b34;
}

.payment-card small {
  display: block;
  margin-top: 4px;
  color: #607267;
  font-size: 12px;
  font-weight: 650;
  line-height: 1.35;
}

.inline-note {
  padding: 10px 12px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #f7fbf5;
  color: #475b4d;
  font-size: 13px;
  font-weight: 700;
}

.recurring-payment {
  display: grid;
  gap: 12px;
  padding: 14px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #f7fbf5;
}

.recurring-payment-list {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.recurring-payment-list .payment-card {
  grid-template-columns: auto minmax(0, 1fr);
  background: #fff;
}

.settlement-card {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  padding: 12px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
}

.segmented-field {
  display: grid;
  gap: 7px;
}

.segmented-field > span {
  color: #223127;
  font-size: 13px;
  font-weight: 760;
}

.segmented-field > div {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.segmented-field button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 38px;
  padding: 8px 12px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
  color: #344238;
  font-weight: 850;
}

.segmented-field button.active {
  border-color: #2f9e44;
  background: #2f9e44;
  color: #fff;
}

.qr-section {
  border-bottom: 0;
  padding-bottom: 0;
}

.qr-section img {
  width: min(210px, 100%);
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
}

.qr-info {
  display: grid;
  gap: 8px;
}

.qr-info div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  color: #475b4d;
  font-size: 13px;
}

.qr-info button {
  border: 0;
  background: transparent;
  color: #216b34;
  font-weight: 850;
  text-decoration: underline;
}

.qr-info strong {
  color: #16231a;
}

.day-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.day-grid label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 38px;
  padding: 8px 12px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
}

.day-grid label.selected {
  border-color: #2f9e44;
  background: #e8f7ec;
  color: #216b34;
}

.day-grid input {
  width: 15px;
  height: 15px;
  accent-color: #2f9e44;
}

.month-days {
  max-width: 320px;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
}

.preview-box {
  position: sticky;
  top: 88px;
  display: grid;
  gap: 10px;
}

.preview-box strong {
  color: #16231a;
  font-size: 18px;
  font-weight: 850;
}

.preview-box span,
.preview-box small {
  color: #607267;
}

.preview-list {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
}

.preview-list span {
  padding: 5px 8px;
  border-radius: 999px;
  background: #e8f7ec;
  color: #216b34;
  font-size: 12px;
  font-weight: 750;
}

.primary-btn.full {
  width: 100%;
}

.state-card {
  padding: 22px;
  color: #607267;
  text-align: center;
}

.error-state {
  color: #991b1b;
}

.alert {
  padding: 13px 14px;
  font-weight: 800;
}

.alert.error {
  border-color: #f0b9b9;
  background: #fff5f5;
  color: #991b1b;
}

.alert.success {
  border-color: #bfe8ca;
  background: #e8f7ec;
  color: #216b34;
}

@media (max-width: 1080px) {
  .counter-board,
  .recurring-panel {
    grid-template-columns: 1fr;
  }

  .booking-side,
  .preview-box {
    position: static;
  }
}

@media (max-width: 820px) {
  .schedule-filters,
  .form-grid,
  .booking-picker,
  .selected-court-strip,
  .settlement-card,
  .recurring-payment-list {
    grid-template-columns: 1fr;
  }

  .period-row {
    grid-template-columns: 1fr;
  }

  .period-label {
    border-right: 0;
    border-bottom: 1px solid #d9e8d9;
  }
}
</style>
