<template>
  <div class="pos-schedule-view">
    <!-- 1. TOP HEADER & SWITCHER -->
    <header class="pos-schedule-head">
      <div class="pos-schedule-left">
        <div class="pos-view-tabs">
          <button
            type="button"
            class="pos-view-tab"
            :class="{ active: scheduleViewMode === 'week' }"
            @click="scheduleViewMode = 'week'"
          >
            <span>Xem theo tuần</span>
          </button>
          <button
            type="button"
            class="pos-view-tab"
            :class="{ active: scheduleViewMode === 'day' }"
            @click="scheduleViewMode = 'day'"
          >
            <span>Xem theo ngày</span>
          </button>
        </div>

        <h2 class="pos-schedule-title">
          {{ scheduleViewMode === 'week' ? weekLabel : formattedSelectedDate }}
        </h2>
      </div>

      <!-- Navigation buttons -->
      <div class="pos-schedule-nav">
        <button
          type="button"
          class="pos-nav-arrow"
          :title="scheduleViewMode === 'week' ? 'Tuần trước' : 'Ngày trước'"
          @click="scheduleViewMode === 'week' ? shiftWeek(-1) : shiftDay(-1)"
        >
          <AppIcon name="chevronLeft" :size="16" />
        </button>
        <button
          type="button"
          class="pos-nav-today-btn"
          @click="scheduleViewMode === 'week' ? goToCurrentWeek() : goToToday()"
        >
          {{ scheduleViewMode === 'week' ? 'Tuần này' : 'Hôm nay' }}
        </button>
        <button
          type="button"
          class="pos-nav-arrow"
          :title="scheduleViewMode === 'week' ? 'Tuần sau' : 'Ngày sau'"
          @click="scheduleViewMode === 'week' ? shiftWeek(1) : shiftDay(1)"
        >
          <AppIcon name="chevronRight" :size="16" />
        </button>
      </div>
    </header>

    <!-- Error Alert -->
    <div v-if="error" class="pos-alert-box is-error">
      <span>{{ error }}</span>
      <button type="button" @click="error = ''">×</button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="pos-loading-state">
      <div class="pos-spinner"></div>
      <p>Đang tải lịch trực của bạn...</p>
    </div>

    <template v-else>
      <!-- 2. WEEKLY CALENDAR VIEW -->
      <div v-if="scheduleViewMode === 'week'" class="pos-week-container">
        <div class="pos-week-grid">
          <div
            v-for="day in weekDays"
            :key="day.iso"
            class="pos-day-col"
            :class="{ 'is-today': day.iso === today }"
          >
            <div class="pos-day-head">
              <span class="pos-day-name">{{ day.label }}</span>
              <strong class="pos-day-date">{{ day.date }}</strong>
            </div>

            <div v-if="day.schedules.length" class="pos-day-shifts">
              <div
                v-for="schedule in day.schedules"
                :key="schedule.id"
                class="pos-shift-card"
                :class="schedule.status"
              >
                <div class="pos-shift-card-accent"></div>
                <div class="pos-shift-card-body">
                  <span class="pos-shift-time">{{ timeRange(schedule) }}</span>
                  <strong class="pos-shift-name">{{ shiftName(schedule) }}</strong>
                  <span class="pos-shift-venue">{{ schedule.venue_cluster?.name || 'Cụm sân' }}</span>
                  <span class="pos-shift-status-tag" :class="schedule.status">
                    {{ statusLabel(schedule.status) }}
                  </span>
                </div>
              </div>
            </div>

            <div v-else class="pos-day-empty">
              <span>Không có ca</span>
            </div>
          </div>
        </div>

        <!-- Summary List of All Shifts this Week -->
        <section v-if="schedules.length" class="pos-week-summary">
          <h3 class="pos-summary-title">Danh sách ca trực trong tuần ({{ schedules.length }} ca)</h3>
          <div class="pos-summary-grid">
            <div
              v-for="schedule in schedules"
              :key="schedule.id"
              class="pos-summary-card"
            >
              <div class="pos-summary-date">
                <strong>{{ fullDate(schedule.date) }}</strong>
                <span>{{ timeRange(schedule) }}</span>
              </div>
              <div class="pos-summary-info">
                <strong>{{ shiftName(schedule) }}</strong>
                <small>{{ schedule.venue_cluster?.name || 'Cụm sân được phân công' }}</small>
                <p v-if="schedule.notes">{{ schedule.notes }}</p>
              </div>
              <span class="pos-shift-status-tag" :class="schedule.status">
                {{ statusLabel(schedule.status) }}
              </span>
            </div>
          </div>
        </section>
      </div>

      <!-- 3. DAILY 24H TIMELINE STRIP VIEW -->
      <div v-else class="pos-day-container">
        <!-- 24h Timeline Axis -->
        <div class="pos-timeline-wrapper">
          <div class="pos-timeline-ticks">
            <span
              v-for="tick in timelineTicks"
              :key="tick.label"
              class="pos-timeline-tick"
              :style="{ left: tick.left + '%' }"
            >
              {{ tick.label }}
            </span>
          </div>

          <div class="pos-timeline-track">
            <!-- Hour Grid Cells -->
            <div
              v-for="h in 24"
              :key="h"
              class="pos-hour-grid-cell"
              :style="{ left: ((h - 1) / 24 * 100) + '%', width: (1 / 24 * 100) + '%' }"
            ></div>

            <!-- Shift Blocks -->
            <div
              v-for="block in todayTimelineBlocks"
              :key="block.id"
              class="pos-timeline-block"
              :class="block.statusClass"
              :style="block.style"
              :title="block.title + ' · ' + block.timeLabel"
            >
              <span class="pos-tb-title">{{ block.title }}</span>
              <span class="pos-tb-time">{{ block.timeLabel }}</span>
            </div>
          </div>
        </div>

        <!-- Shift List Below -->
        <div v-if="sortedSelectedDaySchedules.length" class="pos-day-list">
          <div
            v-for="sch in sortedSelectedDaySchedules"
            :key="sch.id"
            class="pos-day-item"
          >
            <div class="pos-day-item-left">
              <span class="pos-day-item-badge">{{ timeRange(sch) }}</span>
              <div class="pos-day-item-meta">
                <strong>{{ shiftName(sch) }}</strong>
                <span>{{ sch.venue_cluster?.name || 'Cụm sân' }}</span>
                <small v-if="sch.notes">{{ sch.notes }}</small>
              </div>
            </div>
            <span class="pos-shift-status-tag" :class="sch.status">
              {{ statusLabel(sch.status) }}
            </span>
          </div>
        </div>

        <!-- Empty state with 3D model illustration -->
        <div v-else class="pos-day-empty-state">
          <img :src="'/images/staff/schedule_empty_3d.jpg'" alt="3D Schedule Illustration" class="pos-3d-schedule-img" />
          <div class="pos-3d-empty-info">
            <h4>Không có ca trực vào ngày này</h4>
            <p>Bạn không có ca làm việc nào được phân công trong ngày {{ formattedSelectedDate }}.</p>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import SportIllustration from '../../components/common/SportIllustration.vue';
