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

    <!-- PERSONAL ATTENDANCE STATS (Compact Structured Metric Bar) -->
    <div class="attendance-metric-strip">
      <div class="metric-item">
        <span class="metric-label">Tổng giờ tuần</span>
        <span class="metric-value text-forest">{{ totalHoursWorkedLabel }}</span>
      </div>

      <div class="metric-divider"></div>

      <div class="metric-item">
        <span class="metric-label">Đã hoàn thành</span>
        <span class="metric-value text-blue">
          {{ completedShiftsCount }} <span class="metric-total">/ {{ totalShiftsThisWeek }} ca</span>
        </span>
      </div>

      <div class="metric-divider"></div>

      <div class="metric-item">
        <span class="metric-label">Sắp tới &amp; Trực</span>
        <span class="metric-value text-amber">{{ activeOrUpcomingCount }} <span class="metric-total">ca</span></span>
      </div>
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

                <!-- Live or completed attendance log pill -->
                <div v-if="sch.check_in_at || sch.check_out_at" class="shift-attend-pill">
                  <div v-if="sch.check_in_at" class="attend-log-line">
                    <span class="log-label">Vào:</span>
                    <strong class="log-val">{{ formatTimeOnly(sch.check_in_at) }}</strong>
                  </div>
                  <div v-if="sch.check_out_at" class="attend-log-line">
                    <span class="log-label">Ra:</span>
                    <strong class="log-val">{{ formatTimeOnly(sch.check_out_at) }}</strong>
                  </div>
                  <div v-if="sch.check_in_at && sch.check_out_at" class="attend-log-duration">
                    <span>Thực tế: {{ workedDurationText(sch) }}</span>
                  </div>
                </div>

                <div v-if="sch.notes" class="shift-notes-text">
                  {{ sch.notes }}
                </div>

                <!-- Action Button if applicable -->
                <div class="shift-actions">
                  <button
                    v-if="sch.status === 'scheduled' && sch.date === today"
                    type="button"
                    class="action-btn checkin"
                    :disabled="!canCheckIn(sch) || actionLoading === sch.id"
                    @click="handleCheckIn(sch.id)"
                  >
                    {{ actionLoading === sch.id ? 'Đang xử lý...' : 'Vào ca' }}
                  </button>

                  <button
                    v-if="sch.status === 'checked_in' && sch.date === today"
                    type="button"
                    class="action-btn checkout"
                    :disabled="actionLoading === sch.id"
                    @click="handleCheckOut(sch.id)"
                  >
                    {{ actionLoading === sch.id ? 'Đang xử lý...' : 'Kết ca' }}
                  </button>

                  <button
                    v-if="sch.status === 'checked_out'"
                    type="button"
                    class="action-btn report"
                    title="Xem báo cáo doanh thu & phiếu bàn giao ca"
                    @click="openHandoverReport(sch.id)"
                  >
                    <AppIcon name="barChart2" :size="13" />
                    <span>Báo cáo ca</span>
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
                <span class="duration-text">Kế hoạch: {{ calculateDuration(sch) }}</span>
              </div>

              <div class="shift-desc-col">
                <div class="name-text">{{ shiftName(sch) }}</div>
                <div class="venue-text">{{ sch.venue_cluster?.name || 'Cụm sân' }}</div>

                <!-- Structured Attendance Log Box -->
                <div v-if="sch.check_in_at || sch.check_out_at" class="day-attend-box">
                  <div class="day-attend-item">
                    <span class="attend-dot is-in"></span>
                    <span>Vào ca: <strong>{{ formatTimeOnly(sch.check_in_at) }}</strong></span>
                  </div>
                  <div v-if="sch.check_out_at" class="day-attend-item">
                    <span class="attend-dot is-out"></span>
                    <span>Kết ca: <strong>{{ formatTimeOnly(sch.check_out_at) }}</strong></span>
                  </div>
                  <div v-if="sch.check_in_at && sch.check_out_at" class="day-attend-duration">
                    Đã làm: <strong>{{ workedDurationText(sch) }}</strong>
                  </div>
                </div>

                <div v-if="sch.notes" class="notes-text">
                  Ghi chú: {{ sch.notes }}
                </div>
              </div>
            </div>

            <div class="shift-side-info">
              <div class="status-text" :class="sch.status">
                {{ statusLabel(sch.status) }}
              </div>

              <div v-if="sch.status === 'checked_in'" class="live-timer-text">
                Đang trực: {{ liveDuration(sch.check_in_at) }}
              </div>

              <div class="shift-btn-wrap">
                <button
                  v-if="sch.status === 'scheduled' && sch.date === today"
                  type="button"
                  class="action-btn checkin"
                  :disabled="!canCheckIn(sch) || actionLoading === sch.id"
                  @click="handleCheckIn(sch.id)"
                >
                  {{ actionLoading === sch.id ? 'Đang xử lý...' : 'Vào ca' }}
                </button>

                <button
                  v-if="sch.status === 'checked_in' && sch.date === today"
                  type="button"
                  class="action-btn checkout"
                  :disabled="actionLoading === sch.id"
                  @click="handleCheckOut(sch.id)"
                >
                  {{ actionLoading === sch.id ? 'Đang xử lý...' : 'Kết ca' }}
                </button>

                <button
                  v-if="sch.status === 'checked_out'"
                  type="button"
                  class="action-btn report"
                  title="Xem báo cáo doanh thu & phiếu bàn giao ca"
                  @click="openHandoverReport(sch.id)"
                >
                  <AppIcon name="barChart2" :size="13" />
                  <span>Báo cáo ca</span>
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

    <!-- SHIFT HANDOVER / REPORT MODAL -->
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
import { BUSINESS_TIMEZONE, addCalendarDays, businessDateString, businessDateTime } from '../../utils/businessTime.js';
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
      selectedDate: businessDateString(),
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
      return businessDateString();
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
    totalShiftsThisWeek() {
      return this.schedules.length;
    },
    completedShiftsCount() {
      return this.schedules.filter((s) => s.status === 'checked_out').length;
    },
    activeOrUpcomingCount() {
      return this.schedules.filter((s) => s.status === 'scheduled' || s.status === 'checked_in').length;
    },
    totalMinutesWorkedThisWeek() {
      return this.schedules.reduce((acc, sch) => {
        if (!sch.check_in_at) return acc;
        const start = new Date(sch.check_in_at);
        const end = sch.check_out_at ? new Date(sch.check_out_at) : new Date(this.nowTime);
        const diff = Math.max(0, Math.floor((end - start) / 60000));
        return acc + diff;
      }, 0);
    },
    totalHoursWorkedLabel() {
      const mins = this.totalMinutesWorkedThisWeek;
      const h = Math.floor(mins / 60);
      const m = mins % 60;
      if (h === 0 && m === 0) return '0 phút';
      if (h === 0) return `${m} phút`;
      if (m === 0) return `${h} giờ`;
      return `${h} giờ ${m} phút`;
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
    workedDurationText(schedule) {
      if (!schedule?.check_in_at) return '';
      const start = new Date(schedule.check_in_at);
      const end = schedule.check_out_at ? new Date(schedule.check_out_at) : new Date(this.nowTime);
      const diffM = Math.max(0, Math.floor((end - start) / 60000));
      const h = Math.floor(diffM / 60);
      const m = diffM % 60;
      if (h === 0 && m === 0) return '0 phút';
      if (h === 0) return `${m} phút`;
      if (m === 0) return `${h} giờ`;
      return `${h} giờ ${m} phút`;
    },
    openHandoverReport(scheduleId) {
      this.handoverScheduleId = scheduleId;
      this.showHandoverModal = true;
    },
    getMonday(date) {
      const [year, month, day] = businessDateString(date).split('-').map(Number);
      const value = new Date(year, month - 1, day, 12);
      const offset = value.getDay() === 0 ? -6 : 1 - value.getDay();
      value.setDate(value.getDate() + offset);
      value.setHours(0, 0, 0, 0);
      return value;
    },
    isoDate(date) {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
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
      this.selectedDate = addCalendarDays(this.selectedDate, amount);
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
        return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', timeZone: BUSINESS_TIMEZONE });
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

      const shiftStart = businessDateTime(schedule.date, schedule.start_time || '00:00');
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
  color: #0f172a;
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
  gap: 18px;
  flex-wrap: wrap;
}

.view-switch {
  display: inline-flex;
  align-items: center;
  background: #f1f5f9;
  border: none;
  border-radius: 8px;
  padding: 3px;
}

.switch-btn {
  background: transparent;
  border: none;
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 500;
  color: #475569;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.switch-btn:hover {
  color: #0f172a;
}

.switch-btn.active {
  background: #ffffff;
  color: #166534;
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.schedule-period-title {
  font-size: 15px;
  font-weight: 600;
  color: #0f172a;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.date-navigator {
  display: inline-flex;
  align-items: center;
  background: #f1f5f9;
  border: none;
  border-radius: 8px;
  padding: 2px;
}

.nav-btn {
  width: 30px;
  height: 30px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: none;
  color: #475569;
  cursor: pointer;
  border-radius: 6px;
  transition: all 0.15s ease;
}

.nav-btn:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.nav-today-btn {
  background: transparent;
  border: none;
  padding: 0 12px;
  height: 30px;
  font-size: 13px;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  border-radius: 6px;
  transition: all 0.15s ease;
}

.nav-today-btn:hover {
  color: #0f172a;
}

.nav-today-btn.is-current {
  background: #ffffff;
  color: #166534;
  font-weight: 600;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}

.refresh-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: transparent;
  border: none;
  padding: 6px 12px;
  height: 34px;
  font-size: 13px;
  font-weight: 600;
  color: #166534;
  border-radius: 7px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.refresh-btn:hover {
  background: #f4f8f5;
  color: #14532d;
}

/* NOTIFICATIONS */
.notice-msg {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 13px;
  margin-bottom: 16px;
}

.notice-msg.error {
  background: #fee2e2;
  color: #b91c1c;
  border: 1px solid #fca5a5;
}

.notice-msg.success {
  background: #dcfce7;
  color: #15803d;
  border: 1px solid #bbf7d0;
}

.notice-close {
  background: transparent;
  border: none;
  cursor: pointer;
  color: inherit;
  font-size: 14px;
}

/* PERSONAL ATTENDANCE STATS (Compact Structured Metric Bar) */
.attendance-metric-strip {
  display: inline-flex;
  align-items: center;
  background: #f8fafc;
  border-radius: 10px;
  padding: 10px 20px;
  gap: 24px;
  margin-bottom: 20px;
}

.metric-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.metric-label {
  font-size: 11.5px;
  font-weight: 500;
  color: #64748b;
  letter-spacing: 0.2px;
}

.metric-value {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
}

.metric-value.text-forest {
  color: #166534;
}

.metric-value.text-blue {
  color: #0284c7;
}

.metric-value.text-amber {
  color: #d97706;
}

.metric-total {
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
}

.metric-divider {
  width: 1px;
  height: 28px;
  background: #e2e8f0;
}

/* LOADING */
.schedule-loading {
  padding: 50px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  font-size: 13.5px;
  color: #475569;
}

.loading-spinner {
  width: 24px;
  height: 24px;
  border: 2px solid #e2e8f0;
  border-top-color: #166534;
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
  background: #f8fafc;
  border: none;
  border-radius: 12px;
  padding: 14px 10px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-height: 320px;
  transition: all 0.15s ease;
}

.day-column.is-today {
  background: #f4f8f5;
  border: 1px solid #cbdcd0;
  box-shadow: 0 4px 12px rgba(22, 101, 52, 0.05);
}

.day-column-head {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding-bottom: 4px;
  border-bottom: 1px solid rgba(226, 232, 240, 0.6);
}

.day-column.is-today .day-column-head {
  border-bottom-color: rgba(203, 220, 208, 0.6);
}

.day-name {
  font-size: 12px;
  font-weight: 500;
  color: #475569;
}

.day-date {
  font-size: 18px;
  font-weight: 600;
  color: #0f172a;
}

.day-column.is-today .day-date {
  color: #166534;
  font-weight: 700;
}

.day-shifts-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  flex: 1;
}

.shift-entry {
  padding: 12px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  transition: all 0.15s ease;
}

.shift-entry:hover {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
}

.shift-entry.checked_in {
  border-color: #cbdcd0;
}

.shift-entry.scheduled {
  border-color: #e2e8f0;
}

.shift-entry.checked_out {
  border-color: #e2e8f0;
  opacity: 0.92;
}

.shift-time {
  font-size: 12px;
  font-weight: 600;
  color: #166534;
  font-family: ui-monospace, SFMono-Regular, monospace;
}

.shift-name {
  font-size: 13.5px;
  font-weight: 600;
  color: #0f172a;
}

.shift-venue {
  font-size: 12px;
  color: #475569;
}

.shift-status {
  display: inline-block;
  align-self: flex-start;
  font-size: 11px;
  font-weight: 500;
  padding: 2px 6px;
  border-radius: 4px;
  margin-top: 2px;
}

.shift-status.checked_in {
  background: #eaf3ed;
  color: #166534;
}

.shift-status.scheduled {
  background: #e0f2fe;
  color: #0369a1;
}

.shift-status.checked_out {
  background: #f1f5f9;
  color: #475569;
}

/* SHIFT ATTENDANCE LOG PILL */
.shift-attend-pill {
  margin-top: 4px;
  padding: 6px 8px;
  background: #f8fafc;
  border-radius: 6px;
  font-size: 11.5px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.attend-log-line {
  display: flex;
  align-items: center;
  gap: 4px;
  color: #334155;
}

.attend-log-line .log-label {
  color: #64748b;
  font-size: 11px;
}

.attend-log-line .log-val {
  color: #0f172a;
  font-family: ui-monospace, SFMono-Regular, monospace;
}

.attend-log-duration {
  font-size: 11px;
  color: #166534;
  font-weight: 500;
  margin-top: 1px;
}

.shift-notes-text {
  font-size: 11.5px;
  color: #64748b;
  margin-top: 2px;
  font-style: italic;
}

.shift-actions {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.action-btn {
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  width: 100%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  transition: all 0.15s ease;
}

.action-btn.checkin {
  background: #166534;
  color: #ffffff;
}

.action-btn.checkin:hover:not(:disabled) {
  background: #14532d;
}

.action-btn.checkin:disabled {
  background: #e2e8f0;
  color: #94a3b8;
  cursor: not-allowed;
}

.action-btn.checkout {
  background: #dc2626;
  color: #ffffff;
}

.action-btn.checkout:hover:not(:disabled) {
  background: #b91c1c;
}

.action-btn.report {
  background: #f1f5f9;
  color: #166534;
  border: 1px solid #e2e8f0;
}

.action-btn.report:hover {
  background: #eaf3ed;
  color: #14532d;
  border-color: #cbdcd0;
}

.no-shift-msg {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  font-size: 12px;
  color: #94a3b8;
}

/* 2. DAY VIEW */
.day-layout {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.day-nav-bar {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 10px;
}

.day-nav-item {
  background: #f8fafc;
  border: none;
  border-radius: 10px;
  padding: 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.day-nav-item:hover {
  background: #f1f5f9;
}

.day-nav-item.active {
  background: #166534;
  color: #ffffff;
  box-shadow: 0 4px 10px rgba(22, 101, 52, 0.2);
}

.day-nav-item.active .d-label,
.day-nav-item.active .d-date {
  color: #ffffff;
}

.d-label {
  font-size: 12px;
  color: #475569;
  font-weight: 500;
}

.d-date {
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
}

.day-shifts-container {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.day-shift-row {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  background: #ffffff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  transition: all 0.15s ease;
}

.day-shift-row:hover {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.06);
}

.day-shift-row.checked_in {
  border-color: #cbdcd0;
}

.day-shift-row.scheduled {
  border-color: #e2e8f0;
}

.day-shift-row.checked_out {
  border-color: #e2e8f0;
  opacity: 0.92;
}

.shift-main-info {
  display: flex;
  align-items: flex-start;
  gap: 24px;
}

.shift-time-col {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 120px;
}

.time-text {
  font-size: 14px;
  color: #166534;
  font-weight: 600;
  font-family: ui-monospace, SFMono-Regular, monospace;
}

.duration-text {
  font-size: 12px;
  color: #64748b;
}

.shift-desc-col {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.name-text {
  font-size: 15px;
  font-weight: 600;
  color: #0f172a;
}

.venue-text {
  font-size: 13px;
  color: #475569;
}

.day-attend-box {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  padding: 6px 10px;
  background: #f8fafc;
  border-radius: 6px;
  margin-top: 4px;
  font-size: 12px;
}

.day-attend-item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #475569;
}

.day-attend-item strong {
  color: #0f172a;
  font-family: ui-monospace, SFMono-Regular, monospace;
}

.attend-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.attend-dot.is-in {
  background: #166534;
}

.attend-dot.is-out {
  background: #0284c7;
}

.day-attend-duration {
  font-size: 12px;
  color: #166534;
  font-weight: 500;
}

.notes-text {
  font-size: 12.5px;
  color: #64748b;
  margin-top: 2px;
  font-style: italic;
}

.shift-side-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.status-text {
  display: inline-block;
  font-size: 12px;
  font-weight: 500;
  padding: 3px 8px;
  border-radius: 4px;
}

.status-text.checked_in { background: #eaf3ed; color: #166534; }
.status-text.scheduled { background: #e0f2fe; color: #0369a1; }
.status-text.checked_out { background: #f1f5f9; color: #475569; }

.live-timer-text {
  font-size: 12px;
  color: #166534;
  font-weight: 500;
}

.shift-btn-wrap {
  margin-top: 4px;
}

.day-empty-msg {
  padding: 40px;
  text-align: center;
  font-size: 13.5px;
  color: #64748b;
  background: #f8fafc;
  border-radius: 10px;
}

/* RESPONSIVE */
@media (max-width: 992px) {
  .staff-schedule-container {
    padding: 14px 16px 72px 16px;
  }
  .week-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .day-nav-bar {
    grid-template-columns: repeat(4, 1fr);
  }
}

@media (max-width: 640px) {
  .staff-schedule-container {
    padding: 10px 12px 70px 12px;
  }
  .schedule-header {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
  }
  .header-left,
  .header-right {
    width: 100%;
    justify-content: space-between;
  }
  .view-mode-toggle {
    flex: 1;
  }
  .mode-btn {
    flex: 1;
    justify-content: center;
  }
  .nav-group {
    flex: 1;
    justify-content: center;
  }

  /* FLUID 3-COLUMN METRIC STRIP (ZERO OVERFLOW ON MOBILE) */
  .attendance-metric-strip {
    display: grid;
    grid-template-columns: 1fr auto 1fr auto 1fr;
    align-items: center;
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    gap: 8px;
    margin-bottom: 16px;
  }

  .metric-item {
    min-width: 0;
  }

  .metric-label {
    font-size: 11px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .metric-value {
    font-size: 14.5px;
    white-space: nowrap;
  }

  .metric-total {
    font-size: 11.5px;
  }

  .metric-divider {
    height: 24px;
  }

  .week-grid {
    grid-template-columns: 1fr;
  }
  .day-nav-bar {
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
  }
  .day-shift-row {
    flex-direction: column;
    align-items: flex-start;
  }
  .shift-main-info {
    flex-direction: column;
    gap: 8px;
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
