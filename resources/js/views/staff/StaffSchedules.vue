<template>
  <div class="staff-schedule-container">
    <!-- TOP CONTROLS & DATE NAVIGATION -->
    <div class="schedule-header">
      <div class="header-left">
        <div class="view-switch">
          <button
            type="button"
            class="switch-btn"
            :class="{ active: scheduleViewMode === 'week' }"
            @click="scheduleViewMode = 'week'"
          >
            Xem theo tuần
          </button>
          <button
            type="button"
            class="switch-btn"
            :class="{ active: scheduleViewMode === 'day' }"
            @click="scheduleViewMode = 'day'"
          >
            Xem theo ngày
          </button>
        </div>

        <span class="schedule-period-title">
          {{ scheduleViewMode === 'week' ? weekLabel : formattedSelectedDate }}
        </span>
      </div>

      <div class="header-right">
        <div class="date-navigator">
          <button
            type="button"
            class="nav-btn"
            :title="scheduleViewMode === 'day' ? 'Ngày trước' : 'Tuần trước'"
            @click="scheduleViewMode === 'day' ? shiftDay(-1) : shiftWeek(-1)"
          >
            <AppIcon name="chevronLeft" :size="16" />
          </button>

          <button
            type="button"
            class="nav-today-btn"
            :class="{ 'is-current': isCurrentPeriodSelected }"
            @click="scheduleViewMode === 'day' ? goToToday() : goToCurrentWeek()"
          >
            {{ scheduleViewMode === 'day' ? 'Hôm nay' : 'Tuần này' }}
          </button>

          <button
            type="button"
            class="nav-btn"
            :title="scheduleViewMode === 'day' ? 'Ngày sau' : 'Tuần sau'"
            @click="scheduleViewMode === 'day' ? shiftDay(1) : shiftWeek(1)"
          >
            <AppIcon name="chevronRight" :size="16" />
          </button>
        </div>

        <button
          type="button"
          class="refresh-btn"
          title="Làm mới"
          @click="loadSchedules"
        >
          <AppIcon name="refreshCw" :size="15" />
          <span>Làm mới</span>
        </button>
      </div>
    </div>

    <!-- NOTIFICATION MESSAGES -->
    <div v-if="error" class="notice-msg error">
      <span>{{ error }}</span>
      <button type="button" class="notice-close" @click="error = ''">✕</button>
    </div>
    <div v-if="successMsg" class="notice-msg success">
      <span>{{ successMsg }}</span>
      <button type="button" class="notice-close" @click="successMsg = ''">✕</button>
    </div>

    <!-- LOADING STATE -->
    <div v-if="loading" class="schedule-loading">
      <div class="loading-spinner"></div>
      <span>Đang tải lịch làm việc...</span>
    </div>

    <template v-else>
      <!-- ========================================================= -->
      <!-- 1. WEEK VIEW                                              -->
      <!-- ========================================================= -->
      <div v-if="scheduleViewMode === 'week'" class="week-layout">
        <div class="week-grid">
          <div
            v-for="day in weekDays"
            :key="day.iso"
            class="day-column"
            :class="{ 'is-today': day.iso === today }"
          >
            <div class="day-column-head">
              <span class="day-name">{{ day.label }}</span>
              <span class="day-date">{{ day.date }}</span>
            </div>

            <div class="day-shifts-list">
              <div
                v-for="sch in day.schedules"
                :key="sch.id"
                class="shift-entry"
                :class="sch.status"
              >
                <div class="shift-time">{{ timeRange(sch) }}</div>
                <div class="shift-name">{{ shiftName(sch) }}</div>
                <div class="shift-venue">{{ sch.venue_cluster?.name || 'Cụm sân' }}</div>
                <div class="shift-status" :class="sch.status">
                  {{ statusLabel(sch.status) }}
                </div>

                <div v-if="sch.notes" class="shift-notes-text">
                  {{ sch.notes }}
                </div>

                <!-- Action Button if applicable -->
                <div v-if="sch.date === today" class="shift-actions">
                  <button
                    v-if="sch.status === 'scheduled'"
                    type="button"
                    class="action-btn checkin"
                    :disabled="!canCheckIn(sch) || actionLoading === sch.id"
                    @click="handleCheckIn(sch.id)"
                  >
                    {{ actionLoading === sch.id ? 'Đang xử lý...' : 'Check-in' }}
                  </button>

                  <button
                    v-if="sch.status === 'checked_in'"
                    type="button"
                    class="action-btn checkout"
                    :disabled="actionLoading === sch.id"
                    @click="handleCheckOut(sch.id)"
                  >
                    {{ actionLoading === sch.id ? 'Đang xử lý...' : 'Check-out' }}
                  </button>
                </div>
              </div>

              <div v-if="!day.schedules.length" class="no-shift-msg">
                Không có ca
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ========================================================= -->
      <!-- 2. DAY VIEW                                               -->
      <!-- ========================================================= -->
      <div v-else class="day-layout">
        <!-- Weekday Switcher Row -->
        <div class="day-nav-bar">
          <button
            v-for="d in weekDays"
            :key="'nav-' + d.iso"
            type="button"
            class="day-nav-item"
            :class="{
              'active': d.iso === selectedDate,
              'is-today': d.iso === today
            }"
            @click="selectedDate = d.iso"
          >
            <span class="d-label">{{ d.label }}</span>
            <span class="d-date">{{ d.date }}</span>
          </button>
        </div>

        <!-- Shifts List for Selected Day -->
        <div v-if="sortedSelectedDaySchedules.length" class="day-shifts-container">
          <div
            v-for="sch in sortedSelectedDaySchedules"
            :key="'day-' + sch.id"
            class="day-shift-row"
            :class="sch.status"
          >
            <div class="shift-main-info">
              <div class="shift-time-col">
                <span class="time-text">{{ timeRange(sch) }}</span>
                <span class="duration-text">{{ calculateDuration(sch) }}</span>
              </div>

              <div class="shift-desc-col">
                <div class="name-text">{{ shiftName(sch) }}</div>
                <div class="venue-text">{{ sch.venue_cluster?.name || 'Cụm sân' }}</div>
                <div v-if="sch.notes" class="notes-text">{{ sch.notes }}</div>

                <div v-if="sch.check_in_at || sch.check_out_at" class="logs-text">
                  <span v-if="sch.check_in_at">Check-in: {{ formatTimeOnly(sch.check_in_at) }}</span>
                  <span v-if="sch.check_out_at"> &middot; Check-out: {{ formatTimeOnly(sch.check_out_at) }}</span>
                </div>
              </div>
            </div>

            <div class="shift-side-info">
              <div class="status-text" :class="sch.status">
                {{ statusLabel(sch.status) }}
              </div>

              <div v-if="sch.status === 'checked_in'" class="live-timer-text">
                Đã trực: {{ liveDuration(sch.check_in_at) }}
              </div>

              <div v-if="sch.date === today" class="shift-btn-wrap">
                <button
                  v-if="sch.status === 'scheduled'"
                  type="button"
                  class="action-btn checkin"
                  :disabled="!canCheckIn(sch) || actionLoading === sch.id"
                  @click="handleCheckIn(sch.id)"
                >
                  {{ actionLoading === sch.id ? 'Đang xử lý...' : 'Check-in ca trực' }}
                </button>

                <button
                  v-if="sch.status === 'checked_in'"
                  type="button"
                  class="action-btn checkout"
                  :disabled="actionLoading === sch.id"
                  @click="handleCheckOut(sch.id)"
                >
                  {{ actionLoading === sch.id ? 'Đang xử lý...' : 'Check-out kết thúc ca' }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="day-empty-msg">
          <p>Không có ca làm việc nào trong ngày {{ formattedSelectedDate }}.</p>
        </div>
      </div>
    </template>

    <!-- SHIFT HANDOVER MODAL -->
    <ShiftHandoverModal
      :is-open="showHandoverModal"
      :schedule-id="handoverScheduleId"
      @close="showHandoverModal = false"
      @checked-out="onShiftCheckedOut"
    />
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import ShiftHandoverModal from '../../components/staff/ShiftHandoverModal.vue';
import { ownerStaffShiftService } from '../../services/ownerStaffShiftService.js';
import { useToast } from 'vue-toastification';

export default {
  name: 'StaffSchedules',
  components: { AppIcon, ShiftHandoverModal },
  data() {
    return {
      weekStart: this.getMonday(new Date()),
      schedules: [],
      loading: true,
      error: '',
      successMsg: '',
      scheduleViewMode: 'week',
      selectedDate: this.isoDate(new Date()),
      actionLoading: null,
      showHandoverModal: false,
      handoverScheduleId: null,
      nowTime: new Date(),
      timerInterval: null,
      toast: null,
    };
  },
  computed: {
    today() {
      return this.isoDate(new Date());
    },
    isCurrentPeriodSelected() {
      if (this.scheduleViewMode === 'day') {
        return this.selectedDate === this.today;
      }
      const currentMonday = this.getMonday(new Date());
      return this.weekStart.getTime() === currentMonday.getTime();
    },
    weekDays() {
      const labels = ['Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy', 'Chủ nhật'];
      return Array.from({ length: 7 }, (_, index) => {
        const date = new Date(this.weekStart);
        date.setDate(date.getDate() + index);
        const iso = this.isoDate(date);
        return {
          iso,
          label: labels[index],
          date: String(date.getDate()).padStart(2, '0'),
          schedules: this.schedules.filter((schedule) => schedule.date === iso),
        };
      });
    },
    weekLabel() {
      const end = new Date(this.weekStart);
      end.setDate(end.getDate() + 6);
      const format = new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
      return `${format.format(this.weekStart)} – ${format.format(end)}`;
    },
    selectedDaySchedules() {
      return this.schedules.filter((schedule) => schedule.date === this.selectedDate);
    },
    sortedSelectedDaySchedules() {
      return [...this.selectedDaySchedules].sort((a, b) => {
        return (a.start_time || '').localeCompare(b.start_time || '');
      });
    },
    formattedSelectedDate() {
      try {
        const d = new Date(`${this.selectedDate}T00:00:00`);
        return new Intl.DateTimeFormat('vi-VN', {
          weekday: 'long',
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
        }).format(d);
      } catch (e) {
        return this.selectedDate;
      }
    },
  },
  watch: {
    selectedDate(newDate) {
      const dateMonday = this.getMonday(new Date(newDate));
      if (dateMonday.getTime() !== this.weekStart.getTime()) {
        this.weekStart = dateMonday;
        this.loadSchedules();
      }
    },
  },
  mounted() {
    try {
      this.toast = useToast();
    } catch (e) {
      this.toast = null;
    }
    this.loadSchedules();
    this.timerInterval = setInterval(() => {
      this.nowTime = new Date();
    }, 1000);
  },
  beforeUnmount() {
    if (this.timerInterval) clearInterval(this.timerInterval);
  },
  methods: {
    getMonday(date) {
      const value = new Date(date);
      const offset = value.getDay() === 0 ? -6 : 1 - value.getDay();
      value.setDate(value.getDate() + offset);
      value.setHours(0, 0, 0, 0);
      return value;
    },
    isoDate(date) {
      const offset = date.getTimezoneOffset();
      return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 10);
    },
    shiftWeek(amount) {
      const next = new Date(this.weekStart);
      next.setDate(next.getDate() + amount * 7);
      this.weekStart = next;
      this.loadSchedules();
    },
    goToCurrentWeek() {
      const currentMonday = this.getMonday(new Date());
      this.weekStart = currentMonday;
      this.selectedDate = this.today;
      this.loadSchedules();
    },
    shiftDay(amount) {
      const next = new Date(`${this.selectedDate}T00:00:00`);
      next.setDate(next.getDate() + amount);
      this.selectedDate = this.isoDate(next);
    },
    goToToday() {
      this.selectedDate = this.today;
    },
    async loadSchedules() {
      this.loading = true;
      this.error = '';
      try {
        const end = new Date(this.weekStart);
        end.setDate(end.getDate() + 6);
        const response = await ownerStaffShiftService.mySchedules({
          start_date: this.isoDate(this.weekStart),
          end_date: this.isoDate(end),
        });
        this.schedules = (response.data || []).sort((a, b) => `${a.date} ${a.start_time}`.localeCompare(`${b.date} ${b.start_time}`));
      } catch (error) {
        this.error = error.message || 'Không thể tải lịch làm việc.';
      } finally {
        this.loading = false;
      }
    },
    shiftName(schedule) {
      if (!schedule) return 'Ca trực';
      return schedule.shift?.name || 'Ca đặc biệt';
    },
    timeRange(schedule) {
      if (!schedule) return '';
      return `${String(schedule.start_time || '').slice(0, 5)} - ${String(schedule.end_time || '').slice(0, 5)}`;
    },
    calculateDuration(schedule) {
      if (!schedule?.start_time || !schedule?.end_time) return '';
      const [sh, sm] = schedule.start_time.split(':').map(Number);
      let [eh, em] = schedule.end_time.split(':').map(Number);
      let startM = sh * 60 + sm;
      let endM = eh * 60 + em;
      if (endM <= startM && endM !== 0) endM += 24 * 60;
      const diffM = Math.max(0, endM - startM);
      const hours = (diffM / 60).toFixed(1).replace('.0', '');
      return `${hours} giờ`;
    },
    formatTimeOnly(dateTimeStr) {
      if (!dateTimeStr) return '';
      try {
        const d = new Date(dateTimeStr);
        return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
      } catch (e) {
        return dateTimeStr;
      }
    },
    statusLabel(status) {
      return (
        {
          scheduled: 'Đã lên lịch',
          checked_in: 'Đang trực',
          checked_out: 'Đã hoàn thành',
          absent: 'Vắng mặt',
          cancelled: 'Đã hủy',
        }[status] || 'Đã lên lịch'
      );
    },
    canCheckIn(schedule) {
      if (!schedule) return false;
      if (schedule.date !== this.today) return false;

      const [startHour, startMin] = (schedule.start_time || '00:00').split(':').map(Number);
      const shiftStart = new Date();
      shiftStart.setHours(startHour, startMin, 0, 0);

      const earlyLimit = new Date(shiftStart.getTime() - 30 * 60000);
      return this.nowTime >= earlyLimit;
    },
    liveDuration(checkInAt) {
      if (!checkInAt) return '00:00:00';
      const checkInTime = new Date(checkInAt);
      const diffMs = this.nowTime - checkInTime;
      if (diffMs < 0) return '00:00:00';

      const diffSecs = Math.floor(diffMs / 1000);
      const hours = Math.floor(diffSecs / 3600);
      const minutes = Math.floor((diffSecs % 3600) / 60);
      const seconds = diffSecs % 60;

      return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    },
    async handleCheckIn(scheduleId) {
      this.actionLoading = scheduleId;
      this.error = '';
      this.successMsg = '';
      try {
        await ownerStaffShiftService.checkIn(scheduleId);
        const msg = 'Check-in thành công.';
        this.successMsg = msg;
        if (this.toast) this.toast.success(msg);
        await this.loadSchedules();
      } catch (err) {
        const errorMsg = err.message || 'Không thể thực hiện check-in.';
        this.error = errorMsg;
        if (this.toast) this.toast.error(errorMsg);
      } finally {
        this.actionLoading = null;
      }
    },
    handleCheckOut(scheduleId) {
      this.handoverScheduleId = scheduleId;
      this.showHandoverModal = true;
    },
    async onShiftCheckedOut() {
      this.showHandoverModal = false;
      const msg = 'Check-out hoàn thành ca trực thành công.';
      this.successMsg = msg;
      if (this.toast) this.toast.success(msg);
      await this.loadSchedules();
      window.dispatchEvent(new CustomEvent('staff-attendance-updated'));
    },
  },
};
</script>

