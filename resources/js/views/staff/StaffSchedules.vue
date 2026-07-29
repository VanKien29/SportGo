<template>
  <section class="staff-schedules-page">
    <header class="staff-schedules-head">
      <div class="staff-schedules-header-left">
        <div class="staff-view-switcher">
          <button
            type="button"
            :class="{ active: scheduleViewMode === 'week' }"
            @click="scheduleViewMode = 'week'"
          >Xem theo tuần</button>
          <button
            type="button"
            :class="{ active: scheduleViewMode === 'day' }"
            @click="scheduleViewMode = 'day'"
          >Xem theo ngày</button>
        </div>
        <p v-if="scheduleViewMode === 'week'" class="staff-week-label">{{ weekLabel }}</p>
        <p v-else class="staff-week-label">{{ formattedSelectedDate }}</p>
      </div>

      <!-- Điều hướng tuần (cho chế độ xem tuần) -->
      <div v-if="scheduleViewMode === 'week'" class="staff-week-actions">
        <button type="button" title="Tuần trước" @click="shiftWeek(-1)">
          <AppIcon name="chevronLeft" size="14" />
        </button>
        <button type="button" class="staff-week-today" @click="goToCurrentWeek">Tuần này</button>
        <button type="button" title="Tuần sau" @click="shiftWeek(1)">
          <AppIcon name="chevronRight" size="14" />
        </button>
      </div>

      <!-- Điều hướng ngày (cho chế độ xem ngày) -->
      <div v-if="scheduleViewMode === 'day'" class="staff-week-actions">
        <button type="button" title="Ngày trước" @click="shiftDay(-1)">
          <AppIcon name="chevronLeft" size="14" />
        </button>
        <button type="button" class="staff-week-today" @click="goToToday">Hôm nay</button>
        <button type="button" title="Ngày sau" @click="shiftDay(1)">
          <AppIcon name="chevronRight" size="14" />
        </button>
      </div>
    </header>

    <p v-if="error" class="staff-schedules-alert">{{ error }}</p>
    <div v-if="loading" class="staff-schedules-loading">Đang tải lịch trực...</div>

    <template v-else>
      <!-- CHẾ ĐỘ XEM THEO TUẦN -->
      <div v-if="scheduleViewMode === 'week'">
        <div class="staff-week-grid" role="list" aria-label="Lịch trực trong tuần">
          <article v-for="day in weekDays" :key="day.iso" class="staff-day" :class="{ 'is-today': day.iso === today }" role="listitem">
            <header>
              <span>{{ day.label }}</span>
              <strong>{{ day.date }}</strong>
            </header>
            <div v-if="day.schedules.length" class="staff-day-shifts">
              <div v-for="schedule in day.schedules" :key="schedule.id" class="staff-day-shift">
                <span class="staff-day-time">{{ timeRange(schedule) }}</span>
                <strong>{{ shiftName(schedule) }}</strong>
                <small>{{ schedule.venue_cluster?.name || 'Cụm sân' }}</small>
                <span class="staff-day-status" :class="schedule.status">{{ statusLabel(schedule.status) }}</span>
              </div>
            </div>
            <p v-else>Không có ca</p>
          </article>
        </div>

        <section v-if="schedules.length" class="staff-schedules-list">
          <h2>Tất cả ca trong tuần</h2>
          <article v-for="schedule in schedules" :key="schedule.id" class="staff-schedule-item">
            <time>{{ fullDate(schedule.date) }}</time>
            <div>
              <strong>{{ timeRange(schedule) }} · {{ shiftName(schedule) }}</strong>
              <span>{{ schedule.venue_cluster?.name || 'Cụm sân được phân công' }}</span>
              <small v-if="schedule.notes">{{ schedule.notes }}</small>
            </div>
            <span class="staff-day-status" :class="schedule.status">{{ statusLabel(schedule.status) }}</span>
          </article>
        </section>
      </div>

      <!-- CHẾ ĐỘ XEM THEO NGÀY (Dạng timeline 24h) -->
      <div v-else class="staff-day-view-container">

        <!-- Timeline Board -->
        <div class="shift-timeline-layout">
          <div class="timeline-board">
            <div class="timeline-scroller">
              <div class="timeline-axis">
                <div class="axis-staff">Lịch biểu</div>
                <div class="axis-track">
                  <span
                    v-for="tick in timelineTicks"
                    :key="tick.label"
                    class="axis-tick"
                    :style="{ left: `${tick.left}%` }"
                  >
                    {{ tick.label }}
                  </span>
                </div>
              </div>

              <article class="timeline-row">
                <div class="staff-meta">
                  <strong>Hôm nay</strong>
                  <span>{{ selectedDaySchedules.length }} ca trực</span>
                </div>
                <div class="timeline-track">
                  <span
                    v-for="tick in timelineTicks"
                    :key="`grid-${tick.label}`"
                    class="track-gridline"
                    :style="{ left: `${tick.left}%` }"
                  ></span>
                  <div
                    v-for="block in todayTimelineBlocks"
                    :key="block.id"
                    class="timeline-block"
                    :class="block.statusClass"
                    :style="block.style"
                    :title="`Trạng thái: ${block.statusLabel}. Ghi chú: ${block.notes}`"
                  >
                    <strong>{{ block.title }}</strong>
                    <span class="block-time">{{ block.timeLabel }} · {{ block.venueName }}</span>
                  </div>
                </div>
              </article>
            </div>
          </div>
        </div>

        <!-- Chi tiết ca trực bên dưới -->
        <section v-if="selectedDaySchedules.length" class="staff-day-schedules-list">
          <h4 class="staff-detail-title">Chi tiết ca trực</h4>
          <article v-for="sch in selectedDaySchedules" :key="`detail-${sch.id}`" class="staff-day-shift-row">
            <div class="staff-day-shift-main">
              <div class="staff-day-shift-title-line">
                <strong class="staff-day-shift-name">{{ shiftName(sch) }}</strong>
                <span class="staff-day-shift-venue">{{ sch.venue_cluster?.name || 'Cụm sân được phân công' }}</span>
              </div>
              <span class="staff-day-shift-time">Thời gian: {{ timeRange(sch) }}</span>
              <p v-if="sch.notes" class="staff-day-shift-notes">
                <strong>Ghi chú:</strong> {{ sch.notes }}
              </p>
            </div>
            <div class="staff-day-shift-status">
              <span class="staff-day-status" :class="sch.status">{{ statusLabel(sch.status) }}</span>
            </div>
          </article>
        </section>
        <p v-else class="staff-empty-text">Không có ca trực nào trong ngày này.</p>
      </div>
    </template>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { ownerStaffShiftService } from '../../services/ownerStaffShiftService.js';