import { ownerStaffShiftService } from '../../services/ownerStaffShiftService.js';

export default {
  name: 'StaffSchedules',
  components: { AppIcon, SportIllustration },
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
    selectedDaySchedules() {
      return this.schedules.filter((schedule) => schedule.date === this.selectedDate);
    },
    sortedSelectedDaySchedules() {
      return [...this.selectedDaySchedules].sort((a, b) => {
        return (a.start_time || '').localeCompare(b.start_time || '');
      });
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
      return this.sortedSelectedDaySchedules.map((sch) => {
        const startMins = parseTimeMins(sch.start_time);
        let endMins = parseTimeMins(sch.end_time);
        if (endMins <= startMins && endMins !== 0) endMins += totalMins;
        if (endMins > totalMins) endMins = totalMins;
        const left = (startMins / totalMins) * 100;
        const width = Math.max(((endMins - startMins) / totalMins) * 100, 1);
        return {
          id: sch.id,
          title: sch.shift?.name || 'Ca trực',
          timeLabel: `${(sch.start_time || '').substring(0, 5)} - ${(sch.end_time || '').substring(0, 5)}`,
          statusClass: sch.status,
          style: { left: `${left}%`, width: `${width}%` },
        };
      });
    },
    timelineTicks() {
      const ticks = [];
      for (let h = 0; h <= 24; h += 3) {
        ticks.push({ label: `${String(h).padStart(2, '0')}:00`, left: (h / 24) * 100 });
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
        this.error = error.message || 'Không thể tải lịch trực.';
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
      return {
        scheduled: 'Đã lên lịch',
        checked_in: 'Đang trực',
        checked_out: 'Đã hoàn thành',
        absent: 'Vắng mặt',
        cancelled: 'Đã hủy',
      }[status] || 'Đã lên lịch';
    },
  },
};
</script>