<style scoped>
.staff-schedule-container {
  padding: 20px;
  background: #ffffff;
  min-height: calc(100vh - 60px);
  color: #111827;
  font-weight: 400;
}

/* TOP HEADER & NAVIGATION */
.schedule-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.view-switch {
  display: inline-flex;
  align-items: center;
  background: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 2px;
}

.switch-btn {
  background: transparent;
  border: none;
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 400;
  color: #374151;
  border-radius: 4px;
  cursor: pointer;
}

.switch-btn.active {
  background: #087642;
  color: #ffffff;
}

.schedule-period-title {
  font-size: 14.5px;
  font-weight: 500;
  color: #111827;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.date-navigator {
  display: inline-flex;
  align-items: center;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 2px;
}

.nav-btn {
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: none;
  color: #374151;
  cursor: pointer;
  border-radius: 4px;
}

.nav-btn:hover {
  background: #f0fdf4;
  color: #087642;
}

.nav-today-btn {
  background: transparent;
  border: none;
  padding: 0 10px;
  height: 28px;
  font-size: 13px;
  font-weight: 400;
  color: #374151;
  cursor: pointer;
  border-radius: 4px;
}

.nav-today-btn.is-current {
  background: #087642;
  color: #ffffff;
}

.refresh-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: transparent;
  border: 1px solid #d1d5db;
  padding: 0 12px;
  height: 32px;
  font-size: 13px;
  font-weight: 400;
  color: #374151;
  border-radius: 6px;
  cursor: pointer;
}