export default {
  name: 'StaffSchedules',
  components: { AppIcon },
  data() {
    return {
      weekStart: this.getMonday(new Date()),
      schedules: [],
      loading: true,
      error: '',
      scheduleViewMode: 'week',
      selectedDate: this.isoDate(new Date()),
      selectedWeekDate: this.isoDate(new Date()),
    };
  },
  computed: {
    today() {
      return this.isoDate(new Date());
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
      return `${format.format(this.weekStart)} - ${format.format(end)}`;
    },
    uniqueVenues() {
      return new Set(this.schedules.map((schedule) => schedule.venue_cluster?.id || schedule.venue_cluster?.name).filter(Boolean)).size;
    },
    selectedDaySchedules() {
      return this.schedules.filter((schedule) => schedule.date === this.selectedDate);
    },
    formattedSelectedDate() {
      return new Intl.DateTimeFormat('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(this.selectedDate + 'T00:00:00'));
    },
    todayTimelineBlocks() {
      const parseTimeMins = (timeStr) => {
        if (!timeStr) return 0;
        const parts = timeStr.split(':');
        const h = parseInt(parts[0], 10) || 0;
        const m = parseInt(parts[1], 10) || 0;
        return h * 60 + m;
      };

      const totalMins = 24 * 60;
      return this.selectedDaySchedules.map((sch) => {
        const startMins = parseTimeMins(sch.start_time);
        const endMins = parseTimeMins(sch.end_time);
        const left = (startMins / totalMins) * 100;
        const width = ((endMins - startMins) / totalMins) * 100;
        return {
          id: sch.id,
          title: sch.shift?.name || 'Ca trực',
          timeLabel: `${sch.start_time.substring(0, 5)} - ${sch.end_time.substring(0, 5)}`,
          statusClass: sch.status,
          statusLabel: this.statusLabel(sch.status),
          notes: sch.notes || '',
          venueName: sch.venue_cluster?.name || 'Cụm sân',
          style: {
            left: `${left}%`,
            width: `${width}%`,
          },
        };
      });
    },
    timelineTicks() {
      const ticks = [];
      for (let h = 0; h <= 24; h += 2) {
        ticks.push({
          label: `${String(h).padStart(2, '0')}:00`,
          left: (h / 24) * 100,
        });
      }
      return ticks;
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
    this.loadSchedules();
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
      return new Date(date.getTime() - (offset * 60000)).toISOString().slice(0, 10);
    },
    shiftWeek(amount) {
      const next = new Date(this.weekStart);
      next.setDate(next.getDate() + (amount * 7));
      this.weekStart = next;
      this.selectedWeekDate = this.isoDate(next);
      this.loadSchedules();
    },
    goToCurrentWeek() {
      const currentMonday = this.getMonday(new Date());
      this.weekStart = currentMonday;
      this.selectedWeekDate = this.isoDate(currentMonday);
      this.loadSchedules();
    },
    onWeekDateChange(event) {
      const selected = new Date(event.target.value);
      if (!isNaN(selected.getTime())) {
        this.weekStart = this.getMonday(selected);
        this.loadSchedules();
      }
    },
    onWeekDateChangePicker(iso) {
      if (!iso) return;
      const selected = new Date(iso + 'T00:00:00');
      if (!isNaN(selected.getTime())) {
        this.weekStart = this.getMonday(selected);
        this.selectedWeekDate = iso;
        this.loadSchedules();
      }
    },
    shiftDay(amount) {
      const next = new Date(this.selectedDate + 'T00:00:00');
      next.setDate(next.getDate() + amount);
      this.selectedDate = this.isoDate(next);
    },
    goToToday() {
      this.selectedDate = this.isoDate(new Date());
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
        this.error = error.message || 'Không thể tải lịch trực. Vui lòng thử lại.';
      } finally {
        this.loading = false;
      }
    },
    shiftName(schedule) {
      return schedule.shift?.name || 'Ca trực';
    },
    timeRange(schedule) {
      return `${String(schedule.start_time || '').slice(0, 5)} - ${String(schedule.end_time || '').slice(0, 5)}`;
    },
    fullDate(value) {
      return new Intl.DateTimeFormat('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit' }).format(new Date(`${value}T00:00:00`));
    },
    statusLabel(status) {
      return { scheduled: 'Đã lên lịch', checked_in: 'Đang trực', checked_out: 'Đã hoàn thành', absent: 'Vắng mặt', cancelled: 'Đã hủy' }[status] || 'Đã lên lịch';
    },
  },
};
</script>