<style scoped>
.pos-schedule-view {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  background: #ffffff;
  min-height: calc(100vh - 56px);
}

/* 1. TOP VIEW & NAVIGATION BAR */
.pos-schedule-topbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.pos-schedule-left {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.pos-view-tabs {
  display: flex;
  align-items: center;
  gap: 2px;
  background: #ffffff;
  border: 1px solid #d1d5db;
  padding: 2px;
  border-radius: 6px;
  height: 32px;
  box-sizing: border-box;
}

.pos-view-tab {
  border: 1px solid transparent;
  background: transparent;
  padding: 0 12px;
  height: 26px;
  display: inline-flex;
  align-items: center;
  border-radius: 4px;
  font-size: 12.5px;
  font-weight: 500;
  color: #334155;
  cursor: pointer;
  transition: all 0.12s ease;
}

.pos-view-tab:hover {
  background: #f0fdf4;
  color: #087642;
}

.pos-view-tab.active {
  background: #087642;
  color: #ffffff;
  border-color: #087642;
  font-weight: 500;
}

.pos-schedule-title {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
  letter-spacing: -0.01em;
}

.pos-schedule-nav {
  display: flex;
  align-items: center;
  gap: 2px;
  background: #ffffff;
  border: 1px solid #d1d5db;
  padding: 2px;
  border-radius: 6px;
  height: 32px;
  box-sizing: border-box;
}

.pos-nav-arrow {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border: none;
  background: transparent;
  color: #334155;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.12s ease;
}

.pos-nav-arrow:hover {
  background: #f0fdf4;
  color: #087642;
}

.pos-nav-today-btn {
  border: none;
  background: transparent;
  padding: 0 10px;
  height: 26px;
  display: inline-flex;
  align-items: center;
  font-size: 12.5px;
  font-weight: 500;
  color: #0f172a;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.12s ease;
}

.pos-nav-today-btn:hover {
  background: #f0fdf4;
  color: #087642;
}

/* ERROR & LOADING */
.pos-alert-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  background: #fee2e2;
  border: 1px solid #fca5a5;
  color: #dc2626;
}

.pos-loading-state {
  padding: 60px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  color: #64748b;
  font-size: 13px;
}

