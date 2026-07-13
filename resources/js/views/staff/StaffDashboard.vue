<template>
  <section class="staff-page">
    <p v-if="error" class="staff-alert staff-alert-error">{{ error }}</p>
    <p v-if="success" class="staff-alert staff-alert-success">{{ success }}</p>

    <div v-if="loading" class="staff-loading">Đang tải lịch trực...</div>

    <template v-else>
      <!-- Danh sách ca trực trong tuần dạng schedules (weekly grid) -->
      <section class="staff-list-block">
        <div class="staff-block-title-row">
          <div>
            <p class="staff-week-label">{{ weekLabel }}</p>
          </div>
          <div class="staff-week-actions">
            <button type="button" class="staff-week-btn" title="Tuần trước" @click="shiftWeek(-1)">
              <AppIcon name="chevronLeft" size="14" />
            </button>
            <button type="button" class="staff-week-btn" title="Tuần này" @click="goToCurrentWeek">Tuần này</button>
            <button type="button" class="staff-week-btn" title="Tuần sau" @click="shiftWeek(1)">
              <AppIcon name="chevronRight" size="14" />
            </button>
          </div>
        </div>

        <div class="staff-week-grid">
          <article v-for="day in weekDays" :key="day.iso" class="staff-day" :class="{ 'is-today': day.iso === today }">
            <div class="staff-day-header">
              <span class="staff-day-name">{{ day.label }}</span>
              <strong class="staff-day-date">{{ day.date }}</strong>
            </div>
            <div v-if="day.schedules.length" class="staff-day-shifts">
              <div v-for="schedule in day.schedules" :key="schedule.id" class="staff-day-shift">
                <span class="staff-day-time">{{ timeRange(schedule) }}</span>
                <strong class="staff-day-name-val">{{ shiftName(schedule) }}</strong>
                <span class="staff-day-venue">{{ schedule.venue_cluster?.name || 'Cụm sân' }}</span>
                <span class="staff-day-status-text" :class="schedule.status">{{ statusLabel(schedule.status) }}</span>
              </div>
            </div>
            <p v-else class="staff-no-shift">Không có ca</p>
          </article>
        </div>
      </section>
    </template>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { ownerStaffShiftService } from '../../services/ownerStaffShiftService.js';

export default {
  name: 'StaffDashboard',
  components: { AppIcon },
  data() {
    const todayStr = new Date().toISOString().slice(0, 10);
    return {
      schedules: [],
      weekStart: this.getMonday(new Date()),
      selectedWeekDate: todayStr,
      loading: true,
      error: '',
      success: '',
    };
  },
  computed: {
    today() {
      return this.formatIsoDate(new Date());
    },
    formattedToday() {
      return new Intl.DateTimeFormat('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date());
    },
    weekDays() {
      const labels = ['Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy', 'Chủ nhật'];
      return Array.from({ length: 7 }, (_, index) => {
        const date = new Date(this.weekStart);
        date.setDate(date.getDate() + index);
        const iso = this.formatIsoDate(date);
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
    formatIsoDate(date) {
      const offset = date.getTimezoneOffset();
      return new Date(date.getTime() - (offset * 60000)).toISOString().slice(0, 10);
    },
    shiftWeek(amount) {
      const next = new Date(this.weekStart);
      next.setDate(next.getDate() + (amount * 7));
      this.weekStart = next;
      this.selectedWeekDate = this.formatIsoDate(next);
      this.loadSchedules();
    },
    goToCurrentWeek() {
      const currentMonday = this.getMonday(new Date());
      this.weekStart = currentMonday;
      this.selectedWeekDate = this.formatIsoDate(currentMonday);
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
    async loadSchedules() {
      this.loading = true;
      this.error = '';
      try {
        const end = new Date(this.weekStart);
        end.setDate(end.getDate() + 6);
        const response = await ownerStaffShiftService.mySchedules({
          start_date: this.formatIsoDate(this.weekStart),
          end_date: this.formatIsoDate(end),
        });
        this.schedules = response.data || [];
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
    statusLabel(status) {
      return { scheduled: 'Đã lên lịch', checked_in: 'Đang trực', checked_out: 'Đã hoàn thành', absent: 'Vắng mặt', cancelled: 'Đã hủy' }[status] || 'Đã lên lịch';
    },
  },
};
</script>

<style scoped>
.staff-page {
  max-width: 1120px;
  margin: 0 auto;
  color: var(--admin-text);
  padding: 16px 0;
}

.staff-alert {
  margin: 0 0 16px;
  padding: 10px 12px;
  border-radius: var(--admin-radius);
  font-size: 13px;
}
.staff-alert-error {
  color: var(--admin-danger);
  background: var(--admin-danger-soft);
}
.staff-alert-success {
  color: var(--admin-primary);
  background: var(--admin-primary-soft);
}

.staff-loading {
  min-height: 124px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--admin-muted);
}

.staff-block-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}


.staff-empty-text {
  color: var(--admin-muted);
  font-size: var(--admin-font-size-base, 14px);
  margin: 0;
}

/* List Block styling - Clean flat list */
.staff-list-block {
  padding: 20px 0;
}

/* Weekly grid styling - flat, border-soft */
.staff-week-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  border-top: 1px solid var(--admin-border-soft);
  border-bottom: 1px solid var(--admin-border-soft);
  margin-top: 12px;
}

.staff-day {
  min-height: 180px;
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

.staff-day-header {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.staff-day-name {
  font-size: var(--admin-font-size-sm, 12px);
  color: var(--admin-muted);
  font-weight: 600;
}

.staff-day-date {
  font-size: var(--admin-font-size-base, 14px);
  font-weight: 700;
  color: var(--admin-text);
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
  font-weight: 700;
  color: var(--admin-text);
}

.staff-day-name-val {
  font-size: var(--admin-font-size-md, 13px);
  font-weight: 600;
  color: var(--admin-text);
}

.staff-day-venue {
  font-size: var(--admin-font-size-sm, 12px);
  color: var(--admin-muted);
}

.staff-day-status-text {
  font-size: var(--admin-font-size-sm, 12px);
  font-weight: 600;
  margin-top: 2px;
}

.staff-day-status-text.scheduled {
  color: var(--admin-muted);
}

.staff-day-status-text.checked_in {
  color: var(--admin-primary-dark);
}

.staff-day-status-text.checked_out {
  color: var(--admin-muted);
}

.staff-day-status-text.absent {
  color: var(--admin-danger);
}

.staff-day-status-text.cancelled {
  color: var(--admin-muted);
}

.staff-no-shift {
  font-size: 12px;
  color: var(--admin-faint);
  margin: 0;
}

/* Week actions */
.staff-week-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.staff-week-btn {
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

.staff-week-label {
  font-size: var(--admin-font-size-base, 14px) !important;
  font-weight: 500 !important;
  color: var(--admin-text) !important;
  margin: 0;
}

/* Responsive styles */
@media (max-width: 820px) {
  .staff-week-grid {
    grid-template-columns: 1fr;
    border-left: none;
    border-right: none;
  }

  .staff-day {
    min-height: auto;
    padding: 16px 0;
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

  .staff-day-header {
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
}
</style>