<style scoped>
.staff-schedules-page {
  max-width: 1120px;
  margin: 0 auto;
  color: var(--admin-text);
  padding: 16px 0;
}

.staff-schedules-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
}

.staff-schedules-header-left {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.staff-week-label {
  font-size: var(--admin-font-size-base, 14px) !important;
  font-weight: 500 !important;
  color: var(--admin-text) !important;
  margin: 0;
}
.staff-view-switcher {
  display: flex;
  gap: 8px;
  padding-bottom: 4px;
}

.staff-view-switcher button {
  background: transparent;
  border: none;
  color: var(--admin-muted);
  font-size: 14px;
  font-weight: 400;
  padding: 6px 12px;
  cursor: pointer;
}

.staff-view-switcher button.active {
  color: var(--admin-primary);
}

.staff-date-picker-input-mini {
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-text) !important;
  -webkit-text-fill-color: var(--admin-text) !important;
  padding: 4px 8px;
  font-size: var(--admin-font-size-xs, 11px) !important;
  font-weight: 500;
  height: 36px !important;
  box-sizing: border-box;
  color-scheme: light !important;
}

.staff-date-picker-input-mini::-webkit-datetime-edit {
  font-size: var(--admin-font-size-xs, 11px) !important;
}

:root[data-theme="dark"] .staff-date-picker-input-mini,
[data-theme="dark"] .staff-date-picker-input-mini {
  color-scheme: dark !important;
}

.staff-week-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.staff-week-actions button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  height: 36px !important;
  min-height: 36px !important;
  padding: 0 8px;
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-text);
  font-size: var(--admin-font-size-sm, 12px) !important;
  font-weight: 500;
  cursor: pointer;
}

.staff-week-today {
  font-size: var(--admin-font-size-sm, 12px) !important;
}

.staff-schedules-alert {
  margin: 0 0 16px;
  padding: 10px 12px;
  color: var(--admin-danger);
  background: var(--admin-danger-soft);
  border-radius: var(--admin-radius);
  font-size: 13px;
}