.pos-spinner {
  width: 28px;
  height: 28px;
  border: 2px solid #e5e7eb;
  border-top-color: #087642;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* 2. WEEKLY CALENDAR GRID */
.pos-week-container {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.pos-week-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 10px;
}

.pos-day-col {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 14px 10px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-height: 280px;
}

.pos-day-col.is-today {
  background: #ffffff;
  border-color: #087642;
  box-shadow: 0 0 0 1px #087642;
}

.pos-day-head {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding-bottom: 8px;
  border-bottom: 1px solid #e5e7eb;
}

.pos-day-name {
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
}

.pos-day-date {
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
}

.pos-day-shifts {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.pos-shift-card {
  background: #ffffff;
  border-radius: 4px;
  border: 1px solid #d1d5db;
  display: flex;
  overflow: hidden;
  transition: border-color 0.12s ease;
}

.pos-shift-card:hover {
  border-color: #087642;
}

.pos-shift-card-accent {
  width: 3px;
  background: #087642;
  flex-shrink: 0;
}

.pos-shift-card.checked_in .pos-shift-card-accent { background: #087642; }
.pos-shift-card.checked_out .pos-shift-card-accent { background: #64748b; }

.pos-shift-card-body {
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1;
}

.pos-shift-time {
  font-size: 10.5px;
  font-family: monospace;
  font-weight: 600;
  color: #087642;
}

.pos-shift-name {
  font-size: 12.5px;
  font-weight: 600;
  color: #0f172a;
}

.pos-shift-venue {
  font-size: 10.5px;
  color: #64748b;
}

.pos-shift-status-tag {
  display: inline-block;
  font-size: 10px;
  font-weight: 600;
  padding: 1px 5px;
  border-radius: 3px;
  width: fit-content;
  margin-top: 3px;
}

.pos-shift-status-tag.scheduled {
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #bfdbfe;
}

.pos-shift-status-tag.checked_in {
  background: #f0fdf4;
  color: #087642;
  border: 1px solid #bbf7d0;
}

.pos-shift-status-tag.checked_out {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.pos-day-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  color: #94a3b8;
  font-size: 11.5px;
}

/* WEEK SUMMARY */
.pos-week-summary {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.pos-summary-title {
  font-size: 14.5px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.pos-summary-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.pos-summary-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
}

.pos-summary-date {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pos-summary-date strong {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
}

.pos-summary-date span {
  font-size: 11.5px;
  color: #087642;
  font-family: monospace;
  font-weight: 600;
}

.pos-summary-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
}

.pos-summary-info strong {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
}

.pos-summary-info small {
  font-size: 11px;
  color: #64748b;
}

.pos-summary-info p {
  font-size: 11px;
  color: #94a3b8;
  margin: 2px 0 0;
}

/* 3. DAILY TIMELINE STRIP */
.pos-day-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.pos-timeline-wrapper {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 18px 16px;
}

.pos-timeline-ticks {
  position: relative;
  height: 18px;
  margin-bottom: 6px;
}

.pos-timeline-tick {
  position: absolute;
  transform: translateX(-50%);
  font-size: 10.5px;
  font-family: monospace;
  font-weight: 600;
  color: #64748b;
}

.pos-timeline-track {
  position: relative;
  height: 48px;
  background: #ffffff;
  border-radius: 6px;
  border: 1px solid #d1d5db;
  overflow: hidden;
}

.pos-hour-grid-cell {
  position: absolute;
  top: 0;
  bottom: 0;
  border-right: 1px solid #e5e7eb;
}

.pos-timeline-block {
  position: absolute;
  top: 4px;
  bottom: 4px;
  background: #087642;
  color: #ffffff;
  border-radius: 4px;
  padding: 3px 6px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  overflow: hidden;
}

.pos-timeline-block.checked_in {
  background: #087642;
}

.pos-tb-title {
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pos-tb-time {
  font-size: 9px;
  opacity: 0.9;
}

.pos-day-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pos-day-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.pos-day-item-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.pos-day-item-badge {
  font-family: monospace;
  font-size: 12px;
  font-weight: 600;
  color: #087642;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  padding: 4px 8px;
  border-radius: 4px;
}

.pos-day-item-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pos-day-item-meta strong {
  font-size: 13.5px;
  font-weight: 600;
  color: #0f172a;
}

.pos-day-item-meta span {
  font-size: 11.5px;
  color: #64748b;
}

.pos-day-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 50px 24px;
  gap: 16px;
  color: #64748b;
  font-size: 13px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.pos-3d-schedule-img {
  width: 120px;
  height: 120px;
  object-fit: contain;
  filter: drop-shadow(0 10px 20px rgba(8, 118, 66, 0.15));
  animation: float-slow 3s ease-in-out infinite alternate;
}

@keyframes float-slow {
  from { transform: translateY(0); }
  to { transform: translateY(-4px); }
}

.pos-3d-empty-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 4px;
}

.pos-3d-empty-info h4 {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.pos-3d-empty-info p {
  font-size: 12.5px;
  color: #64748b;
  margin: 0;
  max-width: 380px;
}

/* RESPONSIVE */
@media (max-width: 1024px) {
  .pos-week-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .pos-summary-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .pos-week-grid {
    grid-template-columns: 1fr;
  }
  .pos-schedule-view {
    padding: 14px;
  }
}
</style>