.refresh-btn:hover {
  background: #f0fdf4;
  color: #087642;
}

/* NOTIFICATIONS */
.notice-msg {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border-radius: 6px;
  font-size: 13px;
  margin-bottom: 16px;
}

.notice-msg.error {
  background: #fee2e2;
  color: #b91c1c;
}

.notice-msg.success {
  background: #dcfce7;
  color: #15803d;
}

.notice-close {
  background: transparent;
  border: none;
  cursor: pointer;
  color: inherit;
  font-size: 14px;
}

/* LOADING */
.schedule-loading {
  padding: 40px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  font-size: 13.5px;
  color: #374151;
}

.loading-spinner {
  width: 24px;
  height: 24px;
  border: 2px solid #e5e7eb;
  border-top-color: #087642;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* 1. WEEK VIEW */
.week-layout {
  display: flex;
  flex-direction: column;
}

.week-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 12px;
}

.day-column {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-height: 260px;
  background: #ffffff;
}

.day-column.is-today {
  border-color: #087642;
}

.day-column-head {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding-bottom: 8px;
}

.day-name {
  font-size: 11.5px;
  color: #6b7280;
}

.day-date {
  font-size: 16px;
  font-weight: 500;
  color: #111827;
}

.day-shifts-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

.shift-entry {
  padding: 10px;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  display: flex;
  flex-direction: column;
  gap: 3px;
  background: #ffffff;
}