.staff-schedules-loading {
  min-height: 160px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--admin-muted);
  font-size: 13px;
}

/* Week grid styling */
.staff-week-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  border-top: 1px solid var(--admin-border-soft);
  border-bottom: 1px solid var(--admin-border-soft);
}

.staff-day {
  min-height: 200px;
  padding: 12px;
  border-right: 1px solid var(--admin-border-soft);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.staff-day:first-child {
  border-left: 1px solid var(--admin-border-soft);
}

.staff-day.is-today {
  background: var(--admin-bg-soft);
}

.staff-day header {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.staff-day header span {
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm, 12px);
}

.staff-day header strong {
  font-size: var(--admin-font-size-base, 14px);
  font-weight: 400;
  color: var(--admin-text);
}

.staff-day > p {
  color: var(--admin-faint);
  font-size: var(--admin-font-size-sm, 12px);
  margin: 0;
}

.staff-day-shifts {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.staff-day-shift {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.staff-day-time {
  font-size: var(--admin-font-size-sm, 12px);
  font-weight: 400;
  color: var(--admin-text);
}

.staff-day-shift strong {
  font-size: var(--admin-font-size-md, 13px);
  font-weight: 400;
  color: var(--admin-text);
}

.staff-day-shift small {
  font-size: var(--admin-font-size-sm, 12px);
  color: var(--admin-muted);
}

.staff-day-status {
  font-size: var(--admin-font-size-sm, 12px);
  font-weight: 400;
  margin-top: 2px;
}

.staff-day-status.scheduled {
  color: var(--admin-muted);
}

.staff-day-status.checked_in {
  color: var(--admin-primary-dark);
}

.staff-day-status.checked_out {
  color: var(--admin-muted);
}

.staff-day-status.absent {
  color: var(--admin-danger);
}

.staff-day-status.cancelled {
  color: var(--admin-muted);
}

.staff-schedules-list {
  margin-top: 28px;
}

.staff-schedules-list h2 {
  font-size: 16px;
  font-weight: 400;
  margin-bottom: 12px;
}

.staff-schedule-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 0;
  border-top: 1px solid var(--admin-border-soft);
}

.staff-schedule-item:last-child {
  border-bottom: 1px solid var(--admin-border-soft);
}

.staff-schedule-item time {
  width: 120px;
  color: var(--admin-text);
  font-size: 13px;
  text-transform: capitalize;
}

.staff-schedule-item > div {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.staff-schedule-item strong {
  font-size: 14px;
  font-weight: 400;
  color: var(--admin-text);
}

.staff-schedule-item span,
.staff-schedule-item small {
  color: var(--admin-muted);
  font-size: 12px;
}

/* Day View Styles */
.staff-day-view-container {
  padding: 16px 0;
}

.staff-day-view-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.staff-day-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.staff-day-title {
  font-size: 18px;
  font-weight: 400;
  color: var(--admin-text);
  margin: 0;
}

.staff-date-picker-input {
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-text) !important;
  -webkit-text-fill-color: var(--admin-text) !important;
  padding: 4px 8px;
  font-size: 13px;
  font-weight: 500;
  color-scheme: light !important;
}

:root[data-theme="dark"] .staff-date-picker-input,
[data-theme="dark"] .staff-date-picker-input {
  color-scheme: dark !important;
}

/* Shift Timeline View Styles */
.shift-timeline-layout {
  padding: 16px;
  background: var(--admin-surface);
  overflow-x: auto;
  margin-bottom: 24px;
  border-top: 1px solid var(--admin-border-soft);
  border-bottom: 1px solid var(--admin-border-soft);
}

.timeline-board {
  min-width: 900px;
}

.timeline-axis {
  display: flex;
  align-items: center;
  border-bottom: 2px solid var(--admin-border-soft);
  padding-bottom: 10px;
  margin-bottom: 10px;
}

.axis-staff {
  width: 180px;
  font-weight: 400;
  color: var(--admin-faint);
  font-size: 13px;
}

.axis-track {
  position: relative;
  flex: 1;
  height: 20px;
}

.axis-tick {
  position: absolute;
  transform: translateX(-50%);
  font-size: 11px;
  font-weight: 400;
  color: var(--admin-faint);
}

.timeline-row {
  display: flex;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px dashed var(--admin-border-soft);
}

.staff-meta {
  width: 180px;
  padding-right: 12px;
}

.staff-meta strong {
  display: block;
  font-size: 14px;
  color: var(--admin-text);
}

.staff-meta span {
  font-size: 11px;
  color: var(--admin-faint);
}

.timeline-track {
  position: relative;
  flex: 1;
  height: 54px;
  background: var(--admin-bg-soft);
  border-radius: 8px;
  border: 1px solid var(--admin-border-soft);
}

.track-gridline {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 1px;
  border-left: 1px dashed var(--admin-border-soft);
  opacity: 0.5;
}

.timeline-block {
  position: absolute;
  top: 4px;
  bottom: 4px;
  border-radius: 6px;
  padding: 2px 6px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  overflow: hidden;
  border: 1px solid transparent;
  box-sizing: border-box;
}

.timeline-block strong {
  font-size: 12px;
  font-weight: 400;
  display: block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
  line-height: 1.2;
  margin: 0;
}

.timeline-block .block-time {
  font-size: 11px;
  opacity: 0.85;
  white-space: nowrap;
  line-height: 1.2;
  margin: 0;
  margin-top: 2px;
}

/* Status colors matching theme variables dynamically */
.timeline-block.scheduled {
  background-color: var(--admin-primary-soft);
  border-color: color-mix(in srgb, var(--admin-primary) 30%, transparent);
  color: var(--admin-primary);
}

.timeline-block.checked_in {
  background-color: color-mix(in srgb, #22c55e 12%, transparent);
  border-color: color-mix(in srgb, #22c55e 35%, transparent);
  color: #15803d;
}

.timeline-block.checked_out {
  background-color: var(--admin-bg);
  border-color: var(--admin-border-soft);
  color: var(--admin-muted);
}

.timeline-block.absent {
  background-color: color-mix(in srgb, var(--admin-danger) 12%, transparent);
  border-color: color-mix(in srgb, var(--admin-danger) 35%, transparent);
  color: var(--admin-danger);
}

.timeline-block.cancelled {
  background-color: transparent;
  border-color: var(--admin-border-soft);
  color: var(--admin-muted);
  text-decoration: line-through;
  opacity: 0.6;
}

/* Detail list under timeline */
.staff-day-schedules-list {
  margin-top: 24px;
}

.staff-detail-title {
  font-size: 15px;
  font-weight: 400;
  margin-bottom: 12px;
  color: var(--admin-text);
}

.staff-day-shift-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 16px 0;
  border-bottom: 1px solid var(--admin-border-soft);
}

.staff-day-shift-row:first-child {
  border-top: 1px solid var(--admin-border-soft);
}

.staff-day-shift-main {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.staff-day-shift-title-line {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.staff-day-shift-name {
  font-size: 15px;
  font-weight: 400;
  color: var(--admin-text);
}

.staff-day-shift-venue {
  font-size: 12px;
  color: var(--admin-muted);
}

.staff-day-shift-time {
  font-size: 13px;
  font-weight: 400;
  color: var(--admin-text);
}

.staff-day-shift-notes {
  font-size: 12px;
  color: var(--admin-muted);
  margin-top: 4px;
}

.staff-day-shift-status {
  text-align: right;
}

.staff-empty-text {
  color: var(--admin-muted);
  font-size: 14px;
  margin: 0;
}

/* Responsive */
@media (max-width: 820px) {
  .staff-week-grid {
    grid-template-columns: 1fr;
  }

  .staff-day {
    min-height: auto;
    padding: 12px 0;
    border-right: none;
    border-left: none;
    border-bottom: 1px solid var(--admin-border-soft);
    flex-direction: row;
    align-items: flex-start;
    gap: 16px;
  }

  .staff-day:first-child {
    border-left: none;
  }

  .staff-day.is-today {
    background: transparent;
    border-left: 3px solid var(--admin-primary);
    padding-left: 8px;
  }

  .staff-day header {
    width: 60px;
    flex-shrink: 0;
  }

  .staff-day-shifts {
    flex: 1;
  }

  .staff-day-shift {
    display: grid;
    grid-template-columns: 80px 1fr;
    gap: 4px 12px;
  }

  .staff-day-time {
    grid-row: span 3;
  }

  .staff-schedules-head {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .staff-week-actions {
    width: 100%;
    justify-content: space-between;
  }

  .staff-week-actions button {
    flex: 1;
  }

  .staff-day-view-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .staff-day-title-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    width: 100%;
  }

  .staff-date-picker-input {
    width: 100%;
    box-sizing: border-box;
  }

  .staff-day-shift-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .staff-day-shift-status {
    text-align: left;
  }
}
</style>