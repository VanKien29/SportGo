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
          <label>
            <span>Thời lượng</span>
            <input :value="selectedDurationText" type="text" readonly />
          </label>
        </div>

        <div class="legend">
          <span><i></i>Trống</span>
          <span><i class="selected"></i>Đang chọn</span>
          <span><i class="pending"></i>Chờ thanh toán / giữ chỗ</span>
          <span><i class="busy"></i>Đã đặt</span>
        </div>

        <p v-if="selectionError" class="selection-error">{{ selectionError }}</p>

        <div v-if="scheduleLoading" class="state-card">Đang tải lịch sân...</div>
        <div v-else-if="scheduleError" class="state-card error-state">{{ scheduleError }}</div>
        <div v-else class="schedule-wrap">
          <div class="schedule-grid" :style="scheduleGridStyle">
            <div class="schedule-head sticky-col">Sân / giờ</div>
            <div v-for="slot in scheduleSlots" :key="slot.start_time" class="schedule-head time-head">
              {{ slot.label }}
            </div>

            <template v-for="court in scheduleCourts" :key="court.id">
              <div class="schedule-court sticky-col">
                <strong>{{ court.name }}</strong>
                <span>{{ court.court_type?.name || '-' }}</span>
              </div>
              <button
                v-for="(slot, index) in scheduleSlots"
                :key="`${court.id}-${slot.start_time}`"
                type="button"
                class="schedule-cell"
                :class="cellClass(court.id, slot, index)"
                :disabled="isSlotBusy(court.id, slot)"
                :title="slotTitle(court.id, slot)"
                @click="selectSlot(court, index)"
              ></button>
            </template>
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
              v-for="option in paymentOptions"
              :key="option.value"
              class="payment-card"
              :class="{ active: form.payment_option === option.value }"
            >
              <input v-model="form.payment_option" type="radio" :value="option.value" @change="syncPaidState" />
              <span>{{ option.label }}</span>
              <strong>{{ formatCurrency(option.amount) }}</strong>
            </label>
          </div>

          <div class="paid-row" :class="{ disabled: form.payment_option === 'no_prepay' }">
            <button type="button" :class="{ active: form.is_paid }" @click="form.is_paid = true">Đã thu</button>
            <button type="button" :class="{ active: !form.is_paid }" @click="form.is_paid = false">Chưa thu</button>
          </div>

          <label v-if="form.is_paid && form.payment_option !== 'no_prepay'">
            <span>Phương thức</span>
            <select v-model="form.payment_method">
              <option value="cash">Tiền mặt</option>
              <option value="bank_transfer">Chuyển khoản tại sân</option>
            </select>
          </label>
        </section>

        <button class="primary-btn full" type="button" :disabled="submitting || !canSubmitCounter" @click="submitCounter">
          <AppIcon name="plus" size="16" />
          <span>{{ submitting ? 'Đang tạo...' : 'Tạo booking' }}</span>
        </button>
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
            <input v-model="form.start_time" type="time" />
          </label>
          <label>
            <span>Kết thúc</span>
            <input v-model="form.end_time" type="time" />
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
          <label>
            <span>Hình thức thu</span>
            <select v-model="form.payment_option" @change="syncPaidState">
              <option v-for="option in paymentOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </label>
          <label>
            <span>Trạng thái thu</span>
            <select v-model="form.is_paid" :disabled="form.payment_option === 'no_prepay'">
              <option :value="true">Đã thu</option>
              <option :value="false">Chưa thu</option>
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
      submitting: false,
      error: '',
      notice: '',
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
    scheduleGridStyle() {
      return {
        gridTemplateColumns: `160px repeat(${this.scheduleSlots.length}, 34px)`,
      };
    },
    selectedDurationMinutes() {
      return this.selectedSlotIndexes.length * 30;
    },
    selectedDurationText() {
      return this.selectedDurationMinutes ? `${this.selectedDurationMinutes} phút` : 'Chưa chọn';
    },
    selectedTimeText() {
      if (!this.selectedSlotIndexes.length) return '-';
      const indexes = [...this.selectedSlotIndexes].sort((a, b) => a - b);
      const start = this.scheduleSlots[indexes[0]]?.start_time;
      const end = this.scheduleSlots[indexes[indexes.length - 1]]?.end_time;
      return `${this.formatTime(start)} - ${this.formatTime(end)}`;
    },
    selectedTotal() {
      return this.selectedSlotIndexes.reduce((total, index) => {
        const slot = this.scheduleSlots[index];
        const status = this.slotStatus(this.selectedGridCourtId, slot);
        return total + Number(status?.price || 0);
      }, 0);
    },
    depositPercent() {
      return Number(this.selectedClusterDetail?.booking_config?.deposit_percent || 30);
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
    counterSummaryRows() {
      return [
        ['Cụm sân', this.selectedCluster?.name || '-'],
        ['Sân', this.selectedCounterCourt?.name || '-'],
        ['Ngày', this.formatDate(this.form.booking_date)],
        ['Giờ', this.selectedTimeText],
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
      return this.form.venue_court_id
        && this.form.walk_in_name
        && this.form.walk_in_phone
        && this.form.payment_option
        && this.timeToMinutes(this.form.end_time) > this.timeToMinutes(this.form.start_time)
        && this.recurringPreview.length > 0
        && !this.submitting;
    },
  },
  async created() {
    await this.loadOwnerData();
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
      this.selectedSlotIndexes = [];
      this.selectedGridCourtId = '';

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
    cellClass(courtId, slot, index) {
      const busy = this.busyInterval(courtId, slot);
      return {
        selected: this.selectedGridCourtId === courtId && this.selectedSlotIndexes.includes(index),
        busy: this.isSlotBusy(courtId, slot),
        pending: busy && ['pending_payment', 'pending_approval', 'auto', 'manual'].includes(busy.status),
      };
    },
    slotTitle(courtId, slot) {
      const status = this.slotStatus(courtId, slot);
      if (!status) return 'Không có dữ liệu';
      if (!status.is_available) return 'Khung giờ không khả dụng';
      return `${this.formatTime(slot.start_time)} - ${this.formatTime(slot.end_time)} · ${this.formatCurrency(status.price)}`;
    },
    selectSlot(court, index) {
      this.selectionError = '';

      if (this.selectedGridCourtId && this.selectedGridCourtId !== court.id) {
        this.selectedSlotIndexes = [];
      }

      this.selectedGridCourtId = court.id;
      const selected = [...this.selectedSlotIndexes].sort((a, b) => a - b);

      if (selected.length === 0) {
        this.selectedSlotIndexes = [index];
      } else if (selected.includes(index)) {
        const min = selected[0];
        const max = selected[selected.length - 1];

        if (index === min || index === max) {
          this.selectedSlotIndexes = selected.filter((item) => item !== index);
        } else {
          this.selectionError = 'Khung giờ cần liền mạch.';
          return;
        }
      } else if (index === selected[0] - 1 || index === selected[selected.length - 1] + 1) {
        this.selectedSlotIndexes = [...selected, index].sort((a, b) => a - b);
      } else {
        this.selectionError = 'Khung giờ cần liền mạch.';
        return;
      }

      const indexes = [...this.selectedSlotIndexes].sort((a, b) => a - b);
      this.form.venue_court_id = this.selectedGridCourtId;

      if (indexes.length) {
        this.form.start_time = this.formatTime(this.scheduleSlots[indexes[0]]?.start_time);
        this.form.end_time = this.formatTime(this.scheduleSlots[indexes[indexes.length - 1]]?.end_time);
      }
    },
    async submitCounter() {
      if (!this.canSubmitCounter) return;
      this.submitting = true;
      this.error = '';
      this.notice = '';

      try {
        await ownerBookingService.createCounter({
          venue_court_id: this.selectedGridCourtId,
          walk_in_name: this.form.walk_in_name,
          walk_in_phone: this.form.walk_in_phone,
          booking_date: this.form.booking_date,
          start_time: this.withSeconds(this.form.start_time),
          end_time: this.withSeconds(this.form.end_time),
          payment_option: this.form.payment_option,
          is_paid: this.form.payment_option !== 'no_prepay' ? this.form.is_paid : false,
          payment_method: this.form.payment_method,
        });

        this.notice = 'Đã tạo booking tại quầy.';
        this.selectedSlotIndexes = [];
        this.selectedGridCourtId = '';
        await this.loadSchedule();
      } catch (error) {
        this.error = error.message || 'Không thể tạo booking tại quầy.';
      } finally {
        this.submitting = false;
      }
    },
    async submitRecurring() {
      if (!this.canSubmitRecurring) return;
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

.schedule-filters,
.form-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
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

.schedule-wrap {
  max-width: 100%;
  overflow: auto;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
}

.schedule-grid {
  display: grid;
  min-width: max-content;
}

.schedule-head,
.schedule-court,
.schedule-cell {
  min-height: 34px;
  border-right: 1px solid #e4eee4;
  border-bottom: 1px solid #e4eee4;
}

.schedule-head {
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f2f7ef;
  color: #334238;
  font-size: 11px;
  font-weight: 800;
}

.sticky-col {
  position: sticky;
  left: 0;
  z-index: 2;
}

.schedule-court {
  display: grid;
  align-content: center;
  gap: 3px;
  padding: 7px 10px;
  background: #fff;
}

.schedule-court strong {
  color: #16231a;
  font-size: 12px;
}

.schedule-court span {
  color: #607267;
  font-size: 11px;
}

.schedule-cell {
  background: #fff;
}

.schedule-cell:hover:not(:disabled) {
  background: #e8f7ec;
}

.schedule-cell.selected {
  background: #2f9e44;
}

.schedule-cell.busy {
  background: #dfe7df;
}

.schedule-cell.pending {
  background: #fff2c7;
}

.schedule-cell:disabled {
  cursor: not-allowed;
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

.paid-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.paid-row button {
  min-height: 38px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
  color: #344238;
  font-weight: 800;
}

.paid-row button.active {
  border-color: #2f9e44;
  background: #2f9e44;
  color: #fff;
}

.paid-row.disabled {
  opacity: 0.55;
  pointer-events: none;
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
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