.shift-entry.checked_in {
  border-left: 3px solid #087642;
}

.shift-entry.scheduled {
  border-left: 3px solid #2563eb;
}

.shift-entry.checked_out {
  border-left: 3px solid #9ca3af;
}

.shift-time {
  font-size: 11.5px;
  color: #087642;
}

.shift-name {
  font-size: 13px;
  font-weight: 500;
  color: #111827;
}

.shift-venue {
  font-size: 11.5px;
  color: #4b5563;
}

.shift-status {
  font-size: 11px;
  margin-top: 2px;
}

.shift-status.checked_in { color: #087642; }
.shift-status.scheduled { color: #2563eb; }
.shift-status.checked_out { color: #4b5563; }

.shift-notes-text {
  font-size: 11px;
  color: #6b7280;
  margin-top: 2px;
}

.shift-actions {
  margin-top: 6px;
}

.action-btn {
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 11.5px;
  font-weight: 400;
  border: none;
  cursor: pointer;
  width: 100%;
}

.action-btn.checkin {
  background: #087642;
  color: #ffffff;
}

.action-btn.checkin:disabled {
  background: #e5e7eb;
  color: #9ca3af;
  cursor: not-allowed;
}

.action-btn.checkout {
  background: #dc2626;
  color: #ffffff;
}

.no-shift-msg {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  font-size: 11.5px;
  color: #9ca3af;
}

/* 2. DAY VIEW */
.day-layout {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.day-nav-bar {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 8px;
}

.day-nav-item {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  cursor: pointer;
}

.day-nav-item.active {
  background: #087642;
  color: #ffffff;
  border-color: #087642;
}

.day-nav-item.active .d-label,
.day-nav-item.active .d-date {
  color: #ffffff;
}

.d-label {
  font-size: 11.5px;
  color: #6b7280;
}

.d-date {
  font-size: 15px;
  font-weight: 500;
  color: #111827;
}

.day-shifts-container {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.day-shift-row {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  background: #ffffff;
}

.day-shift-row.checked_in {
  border-left: 3px solid #087642;
}

.day-shift-row.scheduled {
  border-left: 3px solid #2563eb;
}

.day-shift-row.checked_out {
  border-left: 3px solid #9ca3af;
}

.shift-main-info {
  display: flex;
  align-items: flex-start;
  gap: 20px;
}

.shift-time-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 110px;
}

.time-text {
  font-size: 13.5px;
  color: #087642;
  font-weight: 500;
}

.duration-text {
  font-size: 11.5px;
  color: #6b7280;
}

.shift-desc-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.name-text {
  font-size: 14px;
  font-weight: 500;
  color: #111827;
}

.venue-text {
  font-size: 12.5px;
  color: #4b5563;
}

.notes-text {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}

.logs-text {
  font-size: 11.5px;
  color: #4b5563;
  margin-top: 2px;
}

.shift-side-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
}

.status-text {
  font-size: 12.5px;
}

.status-text.checked_in { color: #087642; }
.status-text.scheduled { color: #2563eb; }
.status-text.checked_out { color: #4b5563; }

.live-timer-text {
  font-size: 11.5px;
  color: #087642;
}

.shift-btn-wrap {
  margin-top: 4px;
}

.day-empty-msg {
  padding: 30px;
  text-align: center;
  font-size: 13px;
  color: #6b7280;
}

/* RESPONSIVE */
@media (max-width: 992px) {
  .week-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .day-nav-bar {
    grid-template-columns: repeat(4, 1fr);
  }
}

@media (max-width: 640px) {
  .staff-schedule-container {
    padding: 12px;
  }
  .week-grid {
    grid-template-columns: 1fr;
  }
  .day-nav-bar {
    grid-template-columns: repeat(2, 1fr);
  }
  .day-shift-row {
    flex-direction: column;
    align-items: flex-start;
  }
  .shift-side-info {
    align-items: flex-start;
    width: 100%;
  }
  .shift-btn-wrap {
    width: 100%;
  }
}
</style>